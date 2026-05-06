<?php

namespace App\Modules\Project\Actions;

use App\Modules\HR\Actions\EvaluateTaskDeadlinePointsAction;
use App\Modules\Project\Enums\ExecutionTaskStatus;
use App\Modules\Project\Models\ProjectTask;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Marks a ProjectTask as DONE after verifying all deliverables are complete.
 *
 * Business rules enforced:
 *   1. If the task has any deliverables, every one must have is_completed = true.
 *      Violating this throws a ValidationException — the caller receives a
 *      structured error it can forward directly to the API response.
 *   2. The task status is updated to ExecutionTaskStatus::DONE inside a DB
 *      transaction so the status update and the milestone side-effect are atomic.
 *   3. If the task belongs to a milestone, UpdateMilestoneProgressAction is
 *      invoked immediately to keep milestone progress in sync.
 */
class CompleteProjectTaskAction
{
    public function __construct(
        private readonly UpdateMilestoneProgressAction $updateMilestoneProgress,
        private readonly ProjectHistoryAction $historyAction,
        private readonly EvaluateTaskDeadlinePointsAction $evaluateDeadlinePoints,
    ) {}

    /**
     * @param  ProjectTask  $task  The task to complete.
     * @return ProjectTask         The updated task instance.
     *
     * @throws ValidationException  When one or more deliverables are still pending.
     */
    public function execute(ProjectTask $task): ProjectTask
    {
        // ── Guard: already done ───────────────────────────────────────────────
        if ($task->status === ExecutionTaskStatus::DONE) {
            return $task;
        }

        // ── Guard: deliverable completeness ───────────────────────────────────
        $this->assertAllDeliverablesComplete($task);

        // ── Atomic update + side-effect ───────────────────────────────────────
        DB::transaction(function () use ($task) {
            $oldStatus = $task->status;
            $task->update(['status' => ExecutionTaskStatus::DONE]);

            // Refresh so the cast returns the enum value, not the raw string.
            $task->refresh();

            // Load project if not already loaded
            if (!$task->relationLoaded('project')) {
                $task->load('project');
            }

            // Record activity in project history
            $this->historyAction->recordChange(
                $task->project,
                'task_completed',
                $oldStatus,
                ExecutionTaskStatus::DONE,
                "Task '{$task->title}' marked as done"
            );

            // Propagate progress to the parent milestone if one exists.
            if ($task->milestone_id !== null) {
                // Load the milestone fresh inside the transaction so the
                // progress calculation sees the just-committed task status.
                $milestone = $task->milestone()->lockForUpdate()->first();

                if ($milestone !== null) {
                    $this->updateMilestoneProgress->execute($milestone);
                }
            }

            // ── GAMIFICATION: Evaluate deadline points ────────────────────────
            // This must run INSIDE the transaction so the point transaction is
            // atomic with the task status update. If the transaction rolls back,
            // the point transaction is also rolled back.
            $this->evaluateDeadlinePoints->execute($task);
        });

        return $task->refresh();
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Throws a ValidationException if any deliverable is still pending.
     *
     * We load only the incomplete deliverables so we can surface their titles
     * in the error message — useful for the frontend to highlight exactly which
     * items are blocking completion.
     */
    private function assertAllDeliverablesComplete(ProjectTask $task): void
    {
        $pendingDeliverables = $task->deliverables()
            ->where('is_completed', false)
            ->get(['id', 'title']);

        if ($pendingDeliverables->isEmpty()) {
            return;
        }

        $pendingTitles = $pendingDeliverables->pluck('title')->implode(', ');

        throw ValidationException::withMessages([
            'deliverables' => [
                "Cannot complete task: {$pendingDeliverables->count()} pending deliverable(s) must be checked off first ({$pendingTitles}).",
            ],
        ]);
    }
}
