<?php

namespace App\Enums;

enum LeadSource: string
{
    case FACEBOOK = 'facebook';
    case GOOGLE = 'google';
    case LINKEDIN = 'linkedin';
    case REFERRAL = 'referral';
    case DIRECT = 'direct';
    case WEBSITE = 'website';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::FACEBOOK => __('enums.lead_source.facebook'),
            self::GOOGLE => __('enums.lead_source.google'),
            self::LINKEDIN => __('enums.lead_source.linkedin'),
            self::REFERRAL => __('enums.lead_source.referral'),
            self::DIRECT => __('enums.lead_source.direct'),
            self::WEBSITE => __('enums.lead_source.website'),
            self::OTHER => __('enums.lead_source.other'),
        };
    }
}
