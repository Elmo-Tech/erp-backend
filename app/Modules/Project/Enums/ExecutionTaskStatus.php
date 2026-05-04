<?php

namespace App\Modules\Project\Enums;

/**
 * Task status for the Project Execution layer.
 *
 * Intentionally namespaced under App\Modules\Project\Enums to avoid
 * collision with the existing App\Enums\TaskStatus used by the
 * App\Modules\Task module.
 */
enum ExecutionTaskStatus: string
{
    case BACKLOG    = 'backlog';
    case TO_DO      = 'to_do';
    case IN_PROGRESS = 'in_progress';
    case IN_REVIEW  = 'in_review';
    case QA         = 'qa';
    case DONE       = 'done';

    public function label(): string
    {
        return match ($this) {
            self::BACKLOG     => 'Backlog',
            self::TO_DO       => 'To Do',
            self::IN_PROGRESS => 'In Progress',
            self::IN_REVIEW   => 'In Review',
            self::QA          => 'QA',
            self::DONE        => 'Done',
        };
    }

    /**
     * Statuses that count as "active work" for time-tracking purposes.
     */
    public function isActive(): bool
    {
        return in_array($this, [self::IN_PROGRESS, self::IN_REVIEW, self::QA]);
    }

    public function canTransitionTo(self $target): bool
    {
        return $this !== $target;
    }

    public static function getAllStatuses(): array
    {
        return [
            self::BACKLOG,
            self::TO_DO,
            self::IN_PROGRESS,
            self::IN_REVIEW,
            self::QA,
            self::DONE,
        ];
    }
}
