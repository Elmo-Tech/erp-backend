<?php

namespace Tests\Feature\Project;

use App\Enums\ProjectRole;
use App\Modules\Project\Models\Project;
use App\Modules\Project\Models\ProjectMember;
use App\Modules\Project\Models\ProjectTask;
use App\Modules\Settings\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ProjectSubResourceAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_member_index_requires_access_to_parent_project(): void
    {
        $project = Project::factory()->create();
        $outsider = $this->userWithPermissions(['view_projects']);

        $this->actingAs($outsider, 'sanctum')
            ->getJson("/api/v1/projects/{$project->id}/members")
            ->assertForbidden();
    }

    public function test_project_task_store_requires_access_to_parent_project(): void
    {
        $project = Project::factory()->create();
        $outsider = $this->userWithPermissions(['create_tasks']);

        $this->actingAs($outsider, 'sanctum')
            ->postJson("/api/v1/projects/{$project->id}/tasks", [
                'title' => 'Unauthorized task',
            ])
            ->assertForbidden();
    }

    public function test_project_task_store_allows_authorized_project_manager(): void
    {
        $project = Project::factory()->create();
        $manager = $this->userWithPermissions(['create_tasks', 'view_projects']);

        ProjectMember::create([
            'project_id' => $project->id,
            'user_id' => $manager->id,
            'role' => ProjectRole::MANAGER->value,
        ]);

        $this->actingAs($manager, 'sanctum')
            ->postJson("/api/v1/projects/{$project->id}/tasks", [
                'title' => 'Authorized task',
            ])
            ->assertCreated();

        $this->assertDatabaseHas('project_tasks', [
            'project_id' => $project->id,
            'title' => 'Authorized task',
        ]);
    }

    public function test_project_task_update_requires_access_to_parent_project_before_task_policy(): void
    {
        $project = Project::factory()->create();
        $task = ProjectTask::create([
            'project_id' => $project->id,
            'title' => 'Existing task',
            'status' => 'to_do',
        ]);
        $outsider = $this->userWithPermissions(['manage_tasks']);

        $this->actingAs($outsider, 'sanctum')
            ->patchJson("/api/v1/projects/{$project->id}/tasks/{$task->id}", [
                'title' => 'Should not update',
            ])
            ->assertForbidden();
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
