<?php

namespace App\Enums;

enum ImpactLevel: string
{
    case LOW = 'low';
    case MEDIUM = 'medium';
    case HIGH = 'high';

    public function label(): string
    {
        return match ($this) {
            self::LOW => 'منخفض',
            self::MEDIUM => 'متوسط',
            self::HIGH => 'عالي',
        };
    }

    public function multiplier(): float
    {
        return match ($this) {
            self::LOW => 0.5,
            self::MEDIUM => 1.0,
            self::HIGH => 1.5,
        };
    }
}
