<?php

namespace App\Observers;

use App\Enums\PointTransactionType;
use App\Enums\TaskType;
use App\Models\TaskTestScenario;
use App\Services\Points\UnifiedPointService;

/**
 * Observer for TaskTestScenario model to handle point awards for test scenario verification.
 *
 * This observer:
 * - Awards points when test scenarios are verified as passed (WEEKLY tasks only)
 * - Deducts points when test scenarios are verified as failed (WEEKLY tasks only)
 * - Only applies to test scenarios belonging to WEEKLY tasks
 * - Helps incentivize thorough testing and quality assurance
 *
 * Business Rules:
 * - Only WEEKLY tasks award points for test scenarios
 * - Points are awarded/deducted only when scenarios are VERIFIED (not just completed)
 * - +1 point for verified passed scenarios
 * - -1 point for verified failed scenarios
 */
class TaskTestScenarioObserver
{
    public function __construct(
        protected UnifiedPointService $pointService
    ) {}

    /**
     * Handle the TaskTestScenario "updating" event.
     *
     * This method is called before the model is saved to the database.
     * It handles point awards/deductions when test scenarios are verified.
     *
     * @param TaskTestScenario $scenario
     * @return void
     */
    public function updating(TaskTestScenario $scenario): void
    {
        // Only process if verification status is being changed
        if (!$scenario->isDirty('verified_at') || !$scenario->verified_at) {
            return;
        }

        // Load the task relationship if not already loaded
        if (!$scenario->relationLoaded('task')) {
            $scenario->load('task');
        }

        // Only award points for WEEKLY tasks
        if (!$this->shouldAwardPoints($scenario)) {
            return;
        }

        // Get the task assignee (the developer who worked on this)
        $assignee = $scenario->task->assignee;
        if (!$assignee) {
            return;
        }

        // Award or deduct points based on verification result
        if ($scenario->status === 'passed') {
            $this->pointService->awardTestScenarioPoints($assignee, $scenario);
        } elseif ($scenario->status === 'failed') {
            $this->pointService->deductTestScenarioPoints($assignee, $scenario);
        }
    }

    /**
     * Determine if points should be awarded for this test scenario.
     * Points are only awarded for test scenarios belonging to WEEKLY tasks.
     *
     * @param TaskTestScenario $scenario
     * @return bool
     */
    private function shouldAwardPoints(TaskTestScenario $scenario): bool
    {
        return $scenario->task && $scenario->task->type === TaskType::WEEKLY;
    }
}