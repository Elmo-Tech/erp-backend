<?php

namespace App\Enums;

// erp-backend/app/Enums/TaskPriority.php
enum TaskPriority: string
{
    case LOW = 'low';
    case MEDIUM = 'medium';
    case HIGH = 'high';
    case URGENT = 'urgent';

    public function label(): string
    {
        return match ($this) {
            self::LOW => 'Low',
            self::MEDIUM => 'Medium',
            self::HIGH => 'High',
            self::URGENT => 'Urgent',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::LOW => '#6b7280',
            self::MEDIUM => '#3b82f6',
            self::HIGH => '#f59e0b',
            self::URGENT => '#ef4444',
        };
    }

    public function order(): int
    {
        return match ($this) {
            self::LOW => 1,
            self::MEDIUM => 2,
            self::HIGH => 3,
            self::URGENT => 4,
        };
    }
}
