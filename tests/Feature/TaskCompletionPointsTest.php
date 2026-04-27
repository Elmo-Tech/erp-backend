<?php

namespace Tests\Feature;

use App\Enums\PointTransactionType;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\PointTransaction;
use App\Modules\Task\Models\Task;
use App\Modules\Task\Models\TaskAssignee;
use App\Modules\User\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskCompletionPointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_task_completed_on_time_awards_one_point()
    {
        // Arrange
        $user = User::factory()->create(['total_points' => 0]);
        $task = Task::factory()->create([
            'type' => TaskType::MAIN,
            'status' => TaskStatus::IN_PROGRESS,
            'due_date' => Carbon::tomorrow(),
        ]);

        // Create primary assignee
        TaskAssignee::factory()->create([
            'task_id' => $task->id,
            'user_id' => $user->id,
            'is_primary' => true,
        ]);

        // Act - Complete task on time
        $task->update(['status' => TaskStatus::COMPLETED]);

        // Assert
        $user->refresh();
        $this->assertEquals(1, $user->total_points);

        // Check PointTransaction was created for on-time delivery
        $this->assertDatabaseHas('point_transactions', [
            'user_id' => $user->id,
            'points' => 1,
            'type' => PointTransactionType::TASK_ON_TIME->value,
            'source_type' => Task::class,
            'source_id' => $task->id,
        ]);

        // Check no completion points were awarded (removed from system)
        $this->assertDatabaseMissing('point_transactions', [
            'user_id' => $user->id,
            'type' => 'task_completed', // Old enum value should not exist
        ]);
    }

    public function test_task_completed_one_day_late_deducts_one_point()
    {
        // Arrange
        $user = User::factory()->create(['total_points' => 5]);
        $task = Task::factory()->create([
            'type' => TaskType::MAIN,
            'status' => TaskStatus::IN_PROGRESS,
            'due_date' => Carbon::yesterday(), // Due yesterday
        ]);

        // Create primary assignee
        TaskAssignee::factory()->create([
            'task_id' => $task->id,
            'user_id' => $user->id,
            'is_primary' => true,
        ]);

        // Act - Complete task late
        $task->update(['status' => TaskStatus::COMPLETED]);

        // Assert
        $user->refresh();
        $this->assertEquals(4, $user->total_points); // 5 - 1 = 4

        // Check PointTransaction was created for late delivery
        $this->assertDatabaseHas('point_transactions', [
            'user_id' => $user->id,
            'points' => -1,
            'type' => PointTransactionType::TASK_LATE->value,
            'source_type' => Task::class,
            'source_id' => $task->id,
        ]);

        // Check delay_days was calculated correctly
        $task->refresh();
        $this->assertEquals(1, $task->delay_days);
    }

    public function test_task_completed_three_days_late_deducts_three_points()
    {
        // Arrange
        $user = User::factory()->create(['total_points' => 10]);
        $task = Task::factory()->create([
            'type' => TaskType::WEEKLY,
            'status' => TaskStatus::IN_PROGRESS,
            'due_date' => Carbon::now()->subDays(3), // Due 3 days ago
        ]);

        // Create primary assignee
        TaskAssignee::factory()->create([
            'task_id' => $task->id,
            'user_id' => $user->id,
            'is_primary' => true,
        ]);

        // Act - Complete task 3 days late
        $task->update(['status' => TaskStatus::COMPLETED]);

        // Assert
        $user->refresh();
        $this->assertEquals(7, $user->total_points); // 10 - 3 = 7

        // Check PointTransaction was created for late delivery with correct points
        $this->assertDatabaseHas('point_transactions', [
            'user_id' => $user->id,
            'points' => -3,
            'type' => PointTransactionType::TASK_LATE->value,
            'source_type' => Task::class,
            'source_id' => $task->id,
        ]);

        // Check delay_days was calculated correctly
        $task->refresh();
        $this->assertEquals(3, $task->delay_days);
    }

    public function test_task_completed_without_due_date_awards_no_points()
    {
        // Arrange
        $user = User::factory()->create(['total_points' => 5]);
        $task = Task::factory()->create([
            'type' => TaskType::MAIN,
            'status' => TaskStatus::IN_PROGRESS,
            'due_date' => null, // No due date
        ]);

        // Create primary assignee
        TaskAssignee::factory()->create([
            'task_id' => $task->id,
            'user_id' => $user->id,
            'is_primary' => true,
        ]);

        // Act - Complete task
        $task->update(['status' => TaskStatus::COMPLETED]);

        // Assert - No points should be awarded or deducted
        $user->refresh();
        $this->assertEquals(5, $user->total_points); // Unchanged

        // Check no PointTransaction was created
        $this->assertDatabaseMissing('point_transactions', [
            'user_id' => $user->id,
            'source_type' => Task::class,
            'source_id' => $task->id,
        ]);
    }

    public function test_subtask_completion_does_not_award_points()
    {
        // Arrange
        $user = User::factory()->create(['total_points' => 0]);
        $task = Task::factory()->create([
            'type' => TaskType::SUBTASK, // Subtasks don't award points
            'status' => TaskStatus::IN_PROGRESS,
            'due_date' => Carbon::tomorrow(),
        ]);

        // Create primary assignee
        TaskAssignee::factory()->create([
            'task_id' => $task->id,
            'user_id' => $user->id,
            'is_primary' => true,
        ]);

        // Act - Complete subtask on time
        $task->update(['status' => TaskStatus::COMPLETED]);

        // Assert - No points should be awarded
        $user->refresh();
        $this->assertEquals(0, $user->total_points);

        // Check no PointTransaction was created
        $this->assertDatabaseMissing('point_transactions', [
            'user_id' => $user->id,
            'source_type' => Task::class,
            'source_id' => $task->id,
        ]);
    }

    public function test_only_primary_assignees_get_points()
    {
        // Arrange
        $primaryUser = User::factory()->create(['total_points' => 0]);
        $secondaryUser = User::factory()->create(['total_points' => 0]);

        $task = Task::factory()->create([
            'type' => TaskType::MAIN,
            'status' => TaskStatus::IN_PROGRESS,
            'due_date' => Carbon::tomorrow(),
        ]);

        // Create primary and secondary assignees
        TaskAssignee::factory()->create([
            'task_id' => $task->id,
            'user_id' => $primaryUser->id,
            'is_primary' => true,
        ]);

        TaskAssignee::factory()->create([
            'task_id' => $task->id,
            'user_id' => $secondaryUser->id,
            'is_primary' => false, // Not primary
        ]);

        // Act - Complete task on time
        $task->update(['status' => TaskStatus::COMPLETED]);

        // Assert - Only primary assignee gets points
        $primaryUser->refresh();
        $secondaryUser->refresh();

        $this->assertEquals(1, $primaryUser->total_points);
        $this->assertEquals(0, $secondaryUser->total_points);

        // Check PointTransaction was created only for primary assignee
        $this->assertDatabaseHas('point_transactions', [
            'user_id' => $primaryUser->id,
            'points' => 1,
            'type' => PointTransactionType::TASK_ON_TIME->value,
        ]);

        $this->assertDatabaseMissing('point_transactions', [
            'user_id' => $secondaryUser->id,
            'source_type' => Task::class,
            'source_id' => $task->id,
        ]);
    }

    public function test_task_reopening_still_deducts_bug_bounce_points()
    {
        // Arrange
        $user = User::factory()->create(['total_points' => 5]);
        $task = Task::factory()->create([
            'type' => TaskType::MAIN,
            'status' => TaskStatus::TESTING,
            'reopen_count' => 0,
        ]);

        // Create primary assignee
        TaskAssignee::factory()->create([
            'task_id' => $task->id,
            'user_id' => $user->id,
            'is_primary' => true,
        ]);

        // Act - Reopen task from testing (bug bounce)
        $task->update(['status' => TaskStatus::IN_PROGRESS]);

        // Assert
        $user->refresh();
        $task->refresh();

        $this->assertEquals(4, $user->total_points); // 5 - 1 = 4
        $this->assertEquals(1, $task->reopen_count);

        // Check PointTransaction was created for task reopening
        $this->assertDatabaseHas('point_transactions', [
            'user_id' => $user->id,
            'points' => -1,
            'type' => PointTransactionType::TASK_REOPENED->value,
            'source_type' => Task::class,
            'source_id' => $task->id,
        ]);
    }

    public function test_task_completed_early_awards_on_time_points()
    {
        // Arrange
        $user = User::factory()->create(['total_points' => 0]);
        $task = Task::factory()->create([
            'type' => TaskType::MAIN,
            'status' => TaskStatus::IN_PROGRESS,
            'due_date' => Carbon::now()->addDays(3), // Due in 3 days
        ]);

        // Create primary assignee
        TaskAssignee::factory()->create([
            'task_id' => $task->id,
            'user_id' => $user->id,
            'is_primary' => true,
        ]);

        // Act - Complete task early
        $task->update(['status' => TaskStatus::COMPLETED]);

        // Assert - Should get on-time points (early is considered on-time)
        $user->refresh();
        $task->refresh();

        $this->assertEquals(1, $user->total_points);
        $this->assertTrue($task->delay_days <= 0); // Negative delay_days means early

        // Check PointTransaction was created for on-time delivery
        $this->assertDatabaseHas('point_transactions', [
            'user_id' => $user->id,
            'points' => 1,
            'type' => PointTransactionType::TASK_ON_TIME->value,
            'source_type' => Task::class,
            'source_id' => $task->id,
        ]);
    }
}
