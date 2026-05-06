<?php

namespace App\Modules\HR\Actions;

use App\Enums\PointTransactionType;
use App\Modules\Gamification\Models\PointTransaction;
use App\Modules\Project\Models\ProjectTask;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * Gamification Engine — Deadline Evaluation
 *
 * Triggered when a ProjectTask transitions to DONE.
 *
 * Business rules:
 *   - If the task has no assignee, evaluation is skipped silently.
 *   - If the task has no resolvable deadline (no due_date, no milestone due_date),
 *     evaluation is skipped silently (no deadline = no penalty/reward).
 *   - On time / early  → +5 points  (TASK_ON_TIME)
 *   - Late             → -5 points  (TASK_LATE)
 *
 * Idempotency:
 *   A unique index on (source_type, source_id, type) is not enforced at DB level
 *   because the same task could theoretically be re-opened and re-completed.
 *   Instead, we guard by checking whether a deadline transaction already exists
 *   for this task in the current completion cycle (i.e., since the task was last
 *   moved OUT of DONE). The caller (CompleteProjectTaskAction) is responsible for
 *   ensuring this action is only called on a genuine DONE transition.
 */
class EvaluateTaskDeadlinePointsAction
{
    /**
     * @param  ProjectTask  $task  The task that was just marked DONE.
     */
    public function execute(ProjectTask $task): void
    {
        // ── Guard: must have an assignee ──────────────────────────────────────
        if ($task->assignee_id === null) {
            return;
        }

        // ── Guard: must have a resolvable deadline ────────────────────────────
        // Load milestone relation if not already loaded (needed for fallback)
        if ($task->milestone_id !== null && ! $task->relationLoaded('milestone')) {
            $task->load('milestone');
        }

        $deadline = $task->effectiveDueDate();

        if ($deadline === null) {
            return;
        }

        // ── Idempotency: skip if a deadline transaction already exists ────────
        // This prevents double-awarding if the action is accidentally called twice
        // for the same task completion event.
        $alreadyEvaluated = PointTransaction::where('source_type', ProjectTask::class)
            ->where('source_id', $task->id)
            ->whereIn('type', [
                PointTransactionType::TASK_ON_TIME->value,
                PointTransactionType::TASK_LATE->value,
            ])
            ->exists();

        if ($alreadyEvaluated) {
            return;
        }

        // ── Determine on-time vs late ─────────────────────────────────────────
        $now      = Carbon::now();
        $isOnTime = $now->lte($deadline);

        if ($isOnTime) {
            $points = 5;
            $type   = PointTransactionType::TASK_ON_TIME;
            $reason = 'Task completed on time';
        } else {
            $points = -5;
            $type   = PointTransactionType::TASK_LATE;
            $reason = 'Task missed deadline';
        }

        // ── Compute running balance ───────────────────────────────────────────
        $currentBalance = (int) PointTransaction::where('user_id', $task->assignee_id)
            ->orderByDesc('id')
            ->value('balance_after') ?? 0;

        $balanceAfter = $currentBalance + $points;

        // ── Record the transaction ────────────────────────────────────────────
        PointTransaction::create([
            'user_id'      => $task->assignee_id,
            'points'       => $points,
            'type'         => $type,
            'source_type'  => ProjectTask::class,
            'source_id'    => $task->id,
            'reason'       => $reason,
            'balance_after' => $balanceAfter,
            'created_by'   => Auth::id(),
            'updated_by'   => Auth::id(),
        ]);
    }
}
