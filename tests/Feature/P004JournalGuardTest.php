<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class P004JournalGuardTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{company:string,period:string,account:string,entry:string} */
    private function draftFixture(): array
    {
        DB::table('currencies')->updateOrInsert(['code' => 'EGP'], [
            'name' => 'Synthetic Egyptian Pound',
        ]);
        $company = (string) Str::uuid7();
        $fiscal = (string) Str::uuid7();
        $period = (string) Str::uuid7();
        $account = (string) Str::uuid7();
        $entry = (string) Str::uuid7();
        $time = now();

        DB::table('companies')->insert([
            'id' => $company, 'code' => 'P004-EG', 'legal_name' => 'P004 Synthetic Egypt',
            'country_code' => 'EG', 'base_currency_code' => 'EGP', 'timezone' => 'UTC',
            'status' => 'active', 'created_at' => $time, 'updated_at' => $time,
        ]);
        DB::table('fiscal_years')->insert([
            'id' => $fiscal, 'company_id' => $company, 'label' => 'P004-FY',
            'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31', 'state' => 'open',
            'created_at' => $time, 'updated_at' => $time,
        ]);
        DB::table('accounting_periods')->insert([
            'id' => $period, 'company_id' => $company, 'fiscal_year_id' => $fiscal,
            'starts_on' => '2026-01-01', 'ends_on' => '2026-03-31', 'state' => 'open',
            'created_at' => $time, 'updated_at' => $time,
        ]);
        DB::table('ledger_accounts')->insert([
            'id' => $account, 'company_id' => $company, 'code' => 'CASH',
            'name' => 'Synthetic cash', 'account_type' => 'asset',
            'normal_balance' => 'debit', 'active' => true,
            'created_at' => $time, 'updated_at' => $time,
        ]);
        DB::table('journal_entries')->insert([
            'id' => $entry, 'company_id' => $company, 'period_id' => $period,
            'entry_date' => '2026-02-01', 'status' => 'draft',
            'created_at' => $time, 'updated_at' => $time,
        ]);
        return compact('company', 'period', 'account', 'entry');
    }

    private function line(array $fixture, int $number, string $debit, string $credit): array
    {
        return [
            'id' => (string) Str::uuid7(),
            'company_id' => $fixture['company'],
            'entry_id' => $fixture['entry'],
            'account_id' => $fixture['account'],
            'line_no' => $number, 'debit_base' => $debit, 'credit_base' => $credit,
        ];
    }

    private function rejected(callable $operation): void
    {
        try {
            DB::transaction($operation);
            $this->fail('Expected PostgreSQL to reject an unauthorized journal mutation.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('23514', $e->getMessage());
        }
    }

    /** Test fixture simulates a journal that was posted before P0-04A existed. */
    private function markAsHistoricalPosted(string $entryId): void
    {
        // Test database/migration-owner ONLY. Never disable this trigger on live data.
        $this->assertSame('testing', app()->environment());
        DB::statement('ALTER TABLE public.journal_entries DISABLE TRIGGER p004_guard_journal_entries');
        try {
            DB::table('journal_entries')->where('id', $entryId)->update([
                'status' => 'posted', 'posted_at' => now(),
            ]);
        } finally {
            DB::statement('ALTER TABLE public.journal_entries ENABLE TRIGGER p004_guard_journal_entries');
        }
    }

    public function test_new_posting_and_reversing_are_denied_until_reviewed_service_exists(): void
    {
        $fixture = $this->draftFixture();

        $this->rejected(fn () => DB::table('journal_entries')
            ->where('id', $fixture['entry'])->update(['status' => 'posted', 'posted_at' => now()]));
        $this->rejected(fn () => DB::table('journal_entries')
            ->where('id', $fixture['entry'])->update(['status' => 'reversed', 'posted_at' => now()]));
        $this->rejected(fn () => DB::table('journal_entries')->insert([
            'id' => (string) Str::uuid7(), 'company_id' => $fixture['company'],
            'period_id' => $fixture['period'], 'entry_date' => '2026-02-02',
            'status' => 'posted', 'posted_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]));

        $this->assertSame('draft', DB::table('journal_entries')
            ->where('id', $fixture['entry'])->value('status'));
    }

    public function test_posted_historical_headers_and_lines_are_frozen_without_breaking_drafts(): void
    {
        $fixture = $this->draftFixture();
        $debit = $this->line($fixture, 1, '10.0000', '0');
        $credit = $this->line($fixture, 2, '0', '10.0000');
        DB::table('journal_lines')->insert([$debit, $credit]);

        DB::table('journal_lines')->where('id', $debit['id'])
            ->update(['debit_base' => '12.0000']);
        DB::table('journal_lines')->where('id', $debit['id'])
            ->update(['debit_base' => '10.0000']);
        $this->markAsHistoricalPosted($fixture['entry']);

        $this->rejected(fn () => DB::table('journal_entries')
            ->where('id', $fixture['entry'])->update(['description' => 'modified']));
        $this->rejected(fn () => DB::table('journal_entries')
            ->where('id', $fixture['entry'])->delete());
        $this->rejected(fn () => DB::table('journal_lines')
            ->where('id', $debit['id'])->update(['debit_base' => '11.0000']));
        $this->rejected(fn () => DB::table('journal_lines')
            ->where('id', $credit['id'])->delete());
        $this->rejected(fn () => DB::table('journal_lines')
            ->insert($this->line($fixture, 3, '5.0000', '0')));

        $this->assertSame(2, DB::table('journal_lines')
            ->where('entry_id', $fixture['entry'])->count());
        $this->assertSame('posted', DB::table('journal_entries')
            ->where('id', $fixture['entry'])->value('status'));
    }

    public function test_cross_entry_line_reparenting_into_posted_header_is_denied(): void
    {
        $first = $this->draftFixture();
        $second = (string) Str::uuid7();
        DB::table('journal_entries')->insert([
            'id' => $second, 'company_id' => $first['company'],
            'period_id' => $first['period'], 'entry_date' => '2026-02-02',
            'status' => 'draft', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $line = $this->line($first, 1, '5.0000', '0');
        DB::table('journal_lines')->insert($line);
        $this->markAsHistoricalPosted($second);

        $this->rejected(fn () => DB::table('journal_lines')->where('id', $line['id'])
            ->update(['entry_id' => $second]));
        $this->assertSame($first['entry'], DB::table('journal_lines')
            ->where('id', $line['id'])->value('entry_id'));
    }
}
