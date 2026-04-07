<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    case DRAFT = 'draft';
    case SENT = 'sent';
    case PAID = 'paid';
    case OVERDUE = 'overdue';
    case CANCELLED = 'cancelled';

    /**
     * Get the human-readable label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::SENT => 'Sent',
            self::PAID => 'Paid',
            self::OVERDUE => 'Overdue',
            self::CANCELLED => 'Cancelled',
        };
    }

    /**
     * Check if the current status can transition to the target status.
     */
    public function canTransitionTo(InvoiceStatus $target): bool
    {
        return match ($this) {
            self::DRAFT => in_array($target, [self::SENT, self::CANCELLED]),
            self::SENT => in_array($target, [self::PAID, self::OVERDUE, self::CANCELLED]),
            self::OVERDUE => in_array($target, [self::PAID, self::CANCELLED]),
            self::PAID => false,
            self::CANCELLED => false,
        };
    }

    /**
     * Check if the invoice is paid.
     */
    public function isPaid(): bool
    {
        return $this === self::PAID;
    }

    /**
     * Check if the invoice is payable.
     */
    public function isPayable(): bool
    {
        return in_array($this, [self::SENT, self::OVERDUE]);
    }
}
