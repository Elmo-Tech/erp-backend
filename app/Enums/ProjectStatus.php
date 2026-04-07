<?php

namespace App\Enums;

enum ProjectStatus: string
{
    case DRAFT = 'draft';
    case ACTIVE = 'active';
    case UI_UX = 'ui_ux';
    case DEVELOPMENT = 'development';
    case TESTING = 'testing';
    case COMPLETED = 'completed';
    case ON_HOLD = 'on_hold';
    case CANCELLED = 'cancelled';

    /**
     * Get the human-readable label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::ACTIVE => 'Active',
            self::UI_UX => 'UI/UX Phase',
            self::DEVELOPMENT => 'Development',
            self::TESTING => 'Testing',
            self::COMPLETED => 'Completed',
            self::ON_HOLD => 'On Hold',
            self::CANCELLED => 'Cancelled',
        };
    }

    /**
     * Get allowed transitions from this status.
     * Now allows flexible transitions to any status except same status.
     *
     * @return array<ProjectStatus>
     */
    public function allowedTransitions(): array
    {
        // Allow transition to any status except the current one
        $allStatuses = [
            self::DRAFT,
            self::ACTIVE,
            self::UI_UX,
            self::DEVELOPMENT,
            self::TESTING,
            self::COMPLETED,
            self::ON_HOLD,
            self::CANCELLED,
        ];

        return array_filter($allStatuses, fn ($status) => $status !== $this);
    }

    /**
     * Check if transition to target status is allowed.
     * Now allows flexible transitions - any status can transition to any other status.
     */
    public function canTransitionTo(ProjectStatus $target): bool
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
     * @return array<ProjectStatus>
     */
    public static function getAllStatuses(): array
    {
        return [
            self::DRAFT,
            self::ACTIVE,
            self::UI_UX,
            self::DEVELOPMENT,
            self::TESTING,
            self::COMPLETED,
            self::ON_HOLD,
            self::CANCELLED,
        ];
    }
}
