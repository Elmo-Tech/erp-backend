<?php

namespace App\Enums;

enum DeliverableStatus: string
{
    case PENDING = 'pending';
    case SUBMITTED = 'submitted';
    case UNDER_REVIEW = 'under_review';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';

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

    public function canTransitionTo(self $newStatus): bool
    {
        return match ($this) {
            self::PENDING => $newStatus === self::SUBMITTED,
            self::SUBMITTED => in_array($newStatus, [self::UNDER_REVIEW, self::APPROVED, self::REJECTED]),
            self::UNDER_REVIEW => in_array($newStatus, [self::APPROVED, self::REJECTED]),
            self::APPROVED => false, // Final state
            self::REJECTED => $newStatus === self::SUBMITTED, // Can resubmit
        };
    }

    public static function getReviewableStatuses(): array
    {
        return [self::SUBMITTED, self::UNDER_REVIEW];
    }
}