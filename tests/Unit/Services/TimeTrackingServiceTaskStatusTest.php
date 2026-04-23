<?php

namespace Tests\Unit\Services;

use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Modules\Client\Models\Client;
use App\Modules\Project\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\Sales\TimeTrackingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests for TimeTrackingService Task Status Auto-Update
 *
 * Validates that starting a timer on a task automatically:
 * 1. Changes status from TODO to IN_PROGRESS
 * 2. Sets start_date to today if not already set
 */
class TimeTrackingServiceTaskStatusTest extends TestCase
{
    use RefreshDatabase;

    private TimeTrackingService $timeTrackingService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->timeTrackingService = new TimeTrackingService;
    }

    /**
     * Test that starting timer on TODO task changes status to IN_PROGRESS
     */
    public function test_starting_timer_on_todo_task_changes_status_to_in_progress(): void
    {
        // Create user and client
        $user = User::factory()->create();
        $client = Client::factory()->create();

        // Create project with explicit client
        $project = Project::factory()->create([
            'client_id' => $client->id,
        ]);

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

        // Start timer
        $timeEntry = $this->timeTrackingService->startTimer($user, $task);

        // Verify timer was created
        $this->assertNotNull($timeEntry);
        $this->assertEquals($task->id, $timeEntry->trackable_id);
        $this->assertEquals(Task::class, $timeEntry->trackable_type);

        // Refresh task from database
        $task->refresh();

        // Verify status was changed to IN_PROGRESS
        $this->assertEquals(TaskStatus::IN_PROGRESS, $task->status);

        // Verify start_date was set to today
        $this->assertEquals(Carbon::now('Africa/Cairo')->toDateString(), $task->start_date->toDateString());
    }

    /**
     * Test that starting timer on IN_PROGRESS task doesn't change status
     */
    public function test_starting_timer_on_in_progress_task_keeps_status(): void
    {
        // Create user and client
        $user = User::factory()->create();
        $client = Client::factory()->create();

        // Create project with explicit client
        $project = Project::factory()->create([
            'client_id' => $client->id,
        ]);

        // Create an IN_PROGRESS task
        $task = Task::factory()->create([
            'project_id' => $project->id,
            'status' => TaskStatus::IN_PROGRESS,
            'type' => TaskType::SUBTASK,
            'start_date' => Carbon::yesterday(),
            'parent_id' => null, // Leaf task (no children)
        ]);

        $originalStartDate = $task->start_date;

        // Start timer
        $timeEntry = $this->timeTrackingService->startTimer($user, $task);

        // Verify timer was created
        $this->assertNotNull($timeEntry);

        // Refresh task from database
        $task->refresh();

        // Verify status remained IN_PROGRESS
        $this->assertEquals(TaskStatus::IN_PROGRESS, $task->status);

        // Verify start_date was not changed
        $this->assertEquals($originalStartDate->toDateString(), $task->start_date->toDateString());
    }

    /**
     * Test that starting timer on task with existing start_date doesn't change it
     */
    public function test_starting_timer_on_task_with_existing_start_date_keeps_date(): void
    {
        // Create user and client
        $user = User::factory()->create();
        $client = Client::factory()->create();

        // Create project with explicit client
        $project = Project::factory()->create([
            'client_id' => $client->id,
        ]);

        $existingStartDate = Carbon::parse('2025-01-01');

        // Create a TODO task with existing start_date
        $task = Task::factory()->create([
            'project_id' => $project->id,
            'status' => TaskStatus::TODO,
            'type' => TaskType::SUBTASK,
            'start_date' => $existingStartDate,
            'parent_id' => null, // Leaf task (no children)
        ]);

        // Start timer
        $timeEntry = $this->timeTrackingService->startTimer($user, $task);

        // Verify timer was created
        $this->assertNotNull($timeEntry);

        // Refresh task from database
        $task->refresh();

        // Verify status was changed to IN_PROGRESS
        $this->assertEquals(TaskStatus::IN_PROGRESS, $task->status);

        // Verify start_date was not changed
        $this->assertEquals($existingStartDate->toDateString(), $task->start_date->toDateString());
    }

    /**
     * Test that starting timer on REVIEW task doesn't change status
     */
    public function test_starting_timer_on_review_task_keeps_status(): void
    {
        // Create user and client
        $user = User::factory()->create();
        $client = Client::factory()->create();

        // Create project with explicit client
        $project = Project::factory()->create([
            'client_id' => $client->id,
        ]);

        // Create a REVIEW task
        $task = Task::factory()->create([
            'project_id' => $project->id,
            'status' => TaskStatus::REVIEW,
            'type' => TaskType::SUBTASK,
            'start_date' => null,
            'parent_id' => null, // Leaf task (no children)
        ]);

        // Start timer
        $timeEntry = $this->timeTrackingService->startTimer($user, $task);

        // Verify timer was created
        $this->assertNotNull($timeEntry);

        // Refresh task from database
        $task->refresh();

        // Verify status remained REVIEW (not changed)
        $this->assertEquals(TaskStatus::REVIEW, $task->status);

        // Verify start_date was set to today (since it was null)
        $this->assertEquals(Carbon::now('Africa/Cairo')->toDateString(), $task->start_date->toDateString());
    }
}
