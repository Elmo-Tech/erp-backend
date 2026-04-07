<?php

namespace App\Enums;

enum MilestoneStatus: string
{
    case PENDING = 'pending';
    case DUE = 'due';
    case INVOICED = 'invoiced';
    case PAID = 'paid';
    case OVERDUE = 'overdue';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => __('enums.milestone_status.pending'),
            self::DUE => __('enums.milestone_status.due'),
            self::INVOICED => __('enums.milestone_status.invoiced'),
            self::PAID => __('enums.milestone_status.paid'),
            self::OVERDUE => __('enums.milestone_status.overdue'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDING => 'gray',
            self::DUE => 'yellow',
            self::INVOICED => 'blue',
            self::PAID => 'green',
            self::OVERDUE => 'red',
        };
    }
}
