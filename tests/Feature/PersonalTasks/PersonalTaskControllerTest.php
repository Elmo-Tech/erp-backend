<?php

namespace Tests\Feature\PersonalTasks;

use App\Modules\Project\Models\Project;
use App\Modules\Project\Models\ProjectTask;
use App\Modules\Project\Models\ProjectTimeEntry;
use App\Modules\Settings\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
