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
     *
     * @return array<ProjectStatus>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::DRAFT => [self::ACTIVE],
            self::ACTIVE => [self::UI_UX, self::ON_HOLD, self::CANCELLED],
            self::UI_UX => [self::DEVELOPMENT, self::ON_HOLD, self::CANCELLED],
            self::DEVELOPMENT => [self::TESTING, self::ON_HOLD, self::CANCELLED],
            self::TESTING => [self::COMPLETED, self::DEVELOPMENT, self::ON_HOLD, self::CANCELLED],
            self::ON_HOLD => [], // Dynamic - returns to previous status
            self::COMPLETED => [],
            self::CANCELLED => [],
        };
    }

    /**
     * Check if transition to target status is allowed.
     */
    public function canTransitionTo(ProjectStatus $target): bool
    {
        // Same status is not a transition
        if ($this === $target) {
            return false;
        }

        // Cancelled can be reached from any status except completed
        if ($target === self::CANCELLED && $this !== self::COMPLETED) {
            return true;
        }

        return in_array($target, $this->allowedTransitions(), true);
    }
}
