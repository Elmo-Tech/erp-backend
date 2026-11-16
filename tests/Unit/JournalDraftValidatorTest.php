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
}

