<?php

namespace Tests\Feature\Project;

use App\Modules\Client\Models\Client;
use App\Modules\Project\Models\Project;
use App\Modules\Settings\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProjectIndexControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_projects_without_requiring_quotation_module(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $user = User::factory()->create();
        $user->givePermissionTo(['view_projects', 'manage_all_projects']);

        $client = Client::create([
            'type' => 'company',
            'display_name' => 'Acme',
            'primary_email' => 'acme-projects@example.com',
            'lifecycle_stage' => 'client',
            'status' => 'active',
        ]);

        Project::create([
            'client_id' => $client->id,
            'title' => 'Website',
            'description' => 'Build the public website.',
            'status' => 'draft',
            'budget' => 1000,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/projects');

        $response->assertOk()
            ->assertJsonPath('data.projects.0.title', 'Website');
    }

    public function test_project_index_returns_exact_list_contract_without_pinned_n_plus_one(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $user = User::factory()->create();
        $user->givePermissionTo(['view_projects', 'manage_all_projects']);

        $client = Client::create([
            'type' => 'company',
            'display_name' => 'Acme',
            'primary_email' => 'acme-contract@example.com',
            'lifecycle_stage' => 'client',
            'status' => 'active',
        ]);

        $projects = collect(range(1, 3))->map(fn (int $index) => Project::create([
            'client_id' => $client->id,
            'title' => "Project {$index}",
            'description' => null,
            'status' => 'draft',
            'budget' => 1000 + $index,
        ]));

        DB::table('pinned_projects')->insert([
            'user_id' => $user->id,
            'project_id' => $projects->first()->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/projects?perPage=3');

        $queryCount = count(DB::getQueryLog());

        $response->assertOk()
            ->assertJsonPath('data.projects.0.quotationId', null)
            ->assertJsonPath('data.projects.0.description', null)
            ->assertJsonPath('data.projects.0.budget', null)
            ->assertJsonPath('data.projects.0.canViewBudget', false)
            ->assertJsonPath('data.projects.0.deadline', null)
            ->assertJsonPath('data.projects.0.isPinned', true);

        $this->assertSame([
            'projectId',
            'clientId',
            'quotationId',
            'title',
            'description',
            'status',
            'previousStatus',
            'budget',
            'canViewBudget',
            'deadline',
            'uiApprovedAt',
            'autoApprovalJobId',
            'progress',
            'totalTimeSpent',
            'isPinned',
            'client',
            'createdAt',
            'updatedAt',
            'createdBy',
            'updatedBy',
        ], array_keys($response->json('data.projects.0')));

        $this->assertLessThanOrEqual(5, $queryCount);
    }

    public function test_it_shows_a_project_without_requiring_quotation_module(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $user = User::factory()->create();
        $user->givePermissionTo(['view_projects', 'manage_all_projects']);

        $client = Client::create([
            'type' => 'company',
            'display_name' => 'Acme',
            'primary_email' => 'acme-project-show@example.com',
            'lifecycle_stage' => 'client',
            'status' => 'active',
        ]);

        $project = Project::create([
            'client_id' => $client->id,
            'title' => 'Mobile App',
            'description' => 'Build the mobile app.',
            'status' => 'draft',
            'budget' => 2000,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/projects/{$project->id}");

        $response->assertOk()
            ->assertJsonPath('data.title', 'Mobile App')
            ->assertJsonMissingPath('data.members')
            ->assertJsonMissingPath('data.files');
    }

    public function test_it_forbids_showing_a_project_outside_the_authorized_scope(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $user = User::factory()->create();
        $user->givePermissionTo('view_projects');

        $project = Project::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/projects/{$project->id}")
            ->assertForbidden();
    }
}
