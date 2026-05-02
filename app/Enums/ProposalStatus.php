<?php

namespace App\Enums;

enum ProposalStatus: string
{
    case DRAFT    = 'draft';
    case SENT     = 'sent';
    case ACCEPTED = 'accepted';
    case REJECTED = 'rejected';
    case SKIPPED  = 'skipped';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT    => 'Draft',
            self::SENT     => 'Sent',
            self::ACCEPTED => 'Accepted',
            self::REJECTED => 'Rejected',
            self::SKIPPED  => 'Skipped',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
