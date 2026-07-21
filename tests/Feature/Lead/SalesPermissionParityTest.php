<?php

namespace Tests\Feature\Lead;

use App\Enums\DocumentCreationType;
use App\Enums\LeadStage;
use App\Enums\LeadStatus;
use App\Modules\Lead\Models\Contract;
use App\Modules\Lead\Models\Lead;
use App\Modules\Lead\Models\Quotation;
use App\Modules\Meetings\Models\Meeting;
use App\Modules\Settings\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class SalesPermissionParityTest extends TestCase
{
    use RefreshDatabase;

    public function test_granular_lead_permissions_allow_only_their_matching_actions(): void
    {
        $creator = $this->userWithPermissions(['create_leads']);

        $response = $this->actingAs($creator, 'sanctum')
            ->postJson('/api/v1/leads', ['title' => 'Granular sales lead'])
            ->assertCreated();

        $leadId = $response->json('data.id');

        $this->actingAs($creator, 'sanctum')
            ->getJson("/api/v1/leads/{$leadId}")
            ->assertForbidden();

        $viewer = $this->userWithPermissions(['view_leads']);

        $this->actingAs($viewer, 'sanctum')
            ->getJson("/api/v1/leads/{$leadId}")
            ->assertOk();

        $this->actingAs($viewer, 'sanctum')
            ->postJson('/api/v1/leads', ['title' => 'Forbidden lead'])
            ->assertForbidden();

        $deleter = $this->userWithPermissions(['delete_leads']);

        $this->actingAs($deleter, 'sanctum')
            ->deleteJson("/api/v1/leads/{$leadId}/pipeline")
            ->assertOk();

        $this->assertSoftDeleted('leads', ['id' => $leadId]);
    }

    public function test_manage_leads_remains_an_umbrella_for_lead_and_quotation_actions(): void
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
                    'skip_reason' => 'Permission contract test.',
                    'currency' => 'USD',
                ]],
            ])
            ->assertOk();

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/leads/{$lead->id}/quotations")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_edit_leads_cannot_create_embedded_sales_documents_without_document_permission(): void
    {
        $editor = $this->userWithPermissions(['edit_leads']);
        $lead = $this->lead(['stage' => LeadStage::PROPOSAL]);

        $this->actingAs($editor, 'sanctum')
            ->postJson("/api/v1/leads/{$lead->id}/stage", [
                'new_stage' => LeadStage::QUOTATION->value,
                'stage_results' => [[
                    'stage' => LeadStage::QUOTATION->value,
                    'type' => 'quotation',
                    'creation_type' => DocumentCreationType::SKIPPED->value,
                    'skip_reason' => 'Permission contract test.',
                    'currency' => 'USD',
                ]],
            ])
            ->assertForbidden();

        $this->assertDatabaseEmpty('quotations');
    }

    public function test_convert_leads_permission_converts_without_granting_other_lead_actions(): void
    {
        $converter = $this->userWithPermissions(['convert_leads']);
        $lead = $this->lead(['stage' => LeadStage::WON]);

        $this->actingAs($converter, 'sanctum')
            ->postJson("/api/v1/leads/{$lead->id}/convert")
            ->assertOk()
            ->assertJsonPath('data.client_name', 'Enterprise CRM');

        $this->actingAs($converter, 'sanctum')
            ->postJson('/api/v1/leads', ['title' => 'Forbidden lead'])
            ->assertForbidden();

        $this->assertSame(LeadStatus::ARCHIVED, $lead->fresh()->status);
    }

    public function test_lead_cannot_be_converted_before_it_is_won(): void
    {
        $converter = $this->userWithPermissions(['convert_leads']);
        $lead = $this->lead();

        $this->actingAs($converter, 'sanctum')
            ->postJson("/api/v1/leads/{$lead->id}/convert")
            ->assertUnprocessable();

        $this->assertSame(LeadStatus::ACTIVE, $lead->fresh()->status);
        $this->assertNull($lead->fresh()->client_id);
    }

    public function test_conversion_preserves_lead_company_and_primary_contact_data(): void
    {
        $converter = $this->userWithPermissions(['convert_leads', 'view_clients']);
        $lead = $this->lead([
            'stage' => LeadStage::WON,
            'metadata' => [
                'company_name' => 'Acme LLC',
                'contact_name' => 'Mona Ali',
                'primary_email' => 'mona@acme.test',
                'primary_phone' => '+201001234567',
            ],
        ]);

        $clientId = $this->actingAs($converter, 'sanctum')
            ->postJson("/api/v1/leads/{$lead->id}/convert")
            ->assertOk()
            ->json('data.client_id');

        $this->actingAs($converter, 'sanctum')
            ->getJson("/api/v1/clients/{$clientId}")
            ->assertOk()
            ->assertJsonPath('data.companyName', 'Acme LLC')
            ->assertJsonPath('data.primaryEmail', 'mona@acme.test')
            ->assertJsonPath('data.primaryPhone', '+201001234567')
            ->assertJsonPath('data.contacts.0.displayName', 'Mona Ali')
            ->assertJsonPath('data.contacts.0.email', 'mona@acme.test')
            ->assertJsonPath('data.contacts.0.phone', '+201001234567')
            ->assertJsonPath('data.contacts.0.isPrimary', true);
    }

    public function test_repeated_conversion_returns_the_original_client_and_project(): void
    {
        $converter = $this->userWithPermissions(['convert_leads', 'view_clients', 'view_projects']);
        $lead = $this->lead(['stage' => LeadStage::WON]);

        $first = $this->actingAs($converter, 'sanctum')
            ->postJson("/api/v1/leads/{$lead->id}/convert")
            ->assertOk();

        $second = $this->actingAs($converter, 'sanctum')
            ->postJson("/api/v1/leads/{$lead->id}/convert")
            ->assertOk();

        $this->assertSame($first->json('data.client_id'), $second->json('data.client_id'));
        $this->assertSame($first->json('data.project_id'), $second->json('data.project_id'));

        $this->actingAs($converter, 'sanctum')
            ->getJson('/api/v1/clients/'.$first->json('data.client_id').'/projects')
            ->assertOk()
            ->assertJsonCount(1, 'data.projects');
    }

    public function test_quotation_pipeline_permissions_are_granular_and_nested_scope_is_enforced(): void
    {
        $firstLead = $this->lead();
        $secondLead = $this->lead(['title' => 'Other lead']);
        $quotation = $this->quotation($firstLead);
        $editor = $this->userWithPermissions(['edit_quotations']);

        $this->actingAs($editor, 'sanctum')
            ->patchJson("/api/v1/leads/{$firstLead->id}/pipeline/quotations/{$quotation->id}", [
                'status' => 'accepted',
            ])
            ->assertOk();

        $this->actingAs($editor, 'sanctum')
            ->patchJson("/api/v1/leads/{$secondLead->id}/pipeline/quotations/{$quotation->id}", [
                'status' => 'rejected',
            ])
            ->assertNotFound();

        $this->actingAs($editor, 'sanctum')
            ->deleteJson("/api/v1/leads/{$firstLead->id}/quotations/{$quotation->id}")
            ->assertNotFound();

        $this->assertSame('accepted', $quotation->fresh()->status->value);

        $this->assertDatabaseHas('quotations', ['id' => $quotation->id, 'deleted_at' => null]);
    }

    public function test_create_and_view_quotation_permissions_are_independent(): void
    {
        $lead = $this->lead(['stage' => LeadStage::PROPOSAL]);
        $this->completedMeeting($lead);
        $creator = $this->userWithPermissions(['edit_leads', 'create_quotations']);

        $this->actingAs($creator, 'sanctum')
            ->postJson("/api/v1/leads/{$lead->id}/stage", [
                'new_stage' => LeadStage::QUOTATION->value,
                'stage_results' => [[
                    'stage' => LeadStage::QUOTATION->value,
                    'type' => 'quotation',
                    'creation_type' => DocumentCreationType::SKIPPED->value,
                    'skip_reason' => 'Permission contract test.',
                    'currency' => 'USD',
                ]],
            ])
            ->assertOk();

        $this->actingAs($creator, 'sanctum')
            ->getJson("/api/v1/leads/{$lead->id}/quotations")
            ->assertForbidden();

        $viewer = $this->userWithPermissions(['view_quotations']);

        $this->actingAs($viewer, 'sanctum')
            ->getJson("/api/v1/leads/{$lead->id}/quotations")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_contract_sign_is_a_pipeline_action_and_delete_is_not_public(): void
    {
        $lead = $this->lead();
        $contract = $this->contract($lead);
        $signer = $this->userWithPermissions(['sign_contracts']);

        $this->actingAs($signer, 'sanctum')
            ->postJson("/api/v1/leads/{$lead->id}/pipeline/contracts/{$contract->id}/sign")
            ->assertOk()
            ->assertJsonPath('data.status', 'signed');

        $this->actingAs($signer, 'sanctum')
            ->postJson("/api/v1/leads/{$lead->id}/pipeline/contracts/{$contract->id}/sign")
            ->assertOk()
            ->assertJsonPath('data.status', 'signed');

        $this->actingAs($signer, 'sanctum')
            ->deleteJson("/api/v1/leads/{$lead->id}/contracts/{$contract->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('contracts', ['id' => $contract->id, 'deleted_at' => null]);
    }

    public function test_manage_contracts_is_an_umbrella_with_nested_lead_scope(): void
    {
        $manager = $this->userWithPermissions(['manage_contracts']);
        $lead = $this->lead();
        $foreignLead = $this->lead(['title' => 'Foreign lead']);

        $contractId = $this->contract($lead)->id;

        $this->actingAs($manager, 'sanctum')
            ->postJson("/api/v1/leads/{$foreignLead->id}/pipeline/contracts/{$contractId}/sign")
            ->assertNotFound();

        $this->actingAs($manager, 'sanctum')
            ->postJson("/api/v1/leads/{$lead->id}/pipeline/contracts/{$contractId}/sign")
            ->assertOk();

        $this->actingAs($manager, 'sanctum')
            ->deleteJson("/api/v1/leads/{$lead->id}/contracts/{$contractId}")
            ->assertNotFound();

        $this->assertDatabaseHas('contracts', ['id' => $contractId, 'deleted_at' => null]);
    }

    public function test_create_contracts_cannot_mark_a_contract_signed_without_sign_permission(): void
    {
        $creator = $this->userWithPermissions(['edit_leads', 'create_contracts']);
        $lead = $this->preparedContractLead();

        $this->actingAs($creator, 'sanctum')
            ->postJson("/api/v1/leads/{$lead->id}/stage", [
                'new_stage' => LeadStage::CONTRACT->value,
                'stage_results' => [[
                    'stage' => LeadStage::CONTRACT->value,
                    'type' => 'contract',
                    'creation_type' => DocumentCreationType::SKIPPED->value,
                    'skip_reason' => 'Permission contract test.',
                    'contract_model' => 'standard',
                ]],
            ])
            ->assertOk();

        $this->assertDatabaseHas('contracts', ['lead_id' => $lead->id, 'status' => 'draft']);

        $signedLead = $this->preparedContractLead('Signed permission lead');

        $this->actingAs($creator, 'sanctum')
            ->postJson("/api/v1/leads/{$signedLead->id}/stage", [
                'new_stage' => LeadStage::CONTRACT->value,
                'stage_results' => [[
                    'stage' => LeadStage::CONTRACT->value,
                    'type' => 'contract',
                    'creation_type' => DocumentCreationType::SKIPPED->value,
                    'skip_reason' => 'Permission contract test.',
                    'contract_model' => 'standard',
                    'status' => 'signed',
                ]],
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('contracts', 1);
    }

    private function lead(array $attributes = []): Lead
    {
        return Lead::create(array_merge([
            'title' => 'Enterprise CRM',
            'stage' => LeadStage::NEW_LEAD,
            'status' => LeadStatus::ACTIVE,
        ], $attributes));
    }

    private function quotation(Lead $lead): Quotation
    {
        return Quotation::create([
            'lead_id' => $lead->id,
            'status' => 'draft',
            'creation_type' => DocumentCreationType::DIRECT_UPLOAD->value,
            'currency' => 'USD',
        ]);
    }

    private function contract(Lead $lead): Contract
    {
        return Contract::create([
            'lead_id' => $lead->id,
            'status' => 'draft',
            'creation_type' => DocumentCreationType::DIRECT_UPLOAD->value,
            'contract_model' => 'standard',
        ]);
    }

    private function completedMeeting(Lead $lead): void
    {
        Meeting::create([
            'model_id' => $lead->id,
            'model_type' => Lead::class,
            'status' => 'completed',
            'title' => 'Discovery',
            'scheduled_at' => now(),
        ]);
    }

    private function preparedContractLead(string $title = 'Contract permission lead'): Lead
    {
        $lead = $this->lead(['title' => $title, 'stage' => LeadStage::QUOTATION]);
        $this->completedMeeting($lead);
        Quotation::create([
            'lead_id' => $lead->id,
            'status' => 'accepted',
            'creation_type' => DocumentCreationType::SKIPPED->value,
            'currency' => 'USD',
        ]);

        return $lead;
    }

    /**
     * @param  list<string>  $permissions
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
