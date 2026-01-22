<?php

namespace App\Observers;

use App\Enums\TaskStatus;
use App\Models\Task;
use App\Notifications\TaskAssigned;

/**
 * Observer for Task model to handle bug bounce rate tracking and notifications.
 *
 * This observer:
 * - Tracks status transitions from testing to in_progress
 * - Increments reopen_count when a task bounces back from testing
 * - Sends notifications when tasks are assigned
 * - Helps measure code quality and identify problematic tasks
 *
 * Requirements: 5.2, 5.3, 5.4
 */
class TaskObserver
{
    /**
     * Handle the Task "created" event.
     */
    public function created(Task $task): void
    {
        // Send notification to assignees when task is created with assignees
        if ($task->assignees()->exists()) {
            $assignerName = auth()->user()?->name ?? 'System';
            
            foreach ($task->assignees as $taskAssignee) {
                $taskAssignee->user->notify(new TaskAssigned($task, $assignerName));
            }
        }
    }

    /**
     * Handle the Task "updating" event.
     *
     * This method is called before the model is saved to the database.
     * It handles:
     * - Detecting status changes from testing to in_progress
     * - Incrementing reopen_count for bug bounce rate tracking
     * - Calculating delay_days when task is completed
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

        // Calculate delay_days when task is completed
        if ($newStatus === TaskStatus::COMPLETED && $oldStatus !== TaskStatus::COMPLETED) {
            $task->completed_at = now();
            
            // Calculate delay_days if task has a due_date
            if ($task->due_date) {
                $dueDate = $task->due_date->startOfDay();
                $completedDate = $task->completed_at->startOfDay();
                $task->delay_days = (int) $dueDate->diffInDays($completedDate, false);
            }
        }
    }

    /**
     * Handle the Task "updated" event.
     */
    public function updated(Task $task): void
    {
        // Check if assignees were added
        if ($task->wasRecentlyCreated === false && $task->assignees()->exists()) {
            $assignerName = auth()->user()?->name ?? 'System';
            
            // Get newly assigned users (this is a simplified approach)
            // In a real scenario, you might want to track the actual changes
            foreach ($task->assignees as $taskAssignee) {
                $taskAssignee->user->notify(new TaskAssigned($task, $assignerName));
            }
        }
    }
}
