<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use App\Models\TaskHelpRequest;
use App\Models\TaskSupervisionSession;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class TaskSupervisionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_task_team_managers_with_project_access_can_set_an_eligible_supervisor(): void
    {
        [$manager, $project, $task, $supervisor] = $this->supervisionContext();
        $outsider = User::factory()->create();
        $inactiveMember = User::factory()->create(['is_active' => false]);
        $project->members()->create(['user_id' => $inactiveMember->id, 'role' => 'member']);
        $ineligibleUser = User::factory()->create();

        $this->actingAs($outsider, 'sanctum')
            ->putJson("/api/v1/tasks/{$task->id}/supervisor", ['supervisorId' => $supervisor->id])
            ->assertForbidden();

        $this->actingAs($manager, 'sanctum')
            ->putJson("/api/v1/tasks/{$task->id}/supervisor", ['supervisorId' => $inactiveMember->id])
            ->assertStatus(422);

        $this->actingAs($manager, 'sanctum')
            ->putJson("/api/v1/tasks/{$task->id}/supervisor", ['supervisorId' => $ineligibleUser->id])
            ->assertStatus(422);

        $this->actingAs($manager, 'sanctum')
            ->putJson("/api/v1/tasks/{$task->id}/supervisor", ['supervisorId' => $supervisor->id])
            ->assertOk()
            ->assertJsonPath('data.supervisorId', $supervisor->id)
            ->assertJsonPath('data.supervisor.userId', $supervisor->id);

        $this->assertDatabaseHas('task_activities', [
            'task_id' => $task->id,
            'user_id' => $manager->id,
            'type' => 'supervisor_changed',
        ]);
    }

    public function test_assignees_can_request_help_and_only_authorized_actors_can_follow_the_lifecycle(): void
    {
        [$manager, $project, $task, $supervisor, $assignee] = $this->supervisionContext();
        $this->assignSupervisor($manager, $task, $supervisor);
        $unassignedProjectMember = User::factory()->create();
        $project->members()->create(['user_id' => $unassignedProjectMember->id, 'role' => 'member']);

        $this->actingAs($unassignedProjectMember, 'sanctum')
            ->postJson("/api/v1/tasks/{$task->id}/help-requests", ['message' => 'I need a review.'])
            ->assertForbidden();

        $created = $this->actingAs($assignee, 'sanctum')
            ->postJson("/api/v1/tasks/{$task->id}/help-requests", ['message' => 'I need a review.'])
            ->assertCreated()
            ->assertJsonPath('data.status', 'open');
        $helpRequestId = $created->json('data.helpRequestId');

        $this->actingAs($assignee, 'sanctum')
            ->postJson("/api/v1/tasks/{$task->id}/help-requests", ['message' => 'A duplicate request.'])
            ->assertStatus(422);

        $this->actingAs($unassignedProjectMember, 'sanctum')
            ->patchJson("/api/v1/task-help-requests/{$helpRequestId}", ['status' => 'acknowledged'])
            ->assertForbidden();

        $this->actingAs($supervisor, 'sanctum')
            ->patchJson("/api/v1/task-help-requests/{$helpRequestId}", ['status' => 'acknowledged'])
            ->assertOk()
            ->assertJsonPath('data.status', 'acknowledged')
            ->assertJsonPath('data.acknowledgedById', $supervisor->id);

        $this->actingAs($assignee, 'sanctum')
            ->patchJson("/api/v1/task-help-requests/{$helpRequestId}", ['status' => 'cancelled'])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $secondRequest = $this->actingAs($assignee, 'sanctum')
            ->postJson("/api/v1/tasks/{$task->id}/help-requests", ['message' => 'Please help with the test failure.'])
            ->assertCreated();
        $secondRequestId = $secondRequest->json('data.helpRequestId');

        $this->actingAs($supervisor, 'sanctum')
            ->patchJson("/api/v1/task-help-requests/{$secondRequestId}", ['status' => 'acknowledged'])
            ->assertOk();

        $this->actingAs($supervisor, 'sanctum')
            ->patchJson("/api/v1/task-help-requests/{$secondRequestId}", [
                'status' => 'resolved',
                'resolutionNote' => 'Reviewed and explained the failing assertion.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'resolved')
            ->assertJsonPath('data.resolvedById', $supervisor->id)
            ->assertJsonPath('data.resolutionNote', 'Reviewed and explained the failing assertion.');
    }

    public function test_supervision_sessions_are_independent_from_time_entries_and_limited_to_one_active_session(): void
    {
        [$manager, $project, $task, $supervisor, $assignee] = $this->supervisionContext();
        $this->assignSupervisor($manager, $task, $supervisor);

        $helpRequest = TaskHelpRequest::factory()->create([
            'task_id' => $task->id,
            'requested_by' => $assignee->id,
        ]);
        TimeEntry::factory()->active()->create([
            'user_id' => $supervisor->id,
            'trackable_id' => $task->id,
            'trackable_type' => Task::class,
        ]);
        $timeEntriesBefore = TimeEntry::count();
        $totalTimeBefore = $task->fresh()->total_time_spent;

        $started = $this->actingAs($supervisor, 'sanctum')
            ->postJson("/api/v1/tasks/{$task->id}/supervision-sessions/start", [
                'helpRequestId' => $helpRequest->id,
                'notes' => 'Pairing on the failing integration test.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.taskId', $task->id)
            ->assertJsonPath('data.isActive', true)
            ->assertJsonPath('data.notes', 'Pairing on the failing integration test.');
        $sessionId = $started->json('data.supervisionSessionId');

        $this->actingAs($supervisor, 'sanctum')
            ->getJson('/api/v1/task-supervision-sessions/active')
            ->assertOk()
            ->assertJsonPath('data.supervisionSessionId', $sessionId);

        $this->assertDatabaseHas('task_help_requests', [
            'id' => $helpRequest->id,
            'status' => 'acknowledged',
            'acknowledged_by' => $supervisor->id,
        ]);
        $this->assertSame($timeEntriesBefore, TimeEntry::count());
        $this->assertSame($totalTimeBefore, $task->fresh()->total_time_spent);

        $secondTask = Task::factory()->for($project)->create();
        $this->assignSupervisor($manager, $secondTask, $supervisor);
        $this->actingAs($supervisor, 'sanctum')
            ->postJson("/api/v1/tasks/{$secondTask->id}/supervision-sessions/start")
            ->assertStatus(409);

        $session = TaskSupervisionSession::findOrFail($sessionId);
        $heartbeatBefore = now()->subMinute();
        $session->update([
            'started_at' => now()->subMinutes(7)->subSeconds(30),
            'last_heartbeat_at' => $heartbeatBefore,
        ]);

        $this->actingAs($supervisor, 'sanctum')
            ->postJson("/api/v1/task-supervision-sessions/{$sessionId}/heartbeat")
            ->assertOk();
        $this->assertTrue(TaskSupervisionSession::findOrFail($sessionId)->last_heartbeat_at->gt($heartbeatBefore));

        $this->actingAs($supervisor, 'sanctum')
            ->postJson("/api/v1/task-supervision-sessions/{$sessionId}/stop", [
                'stopReason' => 'Pairing complete.',
                'durationMinutes' => 99999,
            ])
            ->assertOk()
            ->assertJsonPath('data.stopReason', 'Pairing complete.');

        $session->refresh();
        $this->assertNotNull($session->ended_at);
        $this->assertGreaterThanOrEqual(7, $session->duration_minutes);
        $this->assertNotSame(99999, $session->duration_minutes);
        $this->assertSame($timeEntriesBefore, TimeEntry::count());
        $this->assertSame($totalTimeBefore, $task->fresh()->total_time_spent);
    }

    public function test_task_supervision_and_summary_are_not_visible_to_unrelated_project_members(): void
    {
        [$manager, $project, $task, $supervisor, $assignee] = $this->supervisionContext();
        $this->assignSupervisor($manager, $task, $supervisor);
        $request = TaskHelpRequest::factory()->create([
            'task_id' => $task->id,
            'requested_by' => $assignee->id,
        ]);
        TaskSupervisionSession::factory()->create([
            'task_id' => $task->id,
            'supervisor_id' => $supervisor->id,
            'help_request_id' => $request->id,
        ]);
        $unrelatedProjectMember = User::factory()->create();
        $project->members()->create(['user_id' => $unrelatedProjectMember->id, 'role' => 'member']);

        $this->actingAs($unrelatedProjectMember, 'sanctum')
            ->getJson("/api/v1/tasks/{$task->id}/supervision")
            ->assertForbidden();
        $this->actingAs($unrelatedProjectMember, 'sanctum')
            ->getJson("/api/v1/task-supervision/summary?taskId={$task->id}")
            ->assertForbidden();

        $this->actingAs($assignee, 'sanctum')
            ->getJson("/api/v1/tasks/{$task->id}/supervision")
            ->assertOk()
            ->assertJsonPath('data.supervisor.userId', $supervisor->id)
            ->assertJsonCount(1, 'data.helpRequests')
            ->assertJsonCount(1, 'data.sessions');

        $this->actingAs($assignee, 'sanctum')
            ->getJson("/api/v1/task-supervision/summary?taskId={$task->id}&supervisorId={$supervisor->id}")
            ->assertOk()
            ->assertJsonPath('data.summary.taskId', $task->id)
            ->assertJsonPath('data.summary.totalSessions', 1);

        $this->actingAs($manager, 'sanctum')
            ->getJson("/api/v1/task-supervision/summary?supervisorId={$supervisor->id}")
            ->assertOk()
            ->assertJsonPath('data.summary.totalSessions', 1);
    }

    public function test_former_help_requesters_cannot_view_supervision_records_or_cancel_after_unassignment(): void
    {
        [$manager, $project, $task, $supervisor, $assignee] = $this->supervisionContext();
        $this->assignSupervisor($manager, $task, $supervisor);
        $formerRequester = User::factory()->create();
        $task->assignees()->create([
            'user_id' => $formerRequester->id,
            'role' => 'developer',
            'is_primary' => false,
        ]);

        $formerHelpRequestId = $this->actingAs($formerRequester, 'sanctum')
            ->postJson("/api/v1/tasks/{$task->id}/help-requests", ['message' => 'I needed help before reassignment.'])
            ->assertCreated()
            ->json('data.helpRequestId');
        $otherHelpRequest = TaskHelpRequest::factory()->create([
            'task_id' => $task->id,
            'requested_by' => $assignee->id,
        ]);
        TaskSupervisionSession::factory()->create([
            'task_id' => $task->id,
            'supervisor_id' => $supervisor->id,
            'help_request_id' => $otherHelpRequest->id,
        ]);
        $task->assignees()->where('user_id', $formerRequester->id)->delete();

        $this->actingAs($formerRequester, 'sanctum')
            ->getJson("/api/v1/tasks/{$task->id}/supervision")
            ->assertForbidden()
            ->assertJsonMissingPath('data');
        $this->actingAs($formerRequester, 'sanctum')
            ->getJson("/api/v1/task-supervision/summary?taskId={$task->id}")
            ->assertForbidden()
            ->assertJsonMissingPath('data');
        $this->actingAs($formerRequester, 'sanctum')
            ->patchJson("/api/v1/task-help-requests/{$formerHelpRequestId}", ['status' => 'cancelled'])
            ->assertForbidden();
    }

    /**
     * @return array{User, Project, Task, User, User}
     */
    private function supervisionContext(): array
    {
        $manager = User::factory()->create();
        $manager->givePermissionTo(Permission::findOrCreate('manage_task_team', 'web'));

        $project = Project::factory()->create();
        $project->members()->create(['user_id' => $manager->id, 'role' => 'lead']);

        $supervisor = User::factory()->create();
        $project->members()->create(['user_id' => $supervisor->id, 'role' => 'member']);

        $assignee = User::factory()->create();
        $task = Task::factory()->for($project)->create(['total_time_spent' => 37]);
        $task->assignees()->create([
            'user_id' => $assignee->id,
            'role' => 'developer',
            'is_primary' => true,
        ]);

        return [$manager, $project, $task, $supervisor, $assignee];
    }

    private function assignSupervisor(User $manager, Task $task, User $supervisor): void
    {
        $this->actingAs($manager, 'sanctum')
            ->putJson("/api/v1/tasks/{$task->id}/supervisor", ['supervisorId' => $supervisor->id])
            ->assertOk();
    }
}
