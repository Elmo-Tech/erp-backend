<?php

namespace App\Enums;

enum MeetingLocation: string
{
    case ONLINE = 'online';
    case OFFICE = 'office';
    case OUR_OFFICE = 'our_office';
    case CLIENT_SITE = 'client_site';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::ONLINE => __('enums.meeting_location.online'),
            self::OFFICE, self::OUR_OFFICE => __('enums.meeting_location.office'),
            self::CLIENT_SITE => __('enums.meeting_location.client_site'),
            self::OTHER => __('enums.meeting_location.other'),
        };
    }
}
