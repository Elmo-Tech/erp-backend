<?php

namespace Tests\Feature\Lead;

use App\Enums\DocumentCreationType;
use App\Enums\LeadStage;
use App\Enums\LeadStatus;
use App\Modules\Lead\Models\Lead;
use App\Modules\Lead\Models\Proposal;
use App\Modules\Meetings\Models\Meeting;
use App\Modules\Settings\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class LeadApiContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_lead_index_returns_exact_pipeline_contract_without_n_plus_one_flags(): void
    {
        $user = User::factory()->create();
        Permission::findOrCreate('view_leads', 'web');
        $user->givePermissionTo('view_leads');

        $leads = collect(range(1, 3))->map(fn (int $index) => Lead::create([
            'title' => "Lead {$index}",
            'stage' => LeadStage::NEW_LEAD,
            'status' => LeadStatus::ACTIVE,
            'source' => 'website',
            'expected_budget' => 1000 * $index,
            'metadata' => [
                'company_name' => "Company {$index}",
                'primary_email' => "lead{$index}@example.test",
                'primary_phone' => "+20100000000{$index}",
            ],
            'assigned_to' => $user->id,
        ]));

        Meeting::create([
            'model_id' => $leads->first()->id,
            'model_type' => Lead::class,
            'status' => 'completed',
            'title' => 'Discovery',
            'assigned_to' => $user->id,
            'created_by' => $user->id,
            'scheduled_at' => now(),
        ]);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/leads');

        $response->assertOk();

        $firstLead = $response->json('data.0');

        $this->assertSame([
            'id',
            'title',
            'stage',
            'status',
            'company',
            'email',
            'phone',
            'expected_budget',
            'source',
            'assignedTo',
            'hasCompletedMeeting',
            'hasAcceptedProposalOrQuotation',
            'createdAt',
            'updatedAt',
        ], array_keys($firstLead));
        $this->assertTrue($firstLead['hasCompletedMeeting']);
        $this->assertLessThanOrEqual(4, count(DB::getQueryLog()));
    }

    public function test_explicit_meeting_skip_is_reported_as_resolved_pipeline_evidence(): void
    {
        $user = User::factory()->create();
        Permission::findOrCreate('view_leads', 'web');
        $user->givePermissionTo('view_leads');
        $lead = Lead::create([
            'title' => 'Meeting skipped lead',
            'stage' => LeadStage::MEETING,
            'status' => LeadStatus::ACTIVE,
        ]);
        Proposal::create([
            'lead_id' => $lead->id,
            'status' => 'draft',
            'creation_type' => DocumentCreationType::SKIPPED->value,
            'skip_reason' => 'The client asked to continue without a meeting.',
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/leads/{$lead->id}")
            ->assertOk()
            ->assertJsonPath('data.hasCompletedMeeting', true);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/leads')
            ->assertOk()
            ->assertJsonPath('data.0.hasCompletedMeeting', true);
    }
}
