<?php

namespace App\Modules\Task\Actions;

use App\Enums\TaskStatus;
use App\Modules\User\Models\User;
use App\Modules\Task\Models\Task;
use App\Modules\Task\Models\TaskActivity;
use App\Modules\Task\Services\TaskNotificationService;

class UpdateTaskStatusAction
{
    public function __construct(
        private TaskNotificationService $taskNotificationService
    ) {}

    public function execute(Task $task, string $status, User $user, ?string $reason = null): Task
    {
        $oldStatus = $task->status;
        $newStatus = TaskStatus::from($status);

        if ($oldStatus === $newStatus) {
            return $task;
        }

        $task->update([
            'status' => $newStatus,
            'updated_by' => $user->id,
        ]);

        TaskActivity::logStatusChange(
            $task,
            $user,
            $oldStatus->value,
            $newStatus->value,
            $reason
        );

        $this->taskNotificationService->sendTaskStatusChanged(
            $task,
            $user,
            $oldStatus->value,
            $newStatus->value,
            $reason
        );

        $this->handleStatusTransition($task, $oldStatus, $newStatus, $user);

        return $task->fresh();
    }

    private function handleStatusTransition(Task $task, TaskStatus $oldStatus, TaskStatus $newStatus, User $user): void
    {
        if ($oldStatus === TaskStatus::TESTING && $newStatus === TaskStatus::IN_PROGRESS) {
            TaskActivity::logComment(
                $task,
                $user,
                'Task reopened from testing - bug bounce detected',
                true
            );
        }

        if ($newStatus === TaskStatus::COMPLETED && $oldStatus !== TaskStatus::COMPLETED) {
            $completedAt = now();
            $delayDays = null;

            if ($task->due_date) {
                $dueDate = $task->due_date->startOfDay();
                $completedDate = $completedAt->startOfDay();

                if ($completedDate->isAfter($dueDate)) {
                    $delayDays = (int) round($dueDate->diffInDays($completedDate));
                } elseif ($completedDate->isBefore($dueDate)) {
                    $delayDays = (int) -round($completedDate->diffInDays($dueDate));
                } else {
                    $delayDays = 0;
                }
            }

            $task->update([
                'completed_at' => $completedAt,
                'delay_days' => $delayDays,
            ]);

            $delayMessage = '';
            if ($delayDays !== null) {
                if ($delayDays > 0) {
                    $delayMessage = " ({$delayDays} days late)";
                } elseif ($delayDays < 0) {
                    $delayMessage = ' ('.abs($delayDays).' days early)';
                } else {
                    $delayMessage = ' (on time)';
                }
            }

            TaskActivity::logComment(
                $task,
                $user,
                "Task completed{$delayMessage}",
                false
            );
        }

        if ($oldStatus === TaskStatus::COMPLETED && $newStatus !== TaskStatus::COMPLETED) {
            $task->update(['completed_at' => null]);
        }
    }
}
