<?php

namespace App\Enums;

enum MeetingLocation: string
{
    case ONLINE = 'online';
    case OFFICE = 'office';
    case CLIENT_SITE = 'client_site';

    public function label(): string
    {
        return match ($this) {
            self::ONLINE => __('enums.meeting_location.online'),
            self::OFFICE => __('enums.meeting_location.office'),
            self::CLIENT_SITE => __('enums.meeting_location.client_site'),
        };
    }
}
