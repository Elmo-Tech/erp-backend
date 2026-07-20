<?php

namespace App\Enums;

enum ChangeRequestStatus: string
{
    case DRAFT = 'draft';
    case SENT_TO_CLIENT = 'sent_to_client';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::SENT_TO_CLIENT => 'Sent to Client',
            self::APPROVED => 'Approved',
            self::REJECTED => 'Rejected',
        };
    }

    public static function getAllStatuses(): array
    {
        return [
            self::DRAFT,
            self::SENT_TO_CLIENT,
            self::APPROVED,
            self::REJECTED,
        ];
    }
}
