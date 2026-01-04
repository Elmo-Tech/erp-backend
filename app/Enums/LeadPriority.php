<?php

namespace App\Enums;

enum LeadPriority: string
{
    case LOW = 'low';
    case MEDIUM = 'medium';
    case HIGH = 'high';
    case URGENT = 'urgent';

    public function label(): string
    {
        return match($this) {
            self::LOW => __('enums.lead_priority.low'),
            self::MEDIUM => __('enums.lead_priority.medium'),
            self::HIGH => __('enums.lead_priority.high'),
            self::URGENT => __('enums.lead_priority.urgent'),
        };
    }

    public function color(): string
    {
        return match($this) {
            self::LOW => 'gray',
            self::MEDIUM => 'blue',
            self::HIGH => 'orange',
            self::URGENT => 'red',
        };
    }
}
