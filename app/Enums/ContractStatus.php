<?php

namespace App\Enums;

enum ContractStatus: string
{
    case DRAFT = 'draft';
    case PENDING_SIGNATURE = 'pending_signature';
    case SIGNED = 'signed';
    case ACTIVE = 'active';
    case COMPLETED = 'completed';
    case TERMINATED = 'terminated';

    public function label(): string
    {
        return match($this) {
            self::DRAFT => __('enums.contract_status.draft'),
            self::PENDING_SIGNATURE => __('enums.contract_status.pending_signature'),
            self::SIGNED => __('enums.contract_status.signed'),
            self::ACTIVE => __('enums.contract_status.active'),
            self::COMPLETED => __('enums.contract_status.completed'),
            self::TERMINATED => __('enums.contract_status.terminated'),
        };
    }

    public function color(): string
    {
        return match($this) {
            self::DRAFT => 'gray',
            self::PENDING_SIGNATURE => 'yellow',
            self::SIGNED => 'blue',
            self::ACTIVE => 'green',
            self::COMPLETED => 'purple',
            self::TERMINATED => 'red',
        };
    }
}
