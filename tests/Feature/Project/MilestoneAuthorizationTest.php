<?php

namespace Tests\Feature\Project;

use App\Enums\MilestoneStatus;
use App\Enums\ProjectRole;
use App\Modules\Project\Models\Milestone;
use App\Modules\Project\Models\Project;
use App\Modules\Project\Models\ProjectMember;
use App\Modules\Settings\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class MilestoneAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_nonmember_cannot_list_or_read_foreign_project_milestones(): void
    {
        $user = $this->userWithPermissions(['view_projects']);
        $project = Project::factory()->create();
        $milestone = $this->milestoneFor($project);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/projects/{$project->id}/milestones")
            ->assertForbidden();

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/projects/{$project->id}/milestones/{$milestone->id}")
            ->assertForbidden();
    }

    public function test_custom_manager_cannot_create_or_update_a_foreign_project_milestone(): void
    {
        $user = $this->userWithPermissions(['view_projects', 'manage_projects', 'manage_tasks']);
        $project = Project::factory()->create();
        $milestone = $this->milestoneFor($project);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/projects/{$project->id}/milestones", ['title' => 'Foreign milestone'])
            ->assertForbidden();

        $this->actingAs($user, 'sanctum')
            ->patchJson("/api/v1/projects/{$project->id}/milestones/{$milestone->id}", ['title' => 'Hijacked'])
            ->assertForbidden();

        $this->assertSame('Scoped milestone', $milestone->fresh()->title);
        $this->assertDatabaseCount('milestones', 1);
    }

    public function test_delete_capability_does_not_bypass_parent_project_scope(): void
    {
        $user = $this->userWithPermissions(['view_projects', 'manage_projects', 'delete_milestones']);
        $project = Project::factory()->create();
        $milestone = $this->milestoneFor($project);

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/v1/projects/{$project->id}/milestones/{$milestone->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('milestones', ['id' => $milestone->id, 'deleted_at' => null]);
    }

    public function test_authorized_project_member_preserves_milestone_workflow(): void
    {
        $user = $this->userWithPermissions([
            'view_projects',
            'manage_projects',
            'delete_milestones',
        ]);
        $project = Project::factory()->create();
        ProjectMember::create([
            'project_id' => $project->id,
            'user_id' => $user->id,
            'role' => ProjectRole::MANAGER->value,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/projects/{$project->id}/milestones", ['title' => 'Authorized milestone'])
            ->assertCreated();

        $milestoneId = $response->json('data.milestoneId');

        $this->actingAs($user, 'sanctum')
            ->patchJson("/api/v1/projects/{$project->id}/milestones/{$milestoneId}", ['title' => 'Updated milestone'])
            ->assertOk();

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/projects/{$project->id}/milestones/{$milestoneId}")
            ->assertOk()
            ->assertJsonPath('data.title', 'Updated milestone');

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/v1/projects/{$project->id}/milestones/{$milestoneId}")
            ->assertOk();
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

    private function milestoneFor(Project $project): Milestone
    {
        return Milestone::create([
            'project_id' => $project->id,
            'title' => 'Scoped milestone',
            'status' => MilestoneStatus::ACTIVE,
            'progress_percentage' => 0,
        ]);
    }
}
