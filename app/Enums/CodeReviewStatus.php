<?php

namespace App\Enums;

enum CodeReviewStatus: string
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case NEEDS_REVISION = 'needs_revision';
    case REJECTED = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::APPROVED => 'Approved',
            self::NEEDS_REVISION => 'Needs Revision',
            self::REJECTED => 'Rejected',
        };
    }
}
