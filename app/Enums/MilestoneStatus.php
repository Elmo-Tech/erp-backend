<?php

namespace App\Enums;

enum MilestoneStatus: string
{
    case PENDING   = 'pending';
    case ACTIVE    = 'active';
    case COMPLETED = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::PENDING   => 'Pending',
            self::ACTIVE    => 'Active',
            self::COMPLETED => 'Completed',
        };
    }

    public static function getAllStatuses(): array
    {
        return [self::PENDING, self::ACTIVE, self::COMPLETED];
    }
}
