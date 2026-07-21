<?php

namespace Tests\Feature\Project;

use App\Enums\ProjectRole;
use App\Modules\Project\Models\Project;
use App\Modules\Project\Models\ProjectMember;
use App\Modules\Project\Models\ProjectTask;
use App\Modules\Project\Models\ProjectTaskDeliverable;
use App\Modules\Settings\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProjectTaskActionAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_edit_tasks_only_allows_metadata_changes(): void
    {
        [$project, $task, $member] = $this->taskForMember(['view_projects', 'edit_tasks']);

        $this->actingAs($member, 'sanctum')
            ->patchJson($this->taskUrl($project, $task), ['title' => 'Updated title'])
            ->assertOk();

        $this->actingAs($member, 'sanctum')
            ->patchJson($this->taskUrl($project, $task), ['status' => 'in_progress'])
            ->assertForbidden();

        $this->assertDatabaseHas('project_tasks', [
            'id' => $task->id,
            'title' => 'Updated title',
            'status' => 'to_do',
        ]);
    }

    public function test_change_task_status_allows_non_terminal_transition_for_project_member(): void
    {
        [$project, $task, $member] = $this->taskForMember(['view_projects', 'change_task_status']);

        $this->actingAs($member, 'sanctum')
            ->patchJson($this->taskUrl($project, $task), ['status' => 'qa'])
            ->assertOk()
            ->assertJsonPath('data.status', 'qa');
    }

    public function test_work_on_tasks_only_allows_assignee_to_progress_through_in_review(): void
    {
        [$project, $task, $assignee] = $this->taskForMember(['view_projects', 'work_on_tasks'], true);

        $this->actingAs($assignee, 'sanctum')
            ->patchJson($this->taskUrl($project, $task), ['status' => 'in_review'])
            ->assertOk();

        $this->actingAs($assignee, 'sanctum')
            ->patchJson($this->taskUrl($project, $task), ['status' => 'qa'])
            ->assertForbidden();

        $this->assertSame('in_review', $task->fresh()->status->value);
    }

    public function test_complete_tasks_is_required_when_update_targets_done(): void
    {
        [$project, $task, $editor] = $this->taskForMember(['view_projects', 'edit_tasks']);

        $this->actingAs($editor, 'sanctum')
            ->patchJson($this->taskUrl($project, $task), ['status' => 'done'])
            ->assertForbidden();

        $editor->givePermissionTo(Permission::findOrCreate('complete_tasks', 'web'));

        $this->actingAs($editor, 'sanctum')
            ->patchJson($this->taskUrl($project, $task), ['status' => 'done'])
            ->assertOk()
            ->assertJsonPath('data.status', 'done');
    }

    public function test_reopen_tasks_is_required_when_update_leaves_done(): void
    {
        [$project, $task, $member] = $this->taskForMember(['view_projects', 'change_task_status'], false, 'done');

        $this->actingAs($member, 'sanctum')
            ->patchJson($this->taskUrl($project, $task), ['status' => 'in_progress'])
            ->assertForbidden();

        $member->givePermissionTo(Permission::findOrCreate('reopen_tasks', 'web'));

        $this->actingAs($member, 'sanctum')
            ->patchJson($this->taskUrl($project, $task), ['status' => 'in_progress'])
            ->assertOk()
            ->assertJsonPath('data.status', 'in_progress');
    }

    public function test_task_creation_cannot_set_done_without_complete_tasks(): void
    {
        [$project, $creator] = $this->projectMember(['view_projects', 'create_tasks']);

        $this->actingAs($creator, 'sanctum')
            ->postJson("/api/v1/projects/{$project->id}/tasks", [
                'title' => 'Bypass attempt',
                'status' => 'done',
            ])
            ->assertForbidden();

        $creator->givePermissionTo(Permission::findOrCreate('complete_tasks', 'web'));

        $this->actingAs($creator, 'sanctum')
            ->postJson("/api/v1/projects/{$project->id}/tasks", [
                'title' => 'Authorized completed task',
                'status' => 'done',
            ])
            ->assertCreated();
    }

    public function test_toggle_deliverable_requires_exact_permission_or_task_execution_scope(): void
    {
        [$project, $task, $member] = $this->taskForMember(['view_projects']);
        $deliverable = $this->deliverableFor($task);

        $this->actingAs($member, 'sanctum')
            ->patchJson($this->toggleUrl($project, $task, $deliverable))
            ->assertForbidden();

        $member->givePermissionTo(Permission::findOrCreate('toggle_project_task_deliverables', 'web'));

        $this->actingAs($member, 'sanctum')
            ->patchJson($this->toggleUrl($project, $task, $deliverable))
            ->assertOk()
            ->assertJsonPath('data.isCompleted', true);
    }

    public function test_toggle_deliverable_rejects_outsider_even_with_exact_permission(): void
    {
        $project = Project::factory()->create();
        $task = ProjectTask::create([
            'project_id' => $project->id,
            'title' => 'Scoped task',
            'status' => 'to_do',
        ]);
        $deliverable = $this->deliverableFor($task);
        $outsider = $this->userWithPermissions(['toggle_project_task_deliverables']);

        $this->actingAs($outsider, 'sanctum')
            ->patchJson($this->toggleUrl($project, $task, $deliverable))
            ->assertForbidden();

        $this->assertFalse($deliverable->fresh()->is_completed);
    }

    public function test_manage_tasks_is_an_umbrella_only_inside_project_scope(): void
    {
        [$project, $task, $manager] = $this->taskForMember(['view_projects', 'manage_tasks']);
        $deliverable = $this->deliverableFor($task);

        $this->actingAs($manager, 'sanctum')
            ->patchJson($this->taskUrl($project, $task), ['status' => 'done'])
            ->assertOk();

        $this->actingAs($manager, 'sanctum')
            ->patchJson($this->toggleUrl($project, $task, $deliverable))
            ->assertOk();

        $otherProject = Project::factory()->create();
        $otherTask = ProjectTask::create([
            'project_id' => $otherProject->id,
            'title' => 'Outside scope',
            'status' => 'to_do',
        ]);

        $this->actingAs($manager, 'sanctum')
            ->patchJson($this->taskUrl($otherProject, $otherTask), ['status' => 'done'])
            ->assertForbidden();

        $this->assertSame('to_do', $otherTask->fresh()->status->value);
    }

    public function test_complete_endpoint_requires_complete_tasks(): void
    {
        [$project, $task, $member] = $this->taskForMember(['view_projects', 'edit_tasks']);

        $this->actingAs($member, 'sanctum')
            ->postJson("{$this->taskUrl($project, $task)}/complete")
            ->assertForbidden();

        $member->givePermissionTo(Permission::findOrCreate('complete_tasks', 'web'));

        $this->actingAs($member, 'sanctum')
            ->postJson("{$this->taskUrl($project, $task)}/complete")
            ->assertOk()
            ->assertJsonPath('data.status', 'done')
            ->assertJsonPath('data.allowedActions.complete', false);

        $this->actingAs($member, 'sanctum')
            ->postJson("{$this->taskUrl($project, $task)}/complete")
            ->assertOk();
    }

    public function test_resource_allowed_actions_comes_from_the_scoped_policy_contract(): void
    {
        [$project, $task, $member] = $this->taskForMember([
            'view_projects',
            'edit_tasks',
            'complete_tasks',
            'create_project_task_deliverables',
            'toggle_project_task_deliverables',
        ]);

        $this->actingAs($member, 'sanctum')
            ->patchJson($this->taskUrl($project, $task), ['title' => 'Resource contract'])
            ->assertOk()
            ->assertJsonPath('data.allowedActions', [
                'edit' => true,
                'delete' => false,
                'changeStatus' => false,
                'complete' => true,
                'reopen' => false,
                'createDeliverable' => true,
                'editDeliverable' => false,
                'deleteDeliverable' => false,
                'toggleDeliverable' => true,
            ]);
    }

    public function test_new_permissions_are_grouped_for_the_role_matrix(): void
    {
        $admin = $this->userWithPermissions(['manage_roles']);

        $permissions = collect(
            $this->actingAs($admin, 'sanctum')
                ->getJson('/api/v1/admin/permissions')
                ->assertOk()
                ->json('data')
        )->keyBy('name');

        $this->assertSame('Task Management', $permissions['change_task_status']['group']);
        $this->assertSame('Task Management', $permissions['complete_tasks']['group']);
        $this->assertSame('Task Management', $permissions['reopen_tasks']['group']);
        $this->assertSame('Task Deliverables', $permissions['toggle_project_task_deliverables']['group']);
    }

    public function test_task_action_permission_migration_is_reversible(): void
    {
        $founder = Role::firstOrCreate(['name' => 'founder', 'guard_name' => 'web']);
        $migration = require database_path('migrations/2026_07_20_000001_add_task_action_permissions.php');

        $migration->down();

        $this->assertSame(0, Permission::whereIn('name', [
            'change_task_status',
            'complete_tasks',
            'reopen_tasks',
            'toggle_project_task_deliverables',
        ])->count());

        $migration->up();

        $founder->refresh();
        $this->assertTrue($founder->hasAllPermissions([
            'change_task_status',
            'complete_tasks',
            'reopen_tasks',
            'toggle_project_task_deliverables',
        ]));
    }

    /**
     * @param  array<int, string>  $permissions
     * @return array{Project, ProjectTask, User}
     */
    private function taskForMember(array $permissions, bool $assigned = false, string $status = 'to_do'): array
    {
        [$project, $member] = $this->projectMember($permissions);
        $task = ProjectTask::create([
            'project_id' => $project->id,
            'title' => 'Authorized task',
            'status' => $status,
            'assignee_id' => $assigned ? $member->id : null,
        ]);

        return [$project, $task, $member];
    }

    /**
     * @param  array<int, string>  $permissions
     * @return array{Project, User}
     */
    private function projectMember(array $permissions): array
    {
        $project = Project::factory()->create();
        $member = $this->userWithPermissions($permissions);

        ProjectMember::create([
            'project_id' => $project->id,
            'user_id' => $member->id,
            'role' => ProjectRole::DEVELOPER->value,
        ]);

        return [$project, $member];
    }

    /** @param array<int, string> $permissions */
    private function userWithPermissions(array $permissions): User
    {
        $user = User::factory()->create();

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $user->givePermissionTo($permissions);

        return $user;
    }

    private function deliverableFor(ProjectTask $task): ProjectTaskDeliverable
    {
        return ProjectTaskDeliverable::create([
            'task_id' => $task->id,
            'title' => 'Checklist',
            'is_completed' => false,
        ]);
    }

    private function taskUrl(Project $project, ProjectTask $task): string
    {
        return "/api/v1/projects/{$project->id}/tasks/{$task->id}";
    }

    private function toggleUrl(Project $project, ProjectTask $task, ProjectTaskDeliverable $deliverable): string
    {
        return "/api/v1/projects/{$project->id}/tasks/{$task->id}/deliverables/{$deliverable->id}/toggle";
    }
}
