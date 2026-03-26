<?php

namespace App\Enums;

enum ProposalActionType: string
{
    case SKIP = 'skip';
    case SCHEDULE = 'schedule';
    case UPLOAD = 'upload';
    case DRAFT = 'draft';

    /**
     * Get all values as array
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Check if value is valid
     */
    public static function isValid(string $value): bool
    {
        return in_array($value, self::values(), true);
    }
}
