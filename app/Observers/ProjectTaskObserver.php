<?php

namespace App\Observers;

use App\Modules\Project\Models\ProjectHistory;
use App\Modules\Project\Models\ProjectTask;
use App\Modules\Settings\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * ProjectTaskObserver — records project history entries whenever a project
 * execution task is created, updated, or deleted.
 *
 * All entries land in the `project_histories` table and are surfaced by the
 * ProjectHistoryController / ActivityTab on the frontend.
 *
 * NOTE: This observer is intentionally lightweight. The ProjectHistoryAction
 * already handles status and assignee changes inside ProjectTaskController.
 * This observer catches any changes that bypass the controller (e.g. direct
 * model updates, queue jobs, etc.) and provides a safety net for the audit log.
 */
class ProjectTaskObserver
{
    public function created(ProjectTask $task): void
    {
        $assigneeName = $task->assignee_id
            ? (User::find($task->assignee_id)?->name ?? "User #{$task->assignee_id}")
            : 'Unassigned';

        $type = $task->parent_id ? 'Subtask' : 'Task';

        $this->record(
            $task->project_id,
            'task_created',
            '',
            $task->title,
            "{$type} \"{$task->title}\" created and assigned to {$assigneeName}"
        );
    }

    public function updated(ProjectTask $task): void
    {
        $dirty = $task->getDirty();

        // Status change
        if (isset($dirty['status'])) {
            $oldStatus = $task->getOriginal('status');
            $newStatus = $dirty['status'];

            $this->record(
                $task->project_id,
                'task_status_changed',
                is_object($oldStatus) ? $oldStatus->value : (string) $oldStatus,
                is_object($newStatus) ? $newStatus->value : (string) $newStatus,
                "Task \"{$task->title}\" status changed"
            );
        }

        // Assignee change
        if (isset($dirty['assignee_id'])) {
            $oldId = $task->getOriginal('assignee_id');
            $newId = $dirty['assignee_id'];
            $oldName = $oldId ? (User::find($oldId)?->name ?? "User #{$oldId}") : 'Unassigned';
            $newName = $newId ? (User::find($newId)?->name ?? "User #{$newId}") : 'Unassigned';

            $this->record(
                $task->project_id,
                'task_assignee_changed',
                $oldName,
                $newName,
                "Task \"{$task->title}\" reassigned from {$oldName} to {$newName}"
            );
        }

        // Title change
        if (isset($dirty['title'])) {
            $this->record(
                $task->project_id,
                'task_title_changed',
                $task->getOriginal('title'),
                $dirty['title'],
                'Task title changed'
            );
        }
    }

    public function deleted(ProjectTask $task): void
    {
        $this->record(
            $task->project_id,
            'task_deleted',
            $task->title,
            '',
            "Task \"{$task->title}\" was deleted"
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
