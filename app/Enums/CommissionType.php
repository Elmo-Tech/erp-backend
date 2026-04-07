<?php

namespace App\Enums;

enum CommissionType: string
{
    case MARKUP = 'markup';
    case MARGIN = 'margin';

    public function label(): string
    {
        return match ($this) {
            self::MARKUP => __('enums.commission_type.markup'),
            self::MARGIN => __('enums.commission_type.margin'),
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::MARKUP => __('enums.commission_type.markup_desc'),
            self::MARGIN => __('enums.commission_type.margin_desc'),
        };
    }
}
