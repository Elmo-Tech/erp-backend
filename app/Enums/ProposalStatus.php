<?php

namespace App\Enums;

enum ProposalStatus: string
{
    case DRAFT = 'draft';
    case SENT = 'sent';
    case VIEWED = 'viewed';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case EXPIRED = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => __('enums.proposal_status.draft'),
            self::SENT => __('enums.proposal_status.sent'),
            self::VIEWED => __('enums.proposal_status.viewed'),
            self::APPROVED => __('enums.proposal_status.approved'),
            self::REJECTED => __('enums.proposal_status.rejected'),
            self::EXPIRED => __('enums.proposal_status.expired'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::DRAFT => 'gray',
            self::SENT => 'blue',
            self::VIEWED => 'cyan',
            self::APPROVED => 'green',
            self::REJECTED => 'red',
            self::EXPIRED => 'orange',
        };
    }
}
