<?php

namespace App\Enums;

enum TemplateType: string
{
    case SAAS = 'saas';
    case CUSTOM = 'custom';
    case MOBILE = 'mobile';
    case MAINTENANCE = 'maintenance';
    case CONSULTATION = 'consultation';

    public function label(): string
    {
        return match ($this) {
            self::SAAS => __('enums.template_type.saas'),
            self::CUSTOM => __('enums.template_type.custom'),
            self::MOBILE => __('enums.template_type.mobile'),
            self::MAINTENANCE => __('enums.template_type.maintenance'),
            self::CONSULTATION => __('enums.template_type.consultation'),
        };
    }
}
