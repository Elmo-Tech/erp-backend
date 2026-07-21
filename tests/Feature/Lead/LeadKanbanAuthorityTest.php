<?php

namespace Tests\Feature\Lead;

use App\Enums\DocumentCreationType;
use App\Enums\LeadStage;
use App\Enums\LeadStatus;
use App\Modules\Lead\Models\Contract;
use App\Modules\Lead\Models\Lead;
use App\Modules\Lead\Models\Quotation;
use App\Modules\Settings\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class LeadKanbanAuthorityTest extends TestCase
{
    use RefreshDatabase;

    public function test_standalone_document_commands_are_not_public_routes(): void
    {
        $user = $this->userWithPermissions(['manage_leads', 'manage_contracts']);
        $lead = $this->lead();
        $quotation = Quotation::create([
            'lead_id' => $lead->id,
            'status' => 'draft',
            'creation_type' => DocumentCreationType::DIRECT_UPLOAD->value,
            'currency' => 'USD',
        ]);
        $contract = Contract::create([
            'lead_id' => $lead->id,
            'status' => 'draft',
            'creation_type' => DocumentCreationType::DIRECT_UPLOAD->value,
            'contract_model' => 'standard',
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/leads/{$lead->id}/proposals", [
                'creation_type' => DocumentCreationType::SKIPPED->value,
                'skip_reason' => 'Legacy details-page command.',
            ])
            ->assertMethodNotAllowed();

        $this->actingAs($user, 'sanctum')
            ->patchJson("/api/v1/leads/{$lead->id}/quotations/{$quotation->id}", ['status' => 'accepted'])
            ->assertNotFound();

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/leads/{$lead->id}/contracts/{$contract->id}/sign")
            ->assertNotFound();

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/v1/leads/{$lead->id}")
            ->assertMethodNotAllowed();

        $this->assertDatabaseHas('leads', ['id' => $lead->id, 'deleted_at' => null]);
    }

    public function test_document_lifecycle_commands_are_available_under_the_pipeline(): void
    {
        $user = $this->userWithPermissions(['manage_leads', 'manage_contracts']);
        $lead = $this->lead();
        $quotation = Quotation::create([
            'lead_id' => $lead->id,
            'status' => 'draft',
            'creation_type' => DocumentCreationType::DIRECT_UPLOAD->value,
            'currency' => 'USD',
        ]);
        $contract = Contract::create([
            'lead_id' => $lead->id,
            'status' => 'draft',
            'creation_type' => DocumentCreationType::DIRECT_UPLOAD->value,
            'contract_model' => 'standard',
        ]);

        $this->actingAs($user, 'sanctum')
            ->patchJson("/api/v1/leads/{$lead->id}/pipeline/quotations/{$quotation->id}", ['status' => 'accepted'])
            ->assertOk()
            ->assertJsonPath('data.status', 'accepted');

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/leads/{$lead->id}/pipeline/contracts/{$contract->id}/sign")
            ->assertOk()
            ->assertJsonPath('data.status', 'signed');

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/v1/leads/{$lead->id}/pipeline")
            ->assertOk();

        $this->assertSoftDeleted('leads', ['id' => $lead->id]);
    }

    private function lead(): Lead
    {
        return Lead::create([
            'title' => 'Kanban authority lead',
            'stage' => LeadStage::NEW_LEAD,
            'status' => LeadStatus::ACTIVE,
        ]);
    }

    /** @param list<string> $permissions */
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
