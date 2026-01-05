<?php

namespace App\Enums;

enum BonusTier: string
{
    case EXCELLENT = 'excellent';
    case GOOD = 'good';
    case AVERAGE = 'average';
    case POOR = 'poor';

    public function label(): string
    {
        return match ($this) {
            self::EXCELLENT => 'Excellent',
            self::GOOD => 'Good',
            self::AVERAGE => 'Average',
            self::POOR => 'Poor',
        };
    }

    public function percentage(): int
    {
        return match ($this) {
            self::EXCELLENT => 100,
            self::GOOD => 75,
            self::AVERAGE => 50,
            self::POOR => 0,
        };
    }

    public function minScore(): int
    {
        return match ($this) {
            self::EXCELLENT => 90,
            self::GOOD => 80,
            self::AVERAGE => 60,
            self::POOR => 0,
        };
    }

    public static function fromScore(float $score): self
    {
        return match (true) {
            $score >= 90 => self::EXCELLENT,
            $score >= 80 => self::GOOD,
            $score >= 60 => self::AVERAGE,
            default => self::POOR,
        };
    }
}
