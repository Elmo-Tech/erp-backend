<?php

namespace Tests\Unit\Observers;

use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Test TaskObserver bug bounce logic.
 * 
 * Verifies that the TaskObserver correctly:
 * - Detects testing → in_progress transitions
 * - Increments reopen_count by exactly 1
 * - Does not modify reopen_count for other transitions
 */
class TaskObserverTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->project = Project::factory()->create();
    }

    /** @test */
    public function it_increments_reopen_count_when_transitioning_from_testing_to_in_progress()
    {
        // Create a task in testing status
        $task = Task::factory()->create([
            'project_id' => $this->project->id,
            'assigned_to' => $this->user->id,
            'status' => TaskStatus::TESTING,
            'reopen_count' => 0,
        ]);

        // Transition from testing to in_progress
        $task->update(['status' => TaskStatus::IN_PROGRESS]);

        // Verify reopen_count was incremented
        $this->assertEquals(1, $task->fresh()->reopen_count);
    }

    /** @test */
    public function it_increments_reopen_count_multiple_times_for_multiple_bounces()
    {
        // Create a task with existing bounce count
        $task = Task::factory()->create([
            'project_id' => $this->project->id,
            'assigned_to' => $this->user->id,
            'status' => TaskStatus::TESTING,
            'reopen_count' => 2,
        ]);

        // Transition from testing to in_progress
        $task->update(['status' => TaskStatus::IN_PROGRESS]);

        // Verify reopen_count was incremented from 2 to 3
        $this->assertEquals(3, $task->fresh()->reopen_count);
    }

    /** @test */
    public function it_does_not_increment_reopen_count_for_other_transitions()
    {
        $testCases = [
            // From todo to in_progress
            ['from' => TaskStatus::TODO, 'to' => TaskStatus::IN_PROGRESS],
            // From in_progress to review
            ['from' => TaskStatus::IN_PROGRESS, 'to' => TaskStatus::REVIEW],
            // From review to testing
            ['from' => TaskStatus::REVIEW, 'to' => TaskStatus::TESTING],
            // From testing to completed
            ['from' => TaskStatus::TESTING, 'to' => TaskStatus::COMPLETED],
            // From in_progress to todo
            ['from' => TaskStatus::IN_PROGRESS, 'to' => TaskStatus::TODO],
            // From review to in_progress
            ['from' => TaskStatus::REVIEW, 'to' => TaskStatus::IN_PROGRESS],
        ];

        foreach ($testCases as $case) {
            $task = Task::factory()->create([
                'project_id' => $this->project->id,
                'assigned_to' => $this->user->id,
                'status' => $case['from'],
                'reopen_count' => 0,
            ]);

            // Perform the transition
            $task->update(['status' => $case['to']]);

            // Verify reopen_count was NOT incremented
            $this->assertEquals(0, $task->fresh()->reopen_count, 
                "Reopen count should not increment for {$case['from']->value} → {$case['to']->value} transition");
        }
    }

    /** @test */
    public function it_does_not_increment_reopen_count_when_status_is_not_changed()
    {
        $task = Task::factory()->create([
            'project_id' => $this->project->id,
            'assigned_to' => $this->user->id,
            'status' => TaskStatus::TESTING,
            'reopen_count' => 0,
        ]);

        // Update other fields but not status
        $task->update(['title' => 'Updated title']);

        // Verify reopen_count was not changed
        $this->assertEquals(0, $task->fresh()->reopen_count);
    }

    /** @test */
    public function it_handles_default_reopen_count_correctly()
    {
        // Create task without explicitly setting reopen_count (uses default 0)
        $task = Task::factory()->create([
            'project_id' => $this->project->id,
            'assigned_to' => $this->user->id,
            'status' => TaskStatus::TESTING,
        ]);

        // Verify default reopen_count is 0
        $this->assertEquals(0, $task->reopen_count);

        // Transition from testing to in_progress
        $task->update(['status' => TaskStatus::IN_PROGRESS]);

        // Verify reopen_count was incremented to 1
        $this->assertEquals(1, $task->fresh()->reopen_count);
    }
}