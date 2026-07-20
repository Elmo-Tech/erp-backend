<?php

namespace Tests\Feature\Project;

use App\Enums\ProjectRole;
use App\Modules\Project\Models\ChangeRequest;
use App\Modules\Project\Models\Project;
use App\Modules\Project\Models\ProjectMember;
use App\Modules\Settings\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ChangeRequestAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_nonmember_cannot_list_or_read_foreign_project_change_requests(): void
    {
        $outsider = $this->userWithPermissions(['view_projects']);
        $project = Project::factory()->create();
        $changeRequest = $this->changeRequestFor($project);

        $this->actingAs($outsider, 'sanctum')
            ->getJson("/api/v1/projects/{$project->id}/change-requests")
            ->assertForbidden();

        $this->actingAs($outsider, 'sanctum')
            ->getJson("/api/v1/projects/{$project->id}/change-requests/{$changeRequest->id}")
            ->assertForbidden();
    }

    public function test_nonmember_cannot_create_a_change_request_for_a_foreign_project(): void
    {
        $outsider = $this->userWithPermissions(['manage_change_requests']);
        $project = Project::factory()->create();

        $this->actingAs($outsider, 'sanctum')
            ->postJson("/api/v1/projects/{$project->id}/change-requests", $this->changeRequestPayload())
            ->assertForbidden();

        $this->assertDatabaseCount('change_requests', 0);
    }

    public function test_nonmember_cannot_update_a_foreign_project_change_request(): void
    {
        $outsider = $this->userWithPermissions(['manage_change_requests']);
        $project = Project::factory()->create();
        $changeRequest = $this->changeRequestFor($project);

        $this->actingAs($outsider, 'sanctum')
            ->patchJson("/api/v1/projects/{$project->id}/change-requests/{$changeRequest->id}", [
                'title' => 'Unauthorized commercial change',
            ])
            ->assertForbidden();

        $this->assertSame('Scoped commercial request', $changeRequest->fresh()->title);
    }

    public function test_delete_capability_does_not_bypass_parent_project_scope(): void
    {
        $outsider = $this->userWithPermissions(['manage_change_requests', 'delete_change_requests']);
        $project = Project::factory()->create();
        $changeRequest = $this->changeRequestFor($project);

        $this->actingAs($outsider, 'sanctum')
            ->deleteJson("/api/v1/projects/{$project->id}/change-requests/{$changeRequest->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('change_requests', ['id' => $changeRequest->id, 'deleted_at' => null]);
    }

    public function test_authorized_project_member_preserves_change_request_workflow(): void
    {
        $member = $this->projectMemberWithPermissions([
            'view_projects',
            'manage_change_requests',
            'delete_change_requests',
        ]);

        $this->actingAs($member['user'], 'sanctum')
            ->postJson(
                "/api/v1/projects/{$member['project']->id}/change-requests",
                $this->changeRequestPayload()
            )
            ->assertCreated();

        $changeRequest = ChangeRequest::query()->sole();

        $this->actingAs($member['user'], 'sanctum')
            ->patchJson(
                "/api/v1/projects/{$member['project']->id}/change-requests/{$changeRequest->id}",
                ['title' => 'Approved scope extension']
            )
            ->assertOk();

        $this->actingAs($member['user'], 'sanctum')
            ->getJson("/api/v1/projects/{$member['project']->id}/change-requests/{$changeRequest->id}")
            ->assertOk()
            ->assertJsonPath('data.title', 'Approved scope extension');

        $this->actingAs($member['user'], 'sanctum')
            ->deleteJson("/api/v1/projects/{$member['project']->id}/change-requests/{$changeRequest->id}")
            ->assertOk();
    }

    public function test_visible_project_cannot_bind_a_change_request_from_another_project(): void
    {
        $member = $this->projectMemberWithPermissions(['view_projects']);
        $foreignChangeRequest = $this->changeRequestFor(Project::factory()->create());

        $this->actingAs($member['user'], 'sanctum')
            ->getJson(
                "/api/v1/projects/{$member['project']->id}/change-requests/{$foreignChangeRequest->id}"
            )
            ->assertNotFound();
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

    /**
     * @param  array<int, string>  $permissions
     * @return array{user: User, project: Project}
     */
    private function projectMemberWithPermissions(array $permissions): array
    {
        $user = $this->userWithPermissions($permissions);
        $project = Project::factory()->create();
        ProjectMember::create([
            'project_id' => $project->id,
            'user_id' => $user->id,
            'role' => ProjectRole::MANAGER->value,
        ]);

        return ['user' => $user, 'project' => $project];
    }

    private function changeRequestFor(Project $project): ChangeRequest
    {
        return ChangeRequest::create([
            'project_id' => $project->id,
            'title' => 'Scoped commercial request',
            'description' => 'Commercial details must remain project scoped.',
            'price' => 1250,
            'status' => 'draft',
        ]);
    }

    /**
     * @return array<string, int|string>
     */
    private function changeRequestPayload(): array
    {
        return [
            'title' => 'Additional delivery phase',
            'description' => 'Add one approved delivery phase.',
            'price' => 900,
        ];
    }
}
