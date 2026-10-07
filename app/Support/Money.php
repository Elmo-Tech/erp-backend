<?php

namespace App\Support;

class Money
{
    public static function toCents(mixed $amount): int
    {
        $amount = (string) $amount;

        if (preg_match('/^(\d+)(?:\.(\d{1,2}))?$/', $amount, $matches) === 1) {
            return ((int) $matches[1] * 100) + (int) str_pad($matches[2] ?? '', 2, '0');
        }

        return (int) round((float) $amount * 100);
    }

    public static function fromCents(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }

    public static function format(mixed $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }
}
