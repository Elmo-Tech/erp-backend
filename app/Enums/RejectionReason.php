<?php

namespace App\Enums;

enum RejectionReason: string
{
    case PRICE = 'price';
    case TIMELINE = 'timeline';
    case COMPETITOR = 'competitor';
    case BUDGET = 'budget';
    case SCOPE = 'scope';
    case OTHER = 'other';

    public function label(): string
    {
        return match($this) {
            self::PRICE => __('enums.rejection_reason.price'),
            self::TIMELINE => __('enums.rejection_reason.timeline'),
            self::COMPETITOR => __('enums.rejection_reason.competitor'),
            self::BUDGET => __('enums.rejection_reason.budget'),
            self::SCOPE => __('enums.rejection_reason.scope'),
            self::OTHER => __('enums.rejection_reason.other'),
        };
    }
}
