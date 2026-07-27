<?php

namespace Tests\Feature;

use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Client;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Integration test for Timer Auto-Status Update functionality
 *
 * Tests the complete flow from API endpoint to database changes
 */
class TimeTrackingTaskStatusIntegrationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test complete flow: API call -> Timer start -> Task status update
     */
    public function test_start_timer_api_updates_task_status_and_start_date(): void
    {
        // Create authenticated user
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');

        // Create client and project
        $client = Client::factory()->create();
        $project = Project::factory()->create([
            'client_id' => $client->id,
        ]);
        $this->allowTaskTimer($user, $project);

        // Create a TODO task without start_date
        $task = Task::factory()->create([
            'project_id' => $project->id,
            'status' => TaskStatus::TODO,
            'type' => TaskType::SUBTASK,
            'start_date' => null,
            'parent_id' => null, // Leaf task (no children)
        ]);

        // Verify initial state
        $this->assertEquals(TaskStatus::TODO, $task->status);
        $this->assertNull($task->start_date);

        // Make API call to start timer
        $response = $this->postJson('/api/v1/time-entries/start', [
            'trackableId' => $task->id,
            'trackableType' => 'task',
        ]);

        // Verify API response
        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Timer started successfully',
            ])
            ->assertJsonStructure([
                'data' => [
                    'timeEntryId',
                    'userId',
                    'trackableId',
                    'trackableType',
                    'isActive',
                    'startTime',
                ],
            ]);

        // Refresh task from database
        $task->refresh();

        // Verify task status was automatically updated to IN_PROGRESS
        $this->assertEquals(TaskStatus::IN_PROGRESS, $task->status);

        // Verify start_date was set to today
        $this->assertEquals(Carbon::now('Africa/Cairo')->toDateString(), $task->start_date->toDateString());

        // Verify timer was created and is active
        $this->assertDatabaseHas('time_entries', [
            'user_id' => $user->id,
            'trackable_id' => $task->id,
            'trackable_type' => Task::class,
            'end_time' => null, // Active timer
        ]);
    }

    /**
     * Test that starting timer on task with existing start_date preserves it
     */
    public function test_start_timer_preserves_existing_start_date(): void
    {
        // Create authenticated user
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');

        // Create client and project
        $client = Client::factory()->create();
        $project = Project::factory()->create([
            'client_id' => $client->id,
        ]);
        $this->allowTaskTimer($user, $project);

        $existingStartDate = Carbon::parse('2025-01-01');

        // Create a TODO task with existing start_date
        $task = Task::factory()->create([
            'project_id' => $project->id,
            'status' => TaskStatus::TODO,
            'type' => TaskType::SUBTASK,
            'start_date' => $existingStartDate,
            'parent_id' => null, // Leaf task (no children)
        ]);

        // Make API call to start timer
        $response = $this->postJson('/api/v1/time-entries/start', [
            'trackableId' => $task->id,
            'trackableType' => 'task',
        ]);

        // Verify API response
        $response->assertStatus(201);

        // Refresh task from database
        $task->refresh();

        // Verify status was changed to IN_PROGRESS
        $this->assertEquals(TaskStatus::IN_PROGRESS, $task->status);

        // Verify start_date was NOT changed (preserved existing date)
        $this->assertEquals($existingStartDate->toDateString(), $task->start_date->toDateString());
    }

    /**
     * Test that non-TODO tasks don't get status changed but start_date is still set if null
     */
    public function test_start_timer_on_non_todo_task(): void
    {
        // Create authenticated user
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');

        // Create client and project
        $client = Client::factory()->create();
        $project = Project::factory()->create([
            'client_id' => $client->id,
        ]);
        $this->allowTaskTimer($user, $project);

        // Create a REVIEW task without start_date
        $task = Task::factory()->create([
            'project_id' => $project->id,
            'status' => TaskStatus::REVIEW,
            'type' => TaskType::SUBTASK,
            'start_date' => null,
            'parent_id' => null, // Leaf task (no children)
        ]);

        // Make API call to start timer
        $response = $this->postJson('/api/v1/time-entries/start', [
            'trackableId' => $task->id,
            'trackableType' => 'task',
        ]);

        // Verify API response
        $response->assertStatus(201);

        // Refresh task from database
        $task->refresh();

        // Verify status remained REVIEW (not changed)
        $this->assertEquals(TaskStatus::REVIEW, $task->status);

        // Verify start_date was set to today (since it was null)
        $this->assertEquals(Carbon::now('Africa/Cairo')->toDateString(), $task->start_date->toDateString());
    }

    private function allowTaskTimer(User $user, Project $project): void
    {
        $user->givePermissionTo([
            Permission::findOrCreate('view_tasks', 'web'),
            Permission::findOrCreate('start_timer', 'web'),
        ]);
        $project->members()->create([
            'user_id' => $user->id,
            'role' => 'member',
        ]);
    }
}
