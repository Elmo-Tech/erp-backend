<?php

namespace App\Services\Tasks;

use App\Modules\Task\Models\Task;
use App\Models\User;
use App\Notifications\TaskAssigned;
use App\Notifications\TaskCommentAdded;
use App\Notifications\TaskStatusChanged;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

class TaskNotificationService
{
    /**
     * Notify assigned users and configured observers about a task assignment event.
     *
     * @param  Collection<int, User>  $assignedUsers
     */
    public function sendTaskAssigned(Task $task, User $assigner, Collection $assignedUsers): void
    {
        $recipients = $assignedUsers
            ->merge($this->observerRecipients($task))
            ->filter(fn ($user) => $user instanceof User && $user->id !== $assigner->id)
            ->unique('id')
            ->values();

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send(
            $recipients,
            new TaskAssigned($task->loadMissing(['project', 'assignees.user']), $assigner->name, $assigner->id)
        );
    }

    public function sendTaskStatusChanged(Task $task, User $changedBy, string $oldStatus, string $newStatus, ?string $comment = null): void
    {
        $recipients = $this->updateRecipients($task, [$changedBy->id]);

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send(
            $recipients,
            new TaskStatusChanged($task->loadMissing('project'), $oldStatus, $newStatus, $changedBy->name, $changedBy->id, $comment)
        );
    }

    public function sendTaskCommentAdded(Task $task, User $commenter, string $comment, bool $isInternal = false): void
    {
        if ($isInternal) {
            return;
        }

        $recipients = $this->updateRecipients($task, [$commenter->id]);

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send(
            $recipients,
            new TaskCommentAdded($task->loadMissing('project'), $commenter->name, $comment, $commenter->id)
        );
    }

    /**
     * @param  array<int, int>  $excludeUserIds
     * @return Collection<int, User>
     */
    private function updateRecipients(Task $task, array $excludeUserIds = []): Collection
    {
        return $this->taskAssigneeUsers($task)
            ->merge($this->observerRecipients($task))
            ->filter(fn ($user) => $user instanceof User && ! in_array($user->id, $excludeUserIds, true))
            ->unique('id')
            ->values();
    }

    /**
     * @return Collection<int, User>
     */
    private function observerRecipients(Task $task): Collection
    {
        $task->loadMissing('createdBy');

        $recipients = collect();

        if (config('tasks.notifications.include_creator') && $task->createdBy) {
            $recipients->push($task->createdBy);
        }

        return $recipients
            ->merge($this->configuredUsers())
            ->filter(fn ($user) => $user instanceof User)
            ->unique('id')
            ->values();
    }

    /**
     * @return Collection<int, User>
     */
    private function taskAssigneeUsers(Task $task): Collection
    {
        $task->loadMissing('assignees.user');

        return $task->assignees
            ->map(fn ($assignee) => $assignee->user)
            ->filter(fn ($user) => $user instanceof User)
            ->values();
    }

    /**
     * @return Collection<int, User>
     */
    private function configuredUsers(): Collection
    {
        $emails = config('tasks.notifications.extra_user_emails', []);

        if (empty($emails)) {
            return collect();
        }

        return User::query()
            ->whereIn('email', $emails)
            ->get();
    }
}
