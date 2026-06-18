<?php

namespace Tests\Feature\PersonalTasks;

use App\Modules\PersonalTasks\Models\PersonalTask;
use App\Modules\PersonalTasks\Models\PersonalTimeEntry;
use App\Modules\Project\Models\Project;
use App\Modules\Project\Models\ProjectTask;
use App\Modules\Project\Models\ProjectTimeEntry;
use App\Modules\Settings\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PersonalTaskControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_and_list_only_their_personal_tasks(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/personal-tasks', [
                'title' => 'Study Laravel',
                'description' => 'Read docs',
                'dueDate' => '2026-06-03',
                'estimatedMinutes' => 45,
            ])
            ->assertCreated()
            ->assertJsonPath('data.title', 'Study Laravel')
            ->assertJsonPath('data.status', 'todo')
            ->assertJsonPath('data.estimatedMinutes', 45);

        $this->actingAs($otherUser, 'sanctum')
            ->postJson('/api/v1/personal-tasks', [
                'title' => 'Eat lunch',
            ])
            ->assertCreated();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/personal-tasks')
            ->assertOk()
            ->assertJsonCount(1, 'data.tasks')
            ->assertJsonPath('data.tasks.0.title', 'Study Laravel');
    }

    public function test_user_can_create_personal_task_with_details_without_assignment_or_milestone(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/personal-tasks', [
                'title' => 'Prepare weekly notes',
                'description' => 'Summarize blockers and follow-ups',
                'dueDate' => '2026-06-20',
                'priority' => 'high',
                'assigneeId' => User::factory()->create()->id,
                'milestoneId' => 99,
            ])
            ->assertCreated()
            ->assertJsonPath('data.title', 'Prepare weekly notes')
            ->assertJsonPath('data.description', 'Summarize blockers and follow-ups')
            ->assertJsonPath('data.dueDate', '2026-06-20')
            ->assertJsonPath('data.priority', 'high')
            ->assertJsonMissingPath('data.assigneeId')
            ->assertJsonMissingPath('data.milestoneId');

        $this->assertDatabaseHas('personal_tasks', [
            'user_id' => $user->id,
            'title' => 'Prepare weekly notes',
            'description' => 'Summarize blockers and follow-ups',
            'due_date' => '2026-06-20 00:00:00',
            'priority' => 'high',
        ]);
    }

    public function test_user_cannot_update_another_users_personal_task(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $response = $this->actingAs($owner, 'sanctum')
            ->postJson('/api/v1/personal-tasks', ['title' => 'Pray'])
            ->assertCreated();

        $taskId = $response->json('data.taskId');

        $this->actingAs($otherUser, 'sanctum')
            ->patchJson("/api/v1/personal-tasks/{$taskId}", ['title' => 'Changed'])
            ->assertNotFound();
    }

    public function test_admin_can_list_and_edit_employee_personal_tasks_and_time_entries(): void
    {
        $admin = $this->userWithPermissions(['manage_employees', 'edit_time_entries']);
        $employee = User::factory()->create();
        $otherEmployee = User::factory()->create();

        $task = PersonalTask::create([
            'user_id' => $employee->id,
            'title' => 'Original task',
            'status' => 'todo',
            'priority' => 'medium',
        ]);
        PersonalTask::create([
            'user_id' => $otherEmployee->id,
            'title' => 'Other task',
            'status' => 'todo',
            'priority' => 'medium',
        ]);
        $entry = PersonalTimeEntry::create([
            'personal_task_id' => $task->id,
            'user_id' => $employee->id,
            'start_time' => '2026-06-18 09:00:00',
            'end_time' => '2026-06-18 10:00:00',
            'duration_minutes' => 60,
            'is_manual' => false,
        ]);

        $this->actingAs($admin, 'sanctum')
            ->getJson("/api/v1/personal-tasks?user_id={$employee->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data.tasks')
            ->assertJsonPath('data.tasks.0.title', 'Original task');

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/v1/personal-tasks/{$task->id}", [
                'title' => 'Admin updated task',
                'status' => 'in_progress',
            ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Admin updated task')
            ->assertJsonPath('data.status', 'in_progress');

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/v1/personal-tasks/{$task->id}/time-entries/{$entry->id}", [
                'startTime' => '2026-06-18 09:30:00',
                'endTime' => '2026-06-18 11:00:00',
                'note' => 'Admin correction',
            ])
            ->assertOk()
            ->assertJsonPath('data.durationMinutes', 90)
            ->assertJsonPath('data.note', 'Admin correction');
    }

    public function test_personal_timer_creates_and_stops_time_entry_without_project_time(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create();
        $projectTask = ProjectTask::create([
            'project_id' => $project->id,
            'title' => 'Build feature',
            'status' => 'in_progress',
            'assignee_id' => $user->id,
        ]);

        $taskResponse = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/personal-tasks', ['title' => 'Eat'])
            ->assertCreated();

        $personalTaskId = $taskResponse->json('data.taskId');

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/personal-tasks/{$personalTaskId}/time-entries/toggle")
            ->assertCreated()
            ->assertJsonPath('data.personalTaskId', $personalTaskId)
            ->assertJsonPath('data.isRunning', true);

        $this->travel(10)->minutes();

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/personal-tasks/{$personalTaskId}/time-entries/toggle")
            ->assertOk()
            ->assertJsonPath('data.isRunning', false);

        $this->assertDatabaseMissing('project_time_entries', [
            'task_id' => $projectTask->id,
            'user_id' => $user->id,
        ]);

        $this->assertSame(0, $projectTask->fresh()->totalLoggedMinutes());
    }

    public function test_starting_personal_timer_stops_active_project_timer_for_same_user(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create();
        $projectTask = ProjectTask::create([
            'project_id' => $project->id,
            'title' => 'Build feature',
            'status' => 'in_progress',
            'assignee_id' => $user->id,
        ]);
        $activeProjectEntry = ProjectTimeEntry::create([
            'task_id' => $projectTask->id,
            'user_id' => $user->id,
            'start_time' => now()->subMinutes(12),
            'is_manual' => false,
        ]);

        $taskResponse = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/personal-tasks', ['title' => 'Study'])
            ->assertCreated();

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/personal-tasks/{$taskResponse->json('data.taskId')}/time-entries/toggle")
            ->assertCreated()
            ->assertJsonPath('data.isRunning', true);

        $this->assertNotNull($activeProjectEntry->fresh()->end_time);
    }

    /**
     * @param  array<int, string>  $permissions
     */
    private function userWithPermissions(array $permissions): User
    {
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $user = User::factory()->create();
        $user->givePermissionTo($permissions);

        return $user;
    }
}
