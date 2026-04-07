<?php

namespace App\Enums;

enum IndustrySector: string
{
    case MEDICAL = 'medical';
    case ECOMMERCE = 'ecommerce';
    case REAL_ESTATE = 'real_estate';
    case SAAS = 'saas';
    case EDUCATION = 'education';
    case FINTECH = 'fintech';
    case LOGISTICS = 'logistics';
    case HOSPITALITY = 'hospitality';
    case MANUFACTURING = 'manufacturing';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::MEDICAL => __('enums.industry_sector.medical'),
            self::ECOMMERCE => __('enums.industry_sector.ecommerce'),
            self::REAL_ESTATE => __('enums.industry_sector.real_estate'),
            self::SAAS => __('enums.industry_sector.saas'),
            self::EDUCATION => __('enums.industry_sector.education'),
            self::FINTECH => __('enums.industry_sector.fintech'),
            self::LOGISTICS => __('enums.industry_sector.logistics'),
            self::HOSPITALITY => __('enums.industry_sector.hospitality'),
            self::MANUFACTURING => __('enums.industry_sector.manufacturing'),
            self::OTHER => __('enums.industry_sector.other'),
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::MEDICAL => '🏥',
            self::ECOMMERCE => '🛒',
            self::REAL_ESTATE => '🏢',
            self::SAAS => '☁️',
            self::EDUCATION => '📚',
            self::FINTECH => '💳',
            self::LOGISTICS => '🚚',
            self::HOSPITALITY => '🏨',
            self::MANUFACTURING => '🏭',
            self::OTHER => '📦',
        };
    }
}
