<?php

namespace App\Enums;

enum ResourceType: string
{
    case BACKEND = 'backend';
    case FRONTEND = 'frontend';
    case UI_UX = 'ui_ux';
    case MOBILE = 'mobile';
    case DEVOPS = 'devops';
    case QA = 'qa';
    case PM = 'pm';

    public function label(): string
    {
        return match ($this) {
            self::BACKEND => __('enums.resource_type.backend'),
            self::FRONTEND => __('enums.resource_type.frontend'),
            self::UI_UX => __('enums.resource_type.ui_ux'),
            self::MOBILE => __('enums.resource_type.mobile'),
            self::DEVOPS => __('enums.resource_type.devops'),
            self::QA => __('enums.resource_type.qa'),
            self::PM => __('enums.resource_type.pm'),
        };
    }
}
