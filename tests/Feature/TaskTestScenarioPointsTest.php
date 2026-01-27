<?php

namespace Tests\Feature;

use App\Enums\PointTransactionType;
use App\Enums\TaskType;
use App\Models\PointTransaction;
use App\Models\Task;
use App\Models\TaskTestScenario;
use App\Models\User;
use App\Models\UserPoint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskTestScenarioPointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_weekly_task_test_scenario_verification_awards_points_when_passed()
    {
        // Arrange
        $user = User::factory()->create(['total_points' => 0]);
        $task = Task::factory()->create([
            'type' => TaskType::WEEKLY,
            'assigned_to' => $user->id,
        ]);
        
        $scenario = TaskTestScenario::factory()->create([
            'task_id' => $task->id,
            'status' => 'pending',
            'verified_at' => null,
        ]);

        // Act - Verify scenario as passed
        $scenario->update([
            'status' => 'passed',
            'verified_at' => now(),
            'verified_by' => User::factory()->create()->id,
        ]);

        // Assert
        $user->refresh();
        $this->assertEquals(1, $user->total_points);
        
        // Check PointTransaction was created
        $this->assertDatabaseHas('point_transactions', [
            'user_id' => $user->id,
            'points' => 1,
            'type' => PointTransactionType::TEST_SCENARIO_VERIFIED->value,
            'source_type' => TaskTestScenario::class,
            'source_id' => $scenario->id,
        ]);

        // Check UserPoint was created
        $this->assertDatabaseHas('user_points', [
            'user_id' => $user->id,
            'points_change' => 1,
            'points_after' => 1,
            'type' => 'automatic',
            'transaction_type' => PointTransactionType::TEST_SCENARIO_VERIFIED->value,
        ]);
    }

    public function test_weekly_task_test_scenario_verification_deducts_points_when_failed()
    {
        // Arrange
        $user = User::factory()->create(['total_points' => 5]);
        $task = Task::factory()->create([
            'type' => TaskType::WEEKLY,
            'assigned_to' => $user->id,
        ]);
        
        $scenario = TaskTestScenario::factory()->create([
            'task_id' => $task->id,
            'status' => 'pending',
            'verified_at' => null,
        ]);

        // Act - Verify scenario as failed
        $scenario->update([
            'status' => 'failed',
            'verified_at' => now(),
            'verified_by' => User::factory()->create()->id,
        ]);

        // Assert
        $user->refresh();
        $this->assertEquals(4, $user->total_points);
        
        // Check PointTransaction was created
        $this->assertDatabaseHas('point_transactions', [
            'user_id' => $user->id,
            'points' => -1,
            'type' => PointTransactionType::TEST_SCENARIO_FAILED->value,
            'source_type' => TaskTestScenario::class,
            'source_id' => $scenario->id,
        ]);

        // Check UserPoint was created
        $this->assertDatabaseHas('user_points', [
            'user_id' => $user->id,
            'points_change' => -1,
            'points_after' => 4,
            'type' => 'automatic',
            'transaction_type' => PointTransactionType::TEST_SCENARIO_FAILED->value,
        ]);
    }

    public function test_main_task_test_scenario_verification_does_not_award_points()
    {
        // Arrange
        $user = User::factory()->create(['total_points' => 0]);
        $task = Task::factory()->create([
            'type' => TaskType::MAIN,
            'assigned_to' => $user->id,
        ]);
        
        $scenario = TaskTestScenario::factory()->create([
            'task_id' => $task->id,
            'status' => 'pending',
            'verified_at' => null,
        ]);

        // Act - Verify scenario as passed
        $scenario->update([
            'status' => 'passed',
            'verified_at' => now(),
            'verified_by' => User::factory()->create()->id,
        ]);

        // Assert - No points should be awarded
        $user->refresh();
        $this->assertEquals(0, $user->total_points);
        
        // Check no PointTransaction was created
        $this->assertDatabaseMissing('point_transactions', [
            'user_id' => $user->id,
            'source_type' => TaskTestScenario::class,
            'source_id' => $scenario->id,
        ]);
    }

    public function test_subtask_test_scenario_verification_does_not_award_points()
    {
        // Arrange
        $user = User::factory()->create(['total_points' => 0]);
        $task = Task::factory()->create([
            'type' => TaskType::SUBTASK,
            'assigned_to' => $user->id,
        ]);
        
        $scenario = TaskTestScenario::factory()->create([
            'task_id' => $task->id,
            'status' => 'pending',
            'verified_at' => null,
        ]);

        // Act - Verify scenario as passed
        $scenario->update([
            'status' => 'passed',
            'verified_at' => now(),
            'verified_by' => User::factory()->create()->id,
        ]);

        // Assert - No points should be awarded
        $user->refresh();
        $this->assertEquals(0, $user->total_points);
        
        // Check no PointTransaction was created
        $this->assertDatabaseMissing('point_transactions', [
            'user_id' => $user->id,
            'source_type' => TaskTestScenario::class,
            'source_id' => $scenario->id,
        ]);
    }

    public function test_test_scenario_verification_without_assignee_does_not_award_points()
    {
        // Arrange
        $task = Task::factory()->create([
            'type' => TaskType::WEEKLY,
            'assigned_to' => null, // No assignee
        ]);
        
        $scenario = TaskTestScenario::factory()->create([
            'task_id' => $task->id,
            'status' => 'pending',
            'verified_at' => null,
        ]);

        // Act - Verify scenario as passed
        $scenario->update([
            'status' => 'passed',
            'verified_at' => now(),
            'verified_by' => User::factory()->create()->id,
        ]);

        // Assert - No PointTransaction should be created
        $this->assertDatabaseMissing('point_transactions', [
            'source_type' => TaskTestScenario::class,
            'source_id' => $scenario->id,
        ]);
    }

    public function test_test_scenario_status_change_without_verification_does_not_award_points()
    {
        // Arrange
        $user = User::factory()->create(['total_points' => 0]);
        $task = Task::factory()->create([
            'type' => TaskType::WEEKLY,
            'assigned_to' => $user->id,
        ]);
        
        $scenario = TaskTestScenario::factory()->create([
            'task_id' => $task->id,
            'status' => 'pending',
            'verified_at' => null,
        ]);

        // Act - Change status without setting verified_at
        $scenario->update([
            'status' => 'passed',
            // verified_at remains null
        ]);

        // Assert - No points should be awarded
        $user->refresh();
        $this->assertEquals(0, $user->total_points);
        
        // Check no PointTransaction was created
        $this->assertDatabaseMissing('point_transactions', [
            'user_id' => $user->id,
            'source_type' => TaskTestScenario::class,
            'source_id' => $scenario->id,
        ]);
    }
}