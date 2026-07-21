<?php

namespace Tests\Feature\Lead;

use App\Enums\DocumentCreationType;
use App\Enums\LeadStage;
use App\Enums\LeadStatus;
use App\Modules\Lead\Models\Lead;
use App\Modules\Lead\Models\Proposal;
use App\Modules\Lead\Models\Quotation;
use App\Modules\Meetings\Models\Meeting;
use App\Modules\Settings\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class LeadPipelineStageGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_proposal_stage_requires_a_completed_lead_meeting(): void
    {
        $user = $this->userWithPermissions(['manage_leads']);
        $lead = $this->lead();

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/leads/{$lead->id}/stage", [
                'new_stage' => LeadStage::PROPOSAL->value,
                'stage_results' => [],
            ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Cannot advance: Complete the meeting and add notes first.');

        $this->assertSame(LeadStage::NEW_LEAD, $lead->fresh()->stage);
    }

    public function test_quotation_stage_allows_a_completed_lead_meeting(): void
    {
        $user = $this->userWithPermissions(['manage_leads']);
        $lead = $this->lead(['stage' => LeadStage::PROPOSAL]);
        $this->completedMeeting($lead);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/leads/{$lead->id}/stage", [
                'new_stage' => LeadStage::QUOTATION->value,
                'stage_results' => [[
                    'stage' => LeadStage::QUOTATION->value,
                    'type' => 'quotation',
                    'creation_type' => DocumentCreationType::SKIPPED->value,
                    'skip_reason' => 'Client requested a direct contract discussion.',
                ]],
            ])
            ->assertOk();

        $this->assertSame(LeadStage::QUOTATION, $lead->fresh()->stage);
    }

    public function test_meeting_stage_update_can_schedule_a_real_lead_meeting(): void
    {
        $user = $this->userWithPermissions(['manage_leads', 'schedule_meetings']);
        $lead = $this->lead();

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/leads/{$lead->id}/stage", [
                'new_stage' => LeadStage::MEETING->value,
                'stage_results' => [],
                'scheduled_meeting' => [
                    'meeting_type' => 'discovery',
                    'date' => '2026-05-01',
                    'time' => '10:30',
                    'location_type' => 'online',
                    'location_details' => '',
                    'internal_attendees' => [(string) $user->id],
                    'external_attendees' => ['primary-contact'],
                    'notes' => 'Client wants a backfilled discovery call.',
                ],
            ])
            ->assertOk();

        $this->assertDatabaseHas('meetings', [
            'model_type' => Lead::class,
            'model_id' => $lead->id,
            'meeting_type' => 'discovery',
            'description' => 'Client wants a backfilled discovery call.',
            'location' => 'online',
            'location_details' => null,
        ]);
        $this->assertSame(LeadStage::MEETING, $lead->fresh()->stage);
    }

    public function test_meeting_permission_denial_does_not_advance_the_lead(): void
    {
        $user = $this->userWithPermissions(['edit_leads']);
        $lead = $this->lead();

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/leads/{$lead->id}/stage", [
                'new_stage' => LeadStage::MEETING->value,
                'stage_results' => [],
                'scheduled_meeting' => [
                    'meeting_type' => 'discovery',
                    'date' => '2026-07-25',
                    'time' => '10:30',
                    'location_type' => 'online',
                    'internal_attendees' => [(string) $user->id],
                    'external_attendees' => [],
                ],
            ])
            ->assertForbidden();

        $this->assertSame(LeadStage::NEW_LEAD, $lead->fresh()->stage);
        $this->assertDatabaseCount('meetings', 0);
    }

    public function test_invalid_meeting_attendee_does_not_advance_the_lead(): void
    {
        $user = $this->userWithPermissions(['manage_leads', 'schedule_meetings']);
        $lead = $this->lead();

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/leads/{$lead->id}/stage", [
                'new_stage' => LeadStage::MEETING->value,
                'stage_results' => [],
                'scheduled_meeting' => [
                    'meeting_type' => 'discovery',
                    'date' => '2026-07-25',
                    'time' => '10:30',
                    'location_type' => 'online',
                    'internal_attendees' => ['999999'],
                    'external_attendees' => [],
                ],
            ])
            ->assertUnprocessable();

        $this->assertSame(LeadStage::NEW_LEAD, $lead->fresh()->stage);
        $this->assertDatabaseCount('meetings', 0);
    }

    public function test_jump_to_won_cannot_bypass_required_business_evidence(): void
    {
        $user = $this->userWithPermissions(['manage_leads', 'manage_contracts']);
        $lead = $this->lead();

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/leads/{$lead->id}/stage", [
                'new_stage' => LeadStage::WON->value,
                'stage_results' => [
                    [
                        'stage' => LeadStage::MEETING->value,
                        'type' => 'proposal',
                        'creation_type' => DocumentCreationType::SKIPPED->value,
                        'skip_reason' => 'No meeting evidence exists.',
                    ],
                    [
                        'stage' => LeadStage::PROPOSAL->value,
                        'type' => 'proposal',
                        'creation_type' => DocumentCreationType::SKIPPED->value,
                        'skip_reason' => 'No proposal evidence exists.',
                    ],
                    [
                        'stage' => LeadStage::QUOTATION->value,
                        'type' => 'quotation',
                        'creation_type' => DocumentCreationType::SKIPPED->value,
                        'skip_reason' => 'No quotation evidence exists.',
                    ],
                    [
                        'stage' => LeadStage::CONTRACT->value,
                        'type' => 'contract',
                        'creation_type' => DocumentCreationType::SKIPPED->value,
                        'skip_reason' => 'No contract evidence exists.',
                    ],
                ],
            ])
            ->assertUnprocessable();

        $this->assertSame(LeadStage::NEW_LEAD, $lead->fresh()->stage);
        $this->assertDatabaseCount('proposals', 0);
        $this->assertDatabaseCount('quotations', 0);
        $this->assertDatabaseCount('contracts', 0);
    }

    public function test_forward_jump_accepts_explicit_evidence_for_every_crossed_stage(): void
    {
        $user = $this->userWithPermissions(['manage_leads']);
        $lead = $this->lead(['stage' => LeadStage::MEETING]);
        $this->completedMeeting($lead);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/leads/{$lead->id}/stage", [
                'new_stage' => LeadStage::QUOTATION->value,
                'stage_results' => [
                    [
                        'stage' => LeadStage::PROPOSAL->value,
                        'type' => 'proposal',
                        'creation_type' => DocumentCreationType::SKIPPED->value,
                        'skip_reason' => 'The client supplied their own scope.',
                    ],
                    [
                        'stage' => LeadStage::QUOTATION->value,
                        'type' => 'quotation',
                        'creation_type' => DocumentCreationType::SKIPPED->value,
                        'skip_reason' => 'Pricing was approved outside the system.',
                    ],
                ],
            ])
            ->assertOk();

        $this->assertSame(LeadStage::QUOTATION, $lead->fresh()->stage);
        $this->assertDatabaseCount('proposals', 1);
        $this->assertDatabaseCount('quotations', 1);
    }

    public function test_pipeline_rejects_backward_stage_moves(): void
    {
        $user = $this->userWithPermissions(['edit_leads']);
        $activeLead = $this->lead(['stage' => LeadStage::PROPOSAL]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/leads/{$activeLead->id}/stage", [
                'new_stage' => LeadStage::MEETING->value,
                'stage_results' => [],
            ])
            ->assertUnprocessable();

        $this->assertSame(LeadStage::PROPOSAL, $activeLead->fresh()->stage);
    }

    public function test_pipeline_rejects_moves_from_a_terminal_stage(): void
    {
        $user = $this->userWithPermissions(['edit_leads']);
        $wonLead = $this->lead(['stage' => LeadStage::WON]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/leads/{$wonLead->id}/stage", [
                'new_stage' => LeadStage::LOST->value,
                'stage_results' => [],
            ])
            ->assertUnprocessable();

        $this->assertSame(LeadStage::WON, $wonLead->fresh()->stage);
    }

    public function test_edit_leads_permission_cannot_mark_a_lead_as_won_without_convert_permission(): void
    {
        $user = $this->userWithPermissions(['edit_leads']);
        $lead = $this->lead(['stage' => LeadStage::CONTRACT]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/leads/{$lead->id}/stage", [
                'new_stage' => LeadStage::WON->value,
                'stage_results' => [],
            ])
            ->assertForbidden();

        $this->assertSame(LeadStage::CONTRACT, $lead->fresh()->stage);
    }

    public function test_stage_evidence_cannot_claim_a_different_document_type(): void
    {
        $user = $this->userWithPermissions(['manage_leads']);
        $lead = $this->lead(['stage' => LeadStage::QUOTATION]);

        Quotation::create([
            'lead_id' => $lead->id,
            'status' => 'accepted',
            'creation_type' => DocumentCreationType::DIRECT_UPLOAD->value,
            'currency' => 'USD',
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/leads/{$lead->id}/stage", [
                'new_stage' => LeadStage::CONTRACT->value,
                'stage_results' => [[
                    'stage' => LeadStage::CONTRACT->value,
                    'type' => 'proposal',
                    'creation_type' => DocumentCreationType::SKIPPED->value,
                    'skip_reason' => 'Mismatched evidence must not bypass contract permission.',
                ]],
            ])
            ->assertUnprocessable();

        $this->assertSame(LeadStage::QUOTATION, $lead->fresh()->stage);
        $this->assertDatabaseCount('proposals', 0);
    }

    public function test_contract_stage_requires_an_accepted_proposal_or_quotation(): void
    {
        $user = $this->userWithPermissions(['manage_leads']);
        $lead = $this->lead(['stage' => LeadStage::QUOTATION]);
        $this->completedMeeting($lead);

        Proposal::create([
            'lead_id' => $lead->id,
            'status' => 'sent',
            'creation_type' => DocumentCreationType::DIRECT_UPLOAD->value,
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/leads/{$lead->id}/stage", [
                'new_stage' => LeadStage::CONTRACT->value,
                'stage_results' => [],
            ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Cannot advance: An accepted proposal or quotation is required first.');

        $this->assertSame(LeadStage::QUOTATION, $lead->fresh()->stage);
    }

    public function test_contract_stage_allows_an_accepted_quotation(): void
    {
        $user = $this->userWithPermissions(['manage_leads', 'create_contracts']);
        $lead = $this->lead(['stage' => LeadStage::QUOTATION]);
        $this->completedMeeting($lead);

        Quotation::create([
            'lead_id' => $lead->id,
            'status' => 'accepted',
            'creation_type' => DocumentCreationType::DIRECT_UPLOAD->value,
            'currency' => 'USD',
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/leads/{$lead->id}/stage", [
                'new_stage' => LeadStage::CONTRACT->value,
                'stage_results' => [[
                    'stage' => LeadStage::CONTRACT->value,
                    'type' => 'contract',
                    'creation_type' => DocumentCreationType::SKIPPED->value,
                    'skip_reason' => 'Contract will be recorded externally.',
                ]],
            ])
            ->assertOk();

        $this->assertSame(LeadStage::CONTRACT, $lead->fresh()->stage);
    }

    public function test_quotation_status_can_be_updated_from_the_lead_endpoint(): void
    {
        $user = $this->userWithPermissions(['manage_leads']);
        $lead = $this->lead();
        $quotation = Quotation::create([
            'lead_id' => $lead->id,
            'status' => 'draft',
            'creation_type' => DocumentCreationType::DIRECT_UPLOAD->value,
            'currency' => 'USD',
        ]);

        $this->actingAs($user, 'sanctum')
            ->patchJson("/api/v1/leads/{$lead->id}/quotations/{$quotation->id}", [
                'status' => 'accepted',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'accepted');

        $this->assertSame('accepted', $quotation->fresh()->status->value);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function lead(array $attributes = []): Lead
    {
        return Lead::create(array_merge([
            'title' => 'Enterprise CRM',
            'stage' => LeadStage::NEW_LEAD,
            'status' => LeadStatus::ACTIVE,
        ], $attributes));
    }

    private function completedMeeting(Lead $lead): Meeting
    {
        return Meeting::create([
            'model_id' => $lead->id,
            'model_type' => Lead::class,
            'status' => 'completed',
            'is_completed' => true,
            'title' => 'Discovery call',
            'outcome_summary' => 'Qualified budget and stakeholders.',
            'completed_at' => now(),
        ]);
    }

    /**
     * @param  array<int, string>  $permissions
     */
    private function userWithPermissions(array $permissions): User
    {
        $user = User::factory()->create();

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $user->givePermissionTo($permissions);

        return $user;
    }
}
