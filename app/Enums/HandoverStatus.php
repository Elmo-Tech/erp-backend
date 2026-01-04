<?php

namespace App\Enums;

enum HandoverStatus: string
{
    case PENDING = 'pending';
    case COMPLETED = 'completed';
    case DISPUTED = 'disputed';

    public function label(): string
    {
        return match($this) {
            self::PENDING => __('enums.handover_status.pending'),
            self::COMPLETED => __('enums.handover_status.completed'),
            self::DISPUTED => __('enums.handover_status.disputed'),
        };
    }

    public function color(): string
    {
        return match($this) {
            self::PENDING => 'yellow',
            self::COMPLETED => 'green',
            self::DISPUTED => 'red',
        };
    }
}
