<?php

namespace App\Enums;

enum ContractTemplateType: string
{
    case DEVELOPMENT = 'development';
    case MAINTENANCE = 'maintenance';
    case SLA = 'sla';
    case NDA = 'nda';
    case CONSULTATION = 'consultation';

    public function label(): string
    {
        return match ($this) {
            self::DEVELOPMENT => __('enums.contract_template_type.development'),
            self::MAINTENANCE => __('enums.contract_template_type.maintenance'),
            self::SLA => __('enums.contract_template_type.sla'),
            self::NDA => __('enums.contract_template_type.nda'),
            self::CONSULTATION => __('enums.contract_template_type.consultation'),
        };
    }
}
