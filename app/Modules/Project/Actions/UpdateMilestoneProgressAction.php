<?php

namespace App\Modules\Project\Actions;

use App\Enums\MilestoneStatus;
use App\Modules\Project\Enums\ExecutionTaskStatus;
use App\Modules\Project\Models\Milestone;

/**
 * Recalculates a Milestone's progress_percentage from its tasks and
 * transitions the Milestone status to ACTIVE or COMPLETED accordingly.
 *
 * Called internally by CompleteProjectTaskAction after a task is marked DONE.
 * Can also be called standalone whenever task state changes (e.g. reopen).
 */
class UpdateMilestoneProgressAction
{
    /**
     * @param  Milestone  $milestone  The milestone to recalculate.
     * @return Milestone              The updated milestone instance.
     */
    public function execute(Milestone $milestone): Milestone
    {
        $total = $milestone->tasks()->count();

        // A milestone with no tasks stays at 0 % and remains in its current
        // status — we do not force it to COMPLETED with zero evidence.
        if ($total === 0) {
            $milestone->update(['progress_percentage' => 0]);

            return $milestone->refresh();
        }

        $done = $milestone->tasks()
            ->where('status', ExecutionTaskStatus::DONE->value)
            ->count();

        $percentage = (int) round(($done / $total) * 100);

        // Determine the new status:
        //   100 % → COMPLETED
        //   1–99 % → ACTIVE  (work has started)
        //   0 %   → PENDING  (nothing done yet)
        $newStatus = match (true) {
            $percentage === 100 => MilestoneStatus::COMPLETED,
            $percentage > 0     => MilestoneStatus::ACTIVE,
            default             => MilestoneStatus::PENDING,
        };

        $milestone->update([
            'progress_percentage' => $percentage,
            'status'              => $newStatus,
        ]);

        return $milestone->refresh();
    }
}
