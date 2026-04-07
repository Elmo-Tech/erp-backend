<?php

namespace App\Enums;

enum ChangeRequestStatus: string
{
    case PENDING = 'pending';
    case UNDER_REVIEW = 'under_review';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case IMPLEMENTED = 'implemented';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => __('enums.change_request_status.pending'),
            self::UNDER_REVIEW => __('enums.change_request_status.under_review'),
            self::APPROVED => __('enums.change_request_status.approved'),
            self::REJECTED => __('enums.change_request_status.rejected'),
            self::IMPLEMENTED => __('enums.change_request_status.implemented'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDING => 'gray',
            self::UNDER_REVIEW => 'yellow',
            self::APPROVED => 'green',
            self::REJECTED => 'red',
            self::IMPLEMENTED => 'blue',
        };
    }
}
