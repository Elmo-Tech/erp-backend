<?php

namespace App\Enums;

enum ActivityType: string
{
    case CALL = 'call';
    case EMAIL = 'email';
    case MEETING = 'meeting';
    case NOTE = 'note';
    case STATUS_CHANGE = 'status_change';
    case TECHNICAL_DISCOVERY = 'technical_discovery';

    public function label(): string
    {
        return match($this) {
            self::CALL => __('enums.activity_type.call'),
            self::EMAIL => __('enums.activity_type.email'),
            self::MEETING => __('enums.activity_type.meeting'),
            self::NOTE => __('enums.activity_type.note'),
            self::STATUS_CHANGE => __('enums.activity_type.status_change'),
            self::TECHNICAL_DISCOVERY => __('enums.activity_type.technical_discovery'),
        };
    }

    public function icon(): string
    {
        return match($this) {
            self::CALL => 'phone',
            self::EMAIL => 'mail',
            self::MEETING => 'calendar',
            self::NOTE => 'file-text',
            self::STATUS_CHANGE => 'refresh-cw',
            self::TECHNICAL_DISCOVERY => 'search',
        };
    }
}
