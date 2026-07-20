<?php

namespace App\Observers;

use App\Modules\Project\Models\Milestone;
use App\Modules\Project\Models\ProjectHistory;
use Illuminate\Support\Facades\Auth;

/**
 * MilestoneObserver — records project history entries whenever a milestone
 * is created, updated, or deleted.
 *
 * All entries land in the `project_histories` table and are surfaced by the
 * ProjectHistoryController / ActivityTab on the frontend.
 */
class MilestoneObserver
{
    public function created(Milestone $milestone): void
    {
        $this->record(
            $milestone->project_id,
            'milestone_created',
            '',
            $milestone->title,
            "Milestone \"{$milestone->title}\" was created"
        );
    }

    public function updated(Milestone $milestone): void
    {
        $dirty = $milestone->getDirty();

        // Title change
        if (isset($dirty['title'])) {
            $this->record(
                $milestone->project_id,
                'milestone_title_changed',
                $milestone->getOriginal('title'),
                $dirty['title'],
                'Milestone title changed'
            );
        }

        // Status change
        if (isset($dirty['status'])) {
            $oldStatus = $milestone->getOriginal('status');
            $newStatus = $dirty['status'];

            $this->record(
                $milestone->project_id,
                'milestone_status_changed',
                is_object($oldStatus) ? $oldStatus->value : (string) $oldStatus,
                is_object($newStatus) ? $newStatus->value : (string) $newStatus,
                "Milestone \"{$milestone->title}\" status changed"
            );
        }
    }

    public function deleted(Milestone $milestone): void
    {
        $this->record(
            $milestone->project_id,
            'milestone_deleted',
            $milestone->title,
            '',
            "Milestone \"{$milestone->title}\" was deleted"
        );
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function record(
        int $projectId,
        string $fieldName,
        string $oldValue,
        string $newValue,
        string $reason
    ): void {
        ProjectHistory::create([
            'project_id' => $projectId,
            'field_name' => $fieldName,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'reason' => $reason,
            'changed_by' => Auth::id(),
        ]);
    }
}
