<?php

namespace App\Enums;

enum MeetingStatus: string
{
    case SCHEDULED = 'scheduled';
    case IN_PROGRESS = 'in_progress';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
    case NO_SHOW = 'no_show';

    public function label(): string
    {
        return match($this) {
            self::SCHEDULED => __('enums.meeting_status.scheduled'),
            self::IN_PROGRESS => __('enums.meeting_status.in_progress'),
            self::COMPLETED => __('enums.meeting_status.completed'),
            self::CANCELLED => __('enums.meeting_status.cancelled'),
            self::NO_SHOW => __('enums.meeting_status.no_show'),
        };
    }

    public function color(): string
    {
        return match($this) {
            self::SCHEDULED => 'blue',
            self::IN_PROGRESS => 'yellow',
            self::COMPLETED => 'green',
            self::CANCELLED => 'gray',
            self::NO_SHOW => 'red',
        };
    }
}
