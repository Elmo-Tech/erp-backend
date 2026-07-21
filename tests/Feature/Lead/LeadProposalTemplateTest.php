<?php

namespace Tests\Feature\Lead;

use App\Enums\LeadStage;
use App\Enums\LeadStatus;
use App\Modules\Lead\Models\Lead;
use App\Modules\Settings\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class LeadProposalTemplateTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_user_can_list_the_available_proposal_templates(): void
    {
        $user = User::factory()->create();
        Permission::findOrCreate('view_leads', 'web');
        $user->givePermissionTo('view_leads');

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/lead-proposal-templates')
            ->assertOk()
            ->assertExactJson([
                'success' => true,
                'message' => 'Proposal templates retrieved successfully.',
                'data' => [
                    ['id' => 1, 'name' => 'Standard Proposal', 'description' => 'A concise proposal for general projects and services.'],
                    ['id' => 2, 'name' => 'Enterprise Proposal', 'description' => 'A structured proposal for larger, multi-stakeholder engagements.'],
                    ['id' => 3, 'name' => 'SaaS Proposal', 'description' => 'A subscription-focused proposal for SaaS products and platforms.'],
                    ['id' => 4, 'name' => 'Retainer Proposal', 'description' => 'An ongoing-services proposal for monthly retainers.'],
                ],
            ]);
    }

    public function test_using_a_template_creates_a_private_downloadable_pdf(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        Permission::findOrCreate('manage_leads', 'web');
        $user->givePermissionTo('manage_leads');
        $lead = Lead::create([
            'title' => 'Customer Support Platform',
            'stage' => LeadStage::PROPOSAL,
            'status' => LeadStatus::ACTIVE,
            'expected_budget' => 25000,
            'metadata' => [
                'company_name' => 'Northstar Logistics',
                'contact_name' => 'Mona Ali',
            ],
        ]);
        $validityDate = now()->addMonth()->toDateString();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/leads/{$lead->id}/proposals", [
                'creation_type' => 'direct_upload',
                'proposal_source' => 'use_template',
                'template_id' => 1,
                'validity_date' => $validityDate,
                'notes' => 'Includes onboarding and knowledge transfer.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.proposal_source', 'use_template')
            ->assertJsonPath('data.template_id', 1)
            ->assertJsonPath('data.validity_date', $validityDate)
            ->assertJsonPath('data.file_mime_type', 'application/pdf');

        $download = $this->actingAs($user, 'sanctum')
            ->get($response->json('data.file_path'))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->assertStringStartsWith('%PDF-', $download->streamedContent());
    }

    public function test_template_generation_rejects_unknown_templates_and_missing_validity(): void
    {
        $user = User::factory()->create();
        Permission::findOrCreate('manage_leads', 'web');
        $user->givePermissionTo('manage_leads');
        $lead = Lead::create([
            'title' => 'Customer Support Platform',
            'stage' => LeadStage::PROPOSAL,
            'status' => LeadStatus::ACTIVE,
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/leads/{$lead->id}/proposals", [
                'creation_type' => 'direct_upload',
                'proposal_source' => 'use_template',
                'template_id' => 99,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['template_id', 'validity_date']);

        $this->assertDatabaseCount('proposals', 0);
    }
}
