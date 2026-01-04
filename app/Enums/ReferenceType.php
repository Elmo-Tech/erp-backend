<?php

namespace App\Enums;

enum ReferenceType: string
{
    case SALES_REP = 'sales_rep';
    case CLIENT_REFERRAL = 'client_referral';
    case EMPLOYEE = 'employee';

    public function label(): string
    {
        return match($this) {
            self::SALES_REP => __('enums.reference_type.sales_rep'),
            self::CLIENT_REFERRAL => __('enums.reference_type.client_referral'),
            self::EMPLOYEE => __('enums.reference_type.employee'),
        };
    }
}
