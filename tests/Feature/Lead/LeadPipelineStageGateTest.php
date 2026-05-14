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
        $lead = $this->lead();
        $this->completedMeeting($lead);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/leads/{$lead->id}/stage", [
                'new_stage' => LeadStage::QUOTATION->value,
                'stage_results' => [],
            ])
            ->assertOk();

        $this->assertSame(LeadStage::QUOTATION, $lead->fresh()->stage);
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
                'stage_results' => [],
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
