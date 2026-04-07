<?php

namespace App\Enums;

enum QuotationStatus: string
{
    case DRAFT = 'draft';
    case SENT = 'sent';
    case VIEWED = 'viewed';
    case EXPIRED = 'expired';
    case NEGOTIATION = 'negotiation';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => __('enums.quotation_status.draft'),
            self::SENT => __('enums.quotation_status.sent'),
            self::VIEWED => __('enums.quotation_status.viewed'),
            self::EXPIRED => __('enums.quotation_status.expired'),
            self::NEGOTIATION => __('enums.quotation_status.negotiation'),
            self::APPROVED => __('enums.quotation_status.approved'),
            self::REJECTED => __('enums.quotation_status.rejected'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::DRAFT => 'gray',
            self::SENT => 'blue',
            self::VIEWED => 'cyan',
            self::EXPIRED => 'red',
            self::NEGOTIATION => 'yellow',
            self::APPROVED => 'green',
            self::REJECTED => 'red',
        };
    }
}
