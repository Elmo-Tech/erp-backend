<?php

namespace App\Enums;

enum DeliverableStatus: string
{
    case PENDING = 'pending';
    case SUBMITTED = 'submitted';
    case UNDER_REVIEW = 'under_review';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';

    /**
     * Get all status values
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get status label for display
     */
    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::SUBMITTED => 'Submitted',
            self::UNDER_REVIEW => 'Under Review',
            self::APPROVED => 'Approved',
            self::REJECTED => 'Rejected',
        };
    }

    /**
     * Get status color for UI
     */
    public function color(): string
    {
        return match ($this) {
            self::PENDING => 'gray',
            self::SUBMITTED => 'blue',
            self::UNDER_REVIEW => 'yellow',
            self::APPROVED => 'green',
            self::REJECTED => 'red',
        };
    }

    /**
     * Check if status allows submission
     */
    public function canSubmit(): bool
    {
        return $this === self::PENDING;
    }

    /**
     * Check if status allows review
     */
    public function canReview(): bool
    {
        return in_array($this, [self::SUBMITTED, self::UNDER_REVIEW]);
    }

    /**
     * Get valid transition statuses
     */
    public function validTransitions(): array
    {
        return match ($this) {
            self::PENDING => [self::SUBMITTED],
            self::SUBMITTED => [self::UNDER_REVIEW, self::APPROVED, self::REJECTED],
            self::UNDER_REVIEW => [self::APPROVED, self::REJECTED],
            self::APPROVED => [],
            self::REJECTED => [self::PENDING], // Allow resubmission after rejection
        };
    }

    /**
     * Check if transition to another status is valid
     */
    public function canTransitionTo(DeliverableStatus $status): bool
    {
        return in_array($status, $this->validTransitions());
    }
}
