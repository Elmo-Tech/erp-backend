<?php

namespace Tests\Feature\Project;

use App\Enums\ProjectRole;
use App\Modules\Project\Models\Project;
use App\Modules\Project\Models\ProjectMember;
use App\Modules\Project\Models\ProjectTask;
use App\Modules\Project\Models\ProjectTimeEntry;
use App\Modules\Settings\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ProjectTimeEntryActiveControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_starting_timer_moves_todo_task_to_in_progress(): void
    {
        $user = $this->userWithPermissions(['view_projects', 'view_tasks', 'work_on_tasks', 'start_timer']);
        $project = Project::factory()->create();
        ProjectMember::create([
            'project_id' => $project->id,
            'user_id' => $user->id,
            'role' => ProjectRole::DEVELOPER->value,
        ]);
        $task = ProjectTask::create([
            'project_id' => $project->id,
            'title' => 'Implement timer status sync',
            'status' => 'to_do',
            'assignee_id' => $user->id,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/projects/{$project->id}/tasks/{$task->id}/time-entries/toggle");

        $response->assertOk()
            ->assertJsonPath('message', 'Timer started');

        $this->assertDatabaseHas('project_tasks', [
            'id' => $task->id,
            'status' => 'in_progress',
        ]);
    }

    public function test_user_can_get_their_active_project_time_entry(): void
    {
        $user = $this->userWithPermissions(['view_projects']);
        $project = Project::factory()->create(['title' => 'Mobile App']);
        $task = ProjectTask::create([
            'project_id' => $project->id,
            'title' => 'Build timer',
            'status' => 'in_progress',
            'assignee_id' => $user->id,
        ]);
        $entry = ProjectTimeEntry::create([
            'task_id' => $task->id,
            'user_id' => $user->id,
            'start_time' => now()->subMinutes(25),
            'is_manual' => false,
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/projects/time-entries/active')
            ->assertOk()
            ->assertJsonPath('data.entryId', $entry->id)
            ->assertJsonPath('data.taskId', $task->id)
            ->assertJsonPath('data.projectId', $project->id)
            ->assertJsonPath('data.task.title', 'Build timer')
            ->assertJsonPath('data.project.title', 'Mobile App');
    }

    public function test_active_project_time_entry_returns_null_when_idle(): void
    {
        $user = $this->userWithPermissions(['view_projects']);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/projects/time-entries/active')
            ->assertOk()
            ->assertJsonPath('data', null);
    }

    public function test_task_time_entries_index_returns_ui_contract_keys(): void
    {
        $user = $this->userWithPermissions(['view_projects', 'manage_all_projects']);
        $project = Project::factory()->create();
        $task = ProjectTask::create([
            'project_id' => $project->id,
            'title' => 'Build timer',
            'status' => 'in_progress',
            'assignee_id' => $user->id,
        ]);
        $entry = ProjectTimeEntry::create([
            'task_id' => $task->id,
            'user_id' => $user->id,
            'start_time' => now()->subMinutes(90),
            'end_time' => now()->subMinutes(15),
            'duration_minutes' => 75,
            'is_manual' => true,
            'note' => 'Reviewed delivery risks',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/projects/{$project->id}/tasks/{$task->id}/time-entries");

        $response->assertOk()
            ->assertJsonPath('data.timeEntries.0.entryId', $entry->id)
            ->assertJsonPath('data.timeEntries.0.taskId', $task->id)
            ->assertJsonPath('data.timeEntries.0.userId', $user->id)
            ->assertJsonPath('data.timeEntries.0.durationMinutes', 75)
            ->assertJsonPath('data.timeEntries.0.isManual', true)
            ->assertJsonPath('data.timeEntries.0.isRunning', false)
            ->assertJsonPath('data.timeEntries.0.note', 'Reviewed delivery risks')
            ->assertJsonPath('data.timeEntries.0.user.name', $user->name);

        $this->assertSame([
            'entryId',
            'taskId',
            'userId',
            'startTime',
            'endTime',
            'durationMinutes',
            'isManual',
            'isRunning',
            'note',
            'user',
            'createdAt',
            'updatedAt',
        ], array_keys($response->json('data.timeEntries.0')));
    }

    public function test_team_timer_start_requires_manage_team_timers_permission(): void
    {
        $manager = $this->userWithPermissions(['view_projects', 'manage_all_projects']);
        $target = User::factory()->create();
        $project = Project::factory()->create();
        $task = ProjectTask::create([
            'project_id' => $project->id,
            'title' => 'Assigned work',
            'status' => 'in_progress',
            'assignee_id' => $target->id,
        ]);

        $this->actingAs($manager, 'sanctum')
            ->postJson("/api/v1/projects/{$project->id}/tasks/{$task->id}/time-entries/start-for-user", [
                'userId' => $target->id,
            ])
            ->assertForbidden();
    }

    public function test_team_timer_start_for_user_creates_active_entry(): void
    {
        $manager = $this->userWithPermissions(['view_projects', 'manage_team_timers']);
        $target = User::factory()->create();
        $project = Project::factory()->create();
        ProjectMember::create([
            'project_id' => $project->id,
            'user_id' => $manager->id,
            'role' => ProjectRole::MANAGER->value,
        ]);
        ProjectMember::create([
            'project_id' => $project->id,
            'user_id' => $target->id,
            'role' => ProjectRole::DEVELOPER->value,
        ]);
        $task = ProjectTask::create([
            'project_id' => $project->id,
            'title' => 'Assigned work',
            'status' => 'in_progress',
            'assignee_id' => $target->id,
        ]);

        $this->actingAs($manager, 'sanctum')
            ->postJson("/api/v1/projects/{$project->id}/tasks/{$task->id}/time-entries/start-for-user", [
                'userId' => $target->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.userId', $target->id)
            ->assertJsonPath('data.taskId', $task->id)
            ->assertJsonPath('data.isRunning', true);
    }

    public function test_time_entry_write_routes_enforce_start_log_and_stop_capabilities(): void
    {
        $user = $this->userWithPermissions(['view_projects', 'view_tasks', 'work_on_tasks', 'start_timer']);
        [$project, $task] = $this->taskForUser($user);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/projects/{$project->id}/tasks/{$task->id}/time-entries", [
                'startTime' => now()->subHour()->toIso8601String(),
                'endTime' => now()->toIso8601String(),
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('project_time_entries', 0);

        Permission::findOrCreate('log_time', 'web');
        $user->syncPermissions(['view_projects', 'view_tasks', 'work_on_tasks', 'log_time']);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/projects/{$project->id}/tasks/{$task->id}/time-entries/toggle")
            ->assertForbidden();

        $this->assertDatabaseCount('project_time_entries', 0);

        Permission::findOrCreate('stop_timer', 'web');
        $user->syncPermissions(['view_projects', 'view_tasks', 'work_on_tasks', 'start_timer']);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/projects/{$project->id}/tasks/{$task->id}/time-entries/toggle")
            ->assertOk()
            ->assertJsonPath('message', 'Timer started');

        $entry = ProjectTimeEntry::query()->where('user_id', $user->id)->firstOrFail();

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/projects/{$project->id}/tasks/{$task->id}/time-entries/toggle")
            ->assertForbidden();

        $this->assertNull($entry->fresh()->end_time);

        $user->givePermissionTo(Permission::findOrCreate('stop_timer', 'web'));

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/projects/{$project->id}/tasks/{$task->id}/time-entries/toggle")
            ->assertOk()
            ->assertJsonPath('message', 'Timer stopped');

        $this->assertNotNull($entry->fresh()->end_time);
    }

    public function test_team_timer_rejects_nonmember_target_without_side_effects(): void
    {
        $manager = $this->userWithPermissions(['view_projects', 'manage_team_timers']);
        $target = User::factory()->create();
        $managedProject = Project::factory()->create();
        $foreignProject = Project::factory()->create();
        ProjectMember::create([
            'project_id' => $managedProject->id,
            'user_id' => $manager->id,
            'role' => ProjectRole::MANAGER->value,
        ]);
        ProjectMember::create([
            'project_id' => $foreignProject->id,
            'user_id' => $target->id,
            'role' => ProjectRole::DEVELOPER->value,
        ]);
        $managedTask = ProjectTask::create([
            'project_id' => $managedProject->id,
            'title' => 'Managed task',
            'status' => 'to_do',
            'assignee_id' => $target->id,
        ]);
        $foreignTask = ProjectTask::create([
            'project_id' => $foreignProject->id,
            'title' => 'Target active task',
            'status' => 'in_progress',
            'assignee_id' => $target->id,
        ]);
        $foreignEntry = ProjectTimeEntry::create([
            'task_id' => $foreignTask->id,
            'user_id' => $target->id,
            'start_time' => now()->subMinutes(20),
            'is_manual' => false,
        ]);

        $this->actingAs($manager, 'sanctum')
            ->postJson("/api/v1/projects/{$managedProject->id}/tasks/{$managedTask->id}/time-entries/start-for-user", [
                'userId' => $target->id,
            ])
            ->assertForbidden();

        $this->assertNull($foreignEntry->fresh()->end_time);
        $this->assertDatabaseMissing('project_time_entries', [
            'task_id' => $managedTask->id,
            'user_id' => $target->id,
        ]);
        $this->assertDatabaseMissing('attendances', [
            'user_id' => $target->id,
            'date' => now()->toDateString(),
        ]);
        $this->assertDatabaseHas('project_tasks', [
            'id' => $managedTask->id,
            'status' => 'to_do',
        ]);
    }

    public function test_team_timer_requires_target_assignment_and_entry_project_scope(): void
    {
        $manager = $this->userWithPermissions(['view_projects', 'manage_team_timers']);
        $target = User::factory()->create();
        $otherUser = User::factory()->create();
        $project = Project::factory()->create();
        ProjectMember::create([
            'project_id' => $project->id,
            'user_id' => $manager->id,
            'role' => ProjectRole::MANAGER->value,
        ]);
        ProjectMember::create([
            'project_id' => $project->id,
            'user_id' => $target->id,
            'role' => ProjectRole::DEVELOPER->value,
        ]);
        $task = ProjectTask::create([
            'project_id' => $project->id,
            'title' => 'Other user assignment',
            'status' => 'to_do',
            'assignee_id' => $otherUser->id,
        ]);

        $this->actingAs($manager, 'sanctum')
            ->postJson("/api/v1/projects/{$project->id}/tasks/{$task->id}/time-entries/start-for-user", [
                'userId' => $target->id,
            ])
            ->assertForbidden();

        $foreignProject = Project::factory()->create();
        $foreignTask = ProjectTask::create([
            'project_id' => $foreignProject->id,
            'title' => 'Foreign timer task',
            'status' => 'in_progress',
            'assignee_id' => $target->id,
        ]);
        $foreignEntry = ProjectTimeEntry::create([
            'task_id' => $foreignTask->id,
            'user_id' => $target->id,
            'start_time' => now()->subMinutes(15),
            'is_manual' => false,
        ]);

        $this->actingAs($manager, 'sanctum')
            ->patchJson("/api/v1/projects/time-entries/{$foreignEntry->id}/stop")
            ->assertForbidden();

        $this->assertNull($foreignEntry->fresh()->end_time);
    }

    /**
     * @return array{0: Project, 1: ProjectTask}
     */
    private function taskForUser(User $user): array
    {
        $project = Project::factory()->create();
        ProjectMember::create([
            'project_id' => $project->id,
            'user_id' => $user->id,
            'role' => ProjectRole::DEVELOPER->value,
        ]);
        $task = ProjectTask::create([
            'project_id' => $project->id,
            'title' => 'Scoped timer task',
            'status' => 'to_do',
            'assignee_id' => $user->id,
        ]);

        return [$project, $task];
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
