<?php

namespace App\Enums;

enum LeadStage: string
{
    case NEW_LEAD       = 'new_lead';
    case CONTACTED      = 'contacted';
    case MEETING        = 'meeting';
    case PROPOSAL       = 'proposal';
    case QUOTATION      = 'quotation';
    case CONTRACT       = 'contract';
    case WON            = 'won';
    case LOST           = 'lost';

    public function label(): string
    {
        return match ($this) {
            self::NEW_LEAD   => 'New Lead',
            self::CONTACTED  => 'Contacted',
            self::MEETING    => 'Meeting',
            self::PROPOSAL   => 'Proposal',
            self::QUOTATION  => 'Quotation',
            self::CONTRACT   => 'Contract',
            self::WON        => 'Won',
            self::LOST       => 'Lost',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
