<?php

namespace App\Domain\Accounting;

use InvalidArgumentException;

/**
 * Validation boundary for a draft journal; NOT a posting service.
 * Posting must additionally lock the period, validate accounts, and persist atomically.
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

        $debits = 0;
        $credits = 0;

        foreach ($lines as $line) {
            foreach (['debit', 'credit'] as $key) {
                if (! preg_match('/^\d+(?:\.\d{1,4})?$/', $line[$key] ?? '')) {
                    throw new InvalidArgumentException('Amounts must have up to four decimal places.');
                }
            }

            $d = $this->toUnits($line['debit']);
            $c = $this->toUnits($line['credit']);
            if (($d > 0) === ($c > 0)) {
                throw new InvalidArgumentException('Exactly one side must be positive.');
            }
            $debits += $d;
            $credits += $c;
        }

        if ($debits !== $credits || $debits <= 0) {
            throw new InvalidArgumentException('Journal must be balanced and nonzero.');
        }
    }

    private function toUnits(string $amount): int
    {
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');
        if (strlen($whole) > 12) {
            throw new InvalidArgumentException('Amount exceeds allowed safe integer validation range.');
        }

        return ((int) $whole * 10000) + (int) str_pad($fraction, 4, '0');
    }
}

