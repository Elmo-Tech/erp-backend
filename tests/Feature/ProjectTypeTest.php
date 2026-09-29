<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Lead;
use App\Models\LookupOption;
use App\Models\Project;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ProjectTypeTest extends TestCase
{
    use RefreshDatabase;

    public function test_direct_project_creation_assigns_an_active_project_type(): void
    {
        $actor = $this->userWithPermissions(['create_projects']);
        $client = Client::factory()->create();
        $projectType = $this->projectType('website');

        $response = $this->actingAs($actor, 'sanctum')
            ->postJson('/api/v1/projects/direct', $this->directProjectData($client, [
                'projectTypeId' => $projectType->id,
            ]))
            ->assertCreated()
            ->assertJsonPath('data.projectTypeId', $projectType->id)
            ->assertJsonPath('data.projectType.projectTypeId', $projectType->id)
            ->assertJsonPath('data.projectType.label', 'Website');

        $this->assertDatabaseHas('projects', [
            'id' => $response->json('data.projectId'),
            'project_type_id' => $projectType->id,
        ]);
    }

    public function test_direct_project_creation_rejects_inactive_and_wrong_type_options(): void
    {
        $actor = $this->userWithPermissions(['create_projects']);
        $client = Client::factory()->create();
        $inactiveProjectType = $this->projectType('legacy', false);
        $taskCategory = LookupOption::create([
            'type' => 'task_category',
            'value' => 'development',
            'label' => 'Development',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->actingAs($actor, 'sanctum')
            ->postJson('/api/v1/projects/direct', $this->directProjectData($client, [
                'projectTypeId' => $inactiveProjectType->id,
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('projectTypeId');

        $this->actingAs($actor, 'sanctum')
            ->postJson('/api/v1/projects/direct', $this->directProjectData($client, [
                'projectTypeId' => $taskCategory->id,
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('projectTypeId');
    }

    public function test_quotation_conversion_requires_an_active_project_type(): void
    {
        $actor = $this->userWithPermissions(['create_projects']);
        $client = Client::factory()->create();
        $lead = Lead::factory()->create(['client_id' => $client->id]);
        $quotation = Quotation::factory()->accepted()->create(['lead_id' => $lead->id]);
        $inactiveProjectType = $this->projectType('inactive-conversion', false);
        $activeProjectType = $this->projectType('active-conversion');

        $this->actingAs($actor, 'sanctum')
            ->postJson('/api/v1/projects/convert', [
                'quotationId' => $quotation->id,
                'projectTypeId' => $inactiveProjectType->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('projectTypeId');

        $this->actingAs($actor, 'sanctum')
            ->postJson('/api/v1/projects/convert', [
                'quotationId' => $quotation->id,
                'projectTypeId' => $activeProjectType->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.projectTypeId', $activeProjectType->id);

        $this->assertDatabaseHas('projects', [
            'quotation_id' => $quotation->id,
            'project_type_id' => $activeProjectType->id,
        ]);
    }

    public function test_quotation_based_project_creation_assigns_an_active_project_type(): void
    {
        $actor = $this->userWithPermissions(['create_projects']);
        $client = Client::factory()->create();
        $lead = Lead::factory()->create(['client_id' => $client->id]);
        $quotation = Quotation::factory()->accepted()->create(['lead_id' => $lead->id]);
        $projectType = $this->projectType('quotation-created');

        $this->actingAs($actor, 'sanctum')
            ->postJson('/api/v1/projects', [
                'quotationId' => $quotation->id,
                'projectTypeId' => $projectType->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.projectTypeId', $projectType->id);

        $this->assertDatabaseHas('projects', [
            'quotation_id' => $quotation->id,
            'project_type_id' => $projectType->id,
        ]);
    }

    public function test_project_update_can_retain_but_not_change_to_an_inactive_project_type(): void
    {
        $actor = $this->userWithPermissions(['manage_all_projects', 'view_all_projects']);
        $currentType = $this->projectType('current-legacy', false);
        $otherInactiveType = $this->projectType('other-legacy', false);
        $project = Project::factory()->create(['project_type_id' => $currentType->id]);

        $this->actingAs($actor, 'sanctum')
            ->putJson("/api/v1/projects/{$project->id}", [
                'projectTypeId' => $currentType->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.projectTypeId', $currentType->id);

        $this->actingAs($actor, 'sanctum')
            ->putJson("/api/v1/projects/{$project->id}", [
                'projectTypeId' => $otherInactiveType->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('projectTypeId');

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'project_type_id' => $currentType->id,
        ]);
    }

    public function test_projects_can_be_filtered_by_project_type(): void
    {
        $actor = $this->userWithPermissions(['view_all_projects']);
        $projectType = $this->projectType('filtered');
        $otherProjectType = $this->projectType('other');
        $matchingProject = Project::factory()->create(['project_type_id' => $projectType->id]);
        Project::factory()->create(['project_type_id' => $otherProjectType->id]);

        $this->actingAs($actor, 'sanctum')
            ->getJson("/api/v1/projects?filter[projectTypeId]={$projectType->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data.projects')
            ->assertJsonPath('data.projects.0.projectId', $matchingProject->id)
            ->assertJsonPath('data.projects.0.projectTypeId', $projectType->id);
    }

    public function test_project_types_assigned_to_projects_cannot_be_deleted_but_can_be_deactivated(): void
    {
        $actor = User::factory()->create();
        $projectType = $this->projectType('retained');
        Project::factory()->create(['project_type_id' => $projectType->id]);

        $this->actingAs($actor, 'sanctum')
            ->deleteJson("/api/v1/settings/lookup-options/{$projectType->id}")
            ->assertStatus(409);

        $this->actingAs($actor, 'sanctum')
            ->putJson("/api/v1/settings/lookup-options/{$projectType->id}", [
                'type' => 'project_type',
                'value' => $projectType->value,
                'label' => $projectType->label,
                'sort_order' => $projectType->sort_order,
                'is_active' => false,
            ])
            ->assertOk();

        $this->assertDatabaseHas('lookup_options', [
            'id' => $projectType->id,
            'is_active' => false,
        ]);
    }

    private function projectType(string $value, bool $isActive = true): LookupOption
    {
        return LookupOption::create([
            'type' => 'project_type',
            'value' => $value,
            'label' => str($value)->headline()->toString(),
            'sort_order' => 1,
            'is_active' => $isActive,
        ]);
    }

    private function directProjectData(Client $client, array $overrides = []): array
    {
        return array_merge([
            'clientId' => $client->id,
            'title' => 'Project Type Test',
            'budget' => 1000,
            'deadline' => now()->addWeek()->toDateString(),
        ], $overrides);
    }

    private function userWithPermissions(array $permissions): User
    {
        $user = User::factory()->create();

        foreach ($permissions as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }

        return $user;
    }
}
