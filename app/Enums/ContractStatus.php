<?php

namespace App\Enums;

enum ContractStatus: string
{
    case DRAFT = 'draft';
    case SENT = 'sent';
    case SIGNED = 'signed';
    case REJECTED = 'rejected';
    case SKIPPED = 'skipped';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::SENT => 'Sent',
            self::SIGNED => 'Signed',
            self::REJECTED => 'Rejected',
            self::SKIPPED => 'Skipped',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
