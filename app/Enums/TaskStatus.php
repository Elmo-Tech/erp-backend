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
     *
     * @return array<TaskStatus>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::TODO => [self::IN_PROGRESS],
            self::IN_PROGRESS => [self::REVIEW, self::TODO],
            self::REVIEW => [self::TESTING, self::IN_PROGRESS],
            self::TESTING => [self::COMPLETED, self::IN_PROGRESS],
            self::COMPLETED => [],
        };
    }

    /**
     * Check if transition to target status is allowed.
     */
    public function canTransitionTo(TaskStatus $target): bool
    {
        // Same status is not a transition
        if ($this === $target) {
            return false;
        }

        return in_array($target, $this->allowedTransitions(), true);
    }
}
