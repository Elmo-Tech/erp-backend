<?php

namespace App\Domain\Accounting;

use InvalidArgumentException;

/**
 * Exact draft-only validation; this never posts or changes a journal.
 * Decimal digits are accumulated as strings. No PHP int/float monetary totals.
 */
final class JournalDraftValidator
{
    /**
     * @param array<int, array{debit:string,credit:string}> $lines
     */
    public function validate(array $lines): void
    {
        if (count($lines) < 2) {
            throw new InvalidArgumentException('A journal needs at least two lines.');
        }

        $debits = '0';
        $credits = '0';

        foreach ($lines as $line) {
            foreach (['debit', 'credit'] as $side) {
                if (! isset($line[$side]) || ! is_string($line[$side])
                    || ! preg_match('/^\d+(?:\.\d{1,4})?$/D', $line[$side])) {
                    throw new InvalidArgumentException('Amounts must be decimal strings with up to four decimal places.');
                }
            }

            $debit = $this->toUnits($line['debit']);
            $credit = $this->toUnits($line['credit']);
            if (($debit !== '0') === ($credit !== '0')) {
                throw new InvalidArgumentException('Exactly one side must be positive.');
            }
            $debits = $this->addUnits($debits, $debit);
            $credits = $this->addUnits($credits, $credit);
        }

        if ($debits !== $credits || $debits === '0') {
            throw new InvalidArgumentException('Journal must be balanced and nonzero.');
        }
    }

    /** Convert DECIMAL(20,4) to canonical unsigned units without integer conversion. */
    private function toUnits(string $amount): string
    {
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');
        $whole = ltrim($whole, '0');
        $whole = $whole === '' ? '0' : $whole;

        if (strlen($whole) > 16) {
            throw new InvalidArgumentException('Amount exceeds DECIMAL(20,4) precision.');
        }

        $units = ltrim($whole.str_pad($fraction, 4, '0'), '0');

        return $units === '' ? '0' : $units;
    }

    /** Exact unbounded nonnegative integer addition, digit by digit. */
    private function addUnits(string $left, string $right): string
    {
        $i = strlen($left) - 1;
        $j = strlen($right) - 1;
        $carry = 0;
        $result = '';

        while ($i >= 0 || $j >= 0 || $carry !== 0) {
            $sum = $carry;
            if ($i >= 0) {
                $sum += ord($left[$i--]) - 48;
            }
            if ($j >= 0) {
                $sum += ord($right[$j--]) - 48;
            }
            $result = (string) ($sum % 10).$result;
            $carry = intdiv($sum, 10);
        }

        return $result;
    }
}
