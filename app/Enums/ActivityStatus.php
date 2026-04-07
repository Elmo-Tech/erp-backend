<?php

namespace App\Enums;

enum ActivityStatus: string
{
    case SCHEDULED = 'scheduled';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::SCHEDULED => __('enums.activity_status.scheduled'),
            self::COMPLETED => __('enums.activity_status.completed'),
            self::CANCELLED => __('enums.activity_status.cancelled'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::SCHEDULED => 'blue',
            self::COMPLETED => 'green',
            self::CANCELLED => 'gray',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::SCHEDULED => '📅',
            self::COMPLETED => '✅',
            self::CANCELLED => '❌',
        };
    }
}
