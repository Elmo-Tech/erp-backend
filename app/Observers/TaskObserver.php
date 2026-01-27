<?php

namespace App\Observers;

use App\Enums\PointTransactionType;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Task;
use App\Notifications\TaskAssigned;
use App\Services\Points\UnifiedPointService;

/**
 * Observer for Task model to handle bug bounce rate tracking, notifications, and point awards.
 *
 * This observer:
 * - Tracks status transitions from testing to in_progress
 * - Increments reopen_count when a task bounces back from testing
 * - Sends notifications when tasks are assigned
 * - Awards points for on-time delivery only (MAIN and WEEKLY tasks only)
 * - Deducts points for late delivery based on delay days (MAIN and WEEKLY tasks only)
 * - Helps measure code quality and identify problematic tasks
 *
 * Requirements: 5.2, 5.3, 5.4
 */
class TaskObserver
{
    public function __construct(
        protected UnifiedPointService $pointService
    ) {}

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
     * - Awarding points for on-time delivery only
     * - Deducting points for late delivery (1 point per day late)
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

        // Handle task reopening (bug bounce) - deduct points
        if ($oldStatus === TaskStatus::TESTING && $newStatus === TaskStatus::IN_PROGRESS) {
            $task->reopen_count = ($task->reopen_count ?? 0) + 1;
            
            // Deduct points for bug bounce only from primary assignees
            if ($this->shouldAwardPoints($task)) {
                $primaryAssignees = $task->assignees()->where('is_primary', true)->get();
                foreach ($primaryAssignees as $assignee) {
                    $this->pointService->deductTaskReopenPoints($assignee->user, $task);
                }
            }
        }

        // Handle task completion - award/deduct points based on timing only
        if ($newStatus === TaskStatus::COMPLETED && $oldStatus !== TaskStatus::COMPLETED) {
            $task->completed_at = now();
            
            // Calculate delay_days if task has a due_date
            $isOnTime = false;
            $delayDays = 0;
            if ($task->due_date) {
                $dueDate = $task->due_date->startOfDay();
                $completedDate = $task->completed_at->startOfDay();
                $delayDays = (int) $dueDate->diffInDays($completedDate, false);
                $task->delay_days = $delayDays;
                
                // Task is on time if completed on or before due date
                $isOnTime = $delayDays <= 0;
            }
            
            // Award/deduct points based on timing only (no base completion points)
            if ($this->shouldAwardPoints($task)) {
                $primaryAssignees = $task->assignees()->where('is_primary', true)->get();
                foreach ($primaryAssignees as $assignee) {
                    $this->pointService->awardTaskCompletionPoints($assignee->user, $task, $isOnTime, $delayDays);
                }
            }
        }

        // Handle task being moved away from completed status
        if ($oldStatus === TaskStatus::COMPLETED && $newStatus !== TaskStatus::COMPLETED) {
            $task->completed_at = null;
            // Note: We keep delay_days for historical tracking
        }
    }

    /**
     * Determine if points should be awarded for this task.
     * Points are only awarded for MAIN and WEEKLY tasks, not for SUBTASKS.
     *
     * @param Task $task
     * @return bool
     */
    private function shouldAwardPoints(Task $task): bool
    {
        return in_array($task->type, [TaskType::MAIN, TaskType::WEEKLY]);
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
