<?php

namespace App\Enums;

enum TaskStatus: string
{
    case TODO = 'todo';
    case IN_PROGRESS = 'in_progress';
    case REVIEW = 'review';
    case TESTING = 'testing';
    case COMPLETED = 'completed';

    /**
     * Get the human-readable label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::TODO => 'To Do',
            self::IN_PROGRESS => 'In Progress',
            self::REVIEW => 'Review',
            self::TESTING => 'Testing',
            self::COMPLETED => 'Completed',
        };
    }

    /**
     * Get allowed transitions from this status.
     * Now allows flexible transitions to any status except same status.
     *
     * @return array<TaskStatus>
     */
    public function allowedTransitions(): array
    {
        // Allow transition to any status except the current one
        $allStatuses = [
            self::TODO,
            self::IN_PROGRESS,
            self::REVIEW,
            self::TESTING,
            self::COMPLETED,
        ];

        return array_filter($allStatuses, fn ($status) => $status !== $this);
    }

    /**
     * Check if transition to target status is allowed.
     * Now allows flexible transitions - any status can transition to any other status.
     */
    public function canTransitionTo(TaskStatus $target): bool
    {
        // Same status is not a transition
        if ($this === $target) {
            return false;
        }

        // Allow transition to any other status
        return true;
    }

    /**
     * Get all available statuses for dropdown/selection.
     *
     * @return array<TaskStatus>
     */
    public static function getAllStatuses(): array
    {
        return [
            self::TODO,
            self::IN_PROGRESS,
            self::REVIEW,
            self::TESTING,
            self::COMPLETED,
        ];
    }

    /**
     * Check if the status is completed.
     */
    public function isCompleted(): bool
    {
        return $this === self::COMPLETED;
    }
}
