<?php

namespace App\Enums;

enum EvaluationStatus: string
{
    case PENDING = 'pending';
    case IN_PROGRESS = 'in_progress';
    case COMPLETED = 'completed';
    case APPROVED = 'approved';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'قيد الانتظار',
            self::IN_PROGRESS => 'جاري التقييم',
            self::COMPLETED => 'مكتمل',
            self::APPROVED => 'معتمد',
        };
    }
}
