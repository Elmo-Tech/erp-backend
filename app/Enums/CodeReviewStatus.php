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
            self::PENDING => 'قيد المراجعة',
            self::APPROVED => 'موافق عليه',
            self::NEEDS_REVISION => 'يحتاج تعديل',
            self::REJECTED => 'مرفوض',
        };
    }
}
