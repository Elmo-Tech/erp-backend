<?php

namespace Tests\Feature\Project;

use App\Enums\ProjectRole;
use App\Modules\Project\Models\Project;
use App\Modules\Project\Models\ProjectMember;
use App\Modules\Project\Models\ProjectTask;
use App\Modules\Project\Models\ProjectTaskDeliverable;
use App\Modules\Settings\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
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

    public function test_project_task_store_allows_visible_project_member_with_create_permission(): void
    {
        $project = Project::factory()->create();
        $developer = $this->userWithPermissions(['create_tasks', 'view_projects']);

        ProjectMember::create([
            'project_id' => $project->id,
            'user_id' => $developer->id,
            'role' => ProjectRole::DEVELOPER->value,
        ]);

        $this->actingAs($developer, 'sanctum')
            ->postJson("/api/v1/projects/{$project->id}/tasks", [
                'title' => 'Developer-created task',
            ])
            ->assertCreated();
    }

    public function test_project_task_update_allows_visible_project_member_with_edit_permission(): void
    {
        $project = Project::factory()->create();
        $task = ProjectTask::create([
            'project_id' => $project->id,
            'title' => 'Original task',
            'status' => 'to_do',
        ]);
        $developer = $this->userWithPermissions(['edit_tasks', 'view_projects']);

        ProjectMember::create([
            'project_id' => $project->id,
            'user_id' => $developer->id,
            'role' => ProjectRole::DEVELOPER->value,
        ]);

        $this->actingAs($developer, 'sanctum')
            ->patchJson("/api/v1/projects/{$project->id}/tasks/{$task->id}", [
                'title' => 'Updated task',
            ])
            ->assertOk();

        $this->assertDatabaseHas('project_tasks', [
            'id' => $task->id,
            'title' => 'Updated task',
        ]);
    }

    public function test_task_deliverable_store_allows_granular_create_permission(): void
    {
        [$project, $task, $developer] = $this->taskForMemberWithPermissions([
            'view_projects',
            'create_project_task_deliverables',
        ]);

        $this->actingAs($developer, 'sanctum')
            ->postJson("/api/v1/projects/{$project->id}/tasks/{$task->id}/deliverables", [
                'title' => 'Release checklist',
            ])
            ->assertCreated();

        $this->assertDatabaseHas('project_task_deliverables', [
            'task_id' => $task->id,
            'title' => 'Release checklist',
        ]);
    }

    public function test_seeded_developer_role_can_create_a_task_deliverable(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $project = Project::factory()->create();
        $task = ProjectTask::create([
            'project_id' => $project->id,
            'title' => 'Developer task',
            'status' => 'to_do',
        ]);
        $developer = User::factory()->create();
        $developer->assignRole('dev');

        ProjectMember::create([
            'project_id' => $project->id,
            'user_id' => $developer->id,
            'role' => ProjectRole::DEVELOPER->value,
        ]);

        $this->actingAs($developer, 'sanctum')
            ->postJson("/api/v1/projects/{$project->id}/tasks/{$task->id}/deliverables", [
                'title' => 'Developer release notes',
            ])
            ->assertCreated();

        $this->assertDatabaseHas('project_task_deliverables', [
            'task_id' => $task->id,
            'title' => 'Developer release notes',
        ]);
    }

    public function test_task_deliverable_store_requires_access_to_parent_project(): void
    {
        $project = Project::factory()->create();
        $task = ProjectTask::create([
            'project_id' => $project->id,
            'title' => 'Protected task',
            'status' => 'to_do',
        ]);
        $outsider = $this->userWithPermissions(['create_project_task_deliverables']);

        $this->actingAs($outsider, 'sanctum')
            ->postJson("/api/v1/projects/{$project->id}/tasks/{$task->id}/deliverables", [
                'title' => 'Unauthorized deliverable',
            ])
            ->assertForbidden();
    }

    public function test_task_deliverable_update_allows_granular_edit_permission(): void
    {
        [$project, $task, $developer] = $this->taskForMemberWithPermissions([
            'view_projects',
            'edit_project_task_deliverables',
        ]);
        $deliverable = ProjectTaskDeliverable::create([
            'task_id' => $task->id,
            'title' => 'Old title',
            'is_completed' => false,
        ]);

        $this->actingAs($developer, 'sanctum')
            ->patchJson("/api/v1/projects/{$project->id}/tasks/{$task->id}/deliverables/{$deliverable->id}", [
                'title' => 'Updated title',
            ])
            ->assertOk();

        $this->assertDatabaseHas('project_task_deliverables', [
            'id' => $deliverable->id,
            'title' => 'Updated title',
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

    /**
     * @param  array<int, string>  $permissions
     * @return array{Project, ProjectTask, User}
     */
    private function taskForMemberWithPermissions(array $permissions): array
    {
        $project = Project::factory()->create();
        $task = ProjectTask::create([
            'project_id' => $project->id,
            'title' => 'Task with deliverables',
            'status' => 'to_do',
        ]);
        $member = $this->userWithPermissions($permissions);

        ProjectMember::create([
            'project_id' => $project->id,
            'user_id' => $member->id,
            'role' => ProjectRole::DEVELOPER->value,
        ]);

        return [$project, $task, $member];
    }
}
