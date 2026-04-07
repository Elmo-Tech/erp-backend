<?php

namespace App\Observers;

use App\Models\Task;
use App\Models\TimeEntry;

/**
 * Observer for TimeEntry model to handle bubble-up time aggregation.
 *
 * This observer:
 * - Recalculates total_time_spent for tasks when time entries are created/updated/deleted
 * - Bubbles up the time aggregation to all ancestor tasks
 * - Uses iterative approach to avoid stack overflow with deep hierarchies
 *
 * Requirements: 2.1, 2.2, 2.3, 2.4, 2.5
 */
class TimeEntryObserver
{
    /**
     * Handle the TimeEntry "saved" event (covers both created and updated).
     *
     * When a time entry is created or updated for a Task, recalculate
     * the total_time_spent for that task and all its ancestors.
     */
    public function saved(TimeEntry $timeEntry): void
    {
        // Only process if the trackable is a Task
        if ($timeEntry->trackable_type === Task::class) {
            $this->recalculateTaskTime($timeEntry->trackable_id);
        }
    }

    /**
     * Handle the TimeEntry "deleted" event.
     *
     * When a time entry is deleted from a Task, recalculate
     * the total_time_spent for that task and all its ancestors.
     */
    public function deleted(TimeEntry $timeEntry): void
    {
        // Only process if the trackable is a Task
        if ($timeEntry->trackable_type === Task::class) {
            $this->recalculateTaskTime($timeEntry->trackable_id);
        }
    }

    /**
     * Recalculate time for a task and bubble up to all ancestors.
     *
     * Uses an iterative approach to avoid stack overflow with deeply nested hierarchies.
     * The algorithm:
     * 1. Collect the task and all its ancestors
     * 2. Update from leaf to root (bottom-up)
     * 3. Each task's total_time_spent = direct time entries + sum of children's total_time_spent
     */
    private function recalculateTaskTime(int $taskId): void
    {
        $task = Task::find($taskId);
        if (! $task) {
            return;
        }

        // Collect all tasks that need recalculation (task + ancestors)
        // We need to update from the leaf task up to the root
        $tasksToUpdate = collect([$task]);
        $current = $task;

        // Traverse up to collect all ancestors
        while ($current->parent_id !== null) {
            $parent = Task::find($current->parent_id);
            if ($parent) {
                $tasksToUpdate->push($parent);
                $current = $parent;
            } else {
                break;
            }
        }

        // Update from leaf to root (the order in collection is already leaf-first)
        // Each task's total_time_spent = direct time + children's total_time_spent
        foreach ($tasksToUpdate as $taskToUpdate) {
            $directTime = $taskToUpdate->getDirectTimeSpent();
            $childrenTime = (int) $taskToUpdate->children()->sum('total_time_spent');

            // Use update without triggering observers to avoid infinite loops
            Task::withoutEvents(function () use ($taskToUpdate, $directTime, $childrenTime) {
                $taskToUpdate->update([
                    'total_time_spent' => $directTime + $childrenTime,
                ]);
            });
        }
    }
}
