<?php

namespace App\Modules\HR\Actions;

use App\Enums\PointTransactionType;
use App\Modules\Gamification\Models\PointTransaction;
use App\Modules\Project\Models\ProjectTask;
use Illuminate\Support\Facades\Auth;

/**
 * Gamification Engine — QA Bug Evaluation
 *
 * Triggered when a ProjectTask is moved BACKWARD from a review/QA stage
 * to a development stage (i.e., QA rejection).
 *
 * QA rejection is defined as a transition FROM:
 *   - IN_REVIEW  → IN_PROGRESS  (code review rejection)
 *   - QA         → IN_PROGRESS  (QA tester rejection)
 *
 * Business rules:
 *   - Assignee (developer) receives -3 points  (BUG_FOUND)
 *   - Auth user (QA tester / reviewer) receives +2 points  (QUALITY_BONUS)
 *     If the auth user IS the assignee (self-review edge case), only the
 *     deduction is applied — the bonus is skipped to prevent self-gaming.
 *
 * Idempotency:
 *   Each QA rejection on the same task creates a new transaction intentionally —
 *   a task can be rejected multiple times and each rejection should be penalised.
 *   There is no deduplication guard here by design.
 */
class EvaluateQABugPointsAction
{
    /**
     * @param  ProjectTask  $task      The task being rejected back to development.
     */
    public function execute(ProjectTask $task): void
    {
        $reviewerId = Auth::id();

        // ── Guard: must have an assignee ──────────────────────────────────────
        if ($task->assignee_id === null) {
            return;
        }

        // ── Deduct points from the developer (assignee) ───────────────────────
        $developerBalance = $this->getLatestBalance($task->assignee_id);

        PointTransaction::create([
            'user_id'       => $task->assignee_id,
            'points'        => -3,
            'type'          => PointTransactionType::BUG_FOUND,
            'source_type'   => ProjectTask::class,
            'source_id'     => $task->id,
            'reason'        => 'Task returned by QA with bugs',
            'balance_after' => $developerBalance - 3,
            'created_by'    => $reviewerId,
            'updated_by'    => $reviewerId,
        ]);

        // ── Award points to the QA tester (auth user) ─────────────────────────
        // Skip if the reviewer is the same person as the assignee (self-review guard)
        if ($reviewerId !== null && $reviewerId !== $task->assignee_id) {
            $reviewerBalance = $this->getLatestBalance($reviewerId);

            PointTransaction::create([
                'user_id'       => $reviewerId,
                'points'        => 2,
                'type'          => PointTransactionType::QUALITY_BONUS,
                'source_type'   => ProjectTask::class,
                'source_id'     => $task->id,
                'reason'        => 'Bug identified',
                'balance_after' => $reviewerBalance + 2,
                'created_by'    => $reviewerId,
                'updated_by'    => $reviewerId,
            ]);
        }
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Fetch the most recent balance_after for a user, defaulting to 0.
     */
    private function getLatestBalance(int $userId): int
    {
        return (int) (PointTransaction::where('user_id', $userId)
            ->orderByDesc('id')
            ->value('balance_after') ?? 0);
    }
}
