<?php

namespace App\Enums;

enum ReminderType: string
{
    case BEFORE_EVENT = 'before_event';
    case AFTER_EVENT = 'after_event';
    case CUSTOM = 'custom';
    case EXPIRY_WARNING = 'expiry_warning';

    public function label(): string
    {
        return match($this) {
            self::BEFORE_EVENT => __('enums.reminder_type.before_event'),
            self::AFTER_EVENT => __('enums.reminder_type.after_event'),
            self::CUSTOM => __('enums.reminder_type.custom'),
            self::EXPIRY_WARNING => __('enums.reminder_type.expiry_warning'),
        };
    }
}
