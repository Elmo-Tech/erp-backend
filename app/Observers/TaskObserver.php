<?php

namespace App\Observers;

use App\Enums\TaskStatus;
use App\Models\Task;

/**
 * Observer for Task model to handle bug bounce rate tracking.
 *
 * This observer:
 * - Tracks status transitions from testing to in_progress
 * - Increments reopen_count when a task bounces back from testing
 * - Helps measure code quality and identify problematic tasks
 *
 * Requirements: 5.2, 5.3, 5.4
 */
class TaskObserver
{
    /**
     * Handle the Task "updating" event.
     *
     * This method is called before the model is saved to the database.
     * It handles:
     * - Detecting status changes from testing to in_progress
     * - Incrementing reopen_count for bug bounce rate tracking
     *
     * @param Task $task
     * @return void
     */
    public function updating(Task $task): void
    {
        // Only process if status is being changed
        if (!$task->isDirty('status')) {
            return;
        }

        $oldStatus = $task->getOriginal('status');
        $newStatus = $task->status;

        // Handle case where old status might be a string (from database)
        if (is_string($oldStatus)) {
            $oldStatus = TaskStatus::tryFrom($oldStatus);
        }

        // Increment reopen_count when moving from testing to in_progress
        // This tracks the "bug bounce rate" - how many times a task fails testing
        if ($oldStatus === TaskStatus::TESTING && $newStatus === TaskStatus::IN_PROGRESS) {
            $task->reopen_count = ($task->reopen_count ?? 0) + 1;
        }
    }
}
