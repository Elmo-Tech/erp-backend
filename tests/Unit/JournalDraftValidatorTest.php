<?php

namespace Tests\Unit;

use App\Domain\Accounting\JournalDraftValidator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class JournalDraftValidatorTest extends TestCase
{
    public function test_balanced_entry_passes(): void
    {
        (new JournalDraftValidator())->validate([
            ['debit' => '150.0000', 'credit' => '0'],
            ['debit' => '0', 'credit' => '150.0000'],
        ]);
        $this->assertTrue(true);
    }

    public function test_unbalanced_entry_fails(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new JournalDraftValidator())->validate([
            ['debit' => '150.0000', 'credit' => '0'],
            ['debit' => '0', 'credit' => '149.9900'],
        ]);
    }

    public function test_two_sided_line_fails(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new JournalDraftValidator())->validate([
            ['debit' => '20', 'credit' => '10'],
            ['debit' => '0', 'credit' => '10'],
        ]);
    }

    public function test_large_totals_differing_by_one_ten_thousandth_never_false_balance(): void
    {
        $lines = [];
        for ($i = 0; $i < 1000; $i++) {
            $lines[] = ['debit' => '999999999999.0000', 'credit' => '0'];
            $lines[] = ['debit' => '0', 'credit' => $i === 999 ? '999999999998.9999' : '999999999999.0000'];
        }
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Journal must be balanced');
        (new JournalDraftValidator())->validate($lines);
    }

    public function test_large_equal_totals_stay_exact_without_php_integer_overflow(): void
    {
        $lines = [];
        for ($i = 0; $i < 1000; $i++) {
            $lines[] = ['debit' => '999999999999.0000', 'credit' => '0'];
            $lines[] = ['debit' => '0', 'credit' => '999999999999.0000'];
        }
        (new JournalDraftValidator())->validate($lines);
        $this->assertCount(2000, $lines);
    }

    public function test_max_decimal_precision_is_accepted_and_excess_is_rejected(): void
    {
        (new JournalDraftValidator())->validate([
            ['debit' => '9999999999999999.9999', 'credit' => '0'],
            ['debit' => '0', 'credit' => '9999999999999999.9999'],
        ]);
        $this->expectException(InvalidArgumentException::class);
        (new JournalDraftValidator())->validate([
            ['debit' => '10000000000000000.0000', 'credit' => '0'],
            ['debit' => '0', 'credit' => '10000000000000000.0000'],
        ]);
    }

    public function test_float_input_is_rejected_before_precision_loss(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new JournalDraftValidator())->validate([
            ['debit' => 1.0001, 'credit' => '0'],
            ['debit' => '0', 'credit' => '1.0001'],
        ]);
    }

    public function test_zero_and_negative_amounts_are_rejected(): void
    {
        foreach ([
            [['debit' => '0.0000', 'credit' => '0'], ['debit' => '0', 'credit' => '1']],
            [['debit' => '-1', 'credit' => '0'], ['debit' => '0', 'credit' => '1']],
        ] as $lines) {
            try {
                (new JournalDraftValidator())->validate($lines);
                $this->fail('Expected invalid amounts to be rejected.');
            } catch (InvalidArgumentException) {
                $this->assertTrue(true);
            }
        }
    }
}
