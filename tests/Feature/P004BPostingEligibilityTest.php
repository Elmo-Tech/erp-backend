<?php

namespace Tests\Feature;

use App\Domain\Accounting\JournalPostingEligibilityValidator;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

final class P004BPostingEligibilityTest extends TestCase
{
    use RefreshDatabase;

    private function uuid(): string
    {
        return (string) Str::uuid7();
    }

    /** @return array{company:string,fiscal:string,period:string,debit:string,credit:string,journal:string} */
    private function seedLedger(string $country = 'EG'): array
    {
        foreach (['EGP' => 'Egyptian Pound', 'EUR' => 'Euro'] as $code => $name) {
            DB::table('currencies')->insertOrIgnore(['code' => $code, 'name' => $name]);
        }

        $company = $this->uuid();
        $fiscal = $this->uuid();
        $period = $this->uuid();
        $debit = $this->uuid();
        $credit = $this->uuid();
        $journal = $this->uuid();
        DB::table('companies')->insert([
            'id' => $company, 'code' => 'P004B-'.$country.'-'.substr($company, 0, 8),
            'legal_name' => 'Synthetic '.$country, 'country_code' => $country,
            'base_currency_code' => $country === 'EG' ? 'EGP' : 'EUR',
            'timezone' => 'UTC', 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('fiscal_years')->insert([
            'id' => $fiscal, 'company_id' => $company, 'label' => 'FY-2026',
            'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31',
            'state' => 'open', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('accounting_periods')->insert([
            'id' => $period, 'company_id' => $company, 'fiscal_year_id' => $fiscal,
            'starts_on' => '2026-01-01', 'ends_on' => '2026-03-31',
            'state' => 'open', 'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach ([$debit => 'DEBIT', $credit => 'CREDIT'] as $id => $code) {
            DB::table('ledger_accounts')->insert([
                'id' => $id, 'company_id' => $company, 'code' => $code,
                'name' => $code, 'account_type' => 'asset', 'normal_balance' => 'debit',
                'active' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        DB::table('journal_entries')->insert([
            'id' => $journal, 'company_id' => $company, 'period_id' => $period,
            'entry_date' => '2026-02-10', 'status' => 'draft',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return compact('company', 'fiscal', 'period', 'debit', 'credit', 'journal');
    }

    private function addLine(array $seed, int $number, string $debit, string $credit, array $extra = []): void
    {
        DB::table('journal_lines')->insert(array_merge([
            'id' => $this->uuid(), 'company_id' => $seed['company'],
            'entry_id' => $seed['journal'],
            'account_id' => $credit === '0' ? $seed['debit'] : $seed['credit'],
            'line_no' => $number, 'debit_base' => $debit, 'credit_base' => $credit,
        ], $extra));
    }

    private function balanced(array $seed): void
    {
        $this->addLine($seed, 1, '10.0000', '0');
        $this->addLine($seed, 2, '0', '10.0000');
    }

    private function validate(array $seed, ?string $companyId = null): void
    {
        (new JournalPostingEligibilityValidator())->assertEligible($companyId ?? $seed['company'], $seed['journal']);
    }

    private function rejects(callable $callback, string $message): void
    {
        try {
            $callback();
            $this->fail('Expected rejection: '.$message);
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString($message, $exception->getMessage());
        }
    }

    public function test_balanced_draft_is_read_only_and_repeatable_but_never_posts(): void
    {
        $s = $this->seedLedger();
        $this->balanced($s);
        $this->validate($s);
        $this->validate($s); // Repeated check is not a duplicate posting command.
        $entry = DB::table('journal_entries')->where('id', $s['journal'])->first();
        $this->assertSame('draft', $entry->status);
        $this->assertNull($entry->posted_at);
        $this->assertSame(2, DB::table('journal_lines')->where('entry_id', $s['journal'])->count());
        $this->assertSame(0, DB::table('audit_logs')->count());
        $this->assertSame(0, DB::table('outbox_events')->count());
        $this->assertSame(0, DB::table('idempotency_requests')->count());
    }

    public function test_missing_single_and_unbalanced_lines_are_rejected(): void
    {
        $s = $this->seedLedger();
        $this->rejects(fn () => $this->validate($s), 'Journal lines');
        $this->addLine($s, 1, '10.0000', '0');
        $this->rejects(fn () => $this->validate($s), 'Journal lines');
        $this->addLine($s, 2, '0', '9.9999');
        $this->rejects(fn () => $this->validate($s), 'Journal lines');
        DB::table('journal_lines')->where('entry_id', $s['journal'])
            ->where('line_no', 2)->update(['credit_base' => '10.0000']);
        $this->validate($s);
    }

    public function test_postgres_numeric_rejects_overflow_scale_false_balance(): void
    {
        $s = $this->seedLedger();
        $max = '9999999999999999.9999';
        $this->addLine($s, 1, $max, '0');
        $this->addLine($s, 2, $max, '0');
        $this->addLine($s, 3, '0', $max);
        $this->addLine($s, 4, '0', '9999999999999999.9998');
        $this->rejects(fn () => $this->validate($s), 'Journal lines');
        DB::table('journal_lines')->where('entry_id', $s['journal'])
            ->where('line_no', 4)->update(['credit_base' => $max]);
        $this->validate($s);
    }

    public function test_inactive_account_and_company_are_rejected(): void
    {
        $s = $this->seedLedger();
        $this->balanced($s);
        DB::table('ledger_accounts')->where('id', $s['debit'])->update(['active' => false]);
        $this->rejects(fn () => $this->validate($s), 'Inactive journal account');
        DB::table('ledger_accounts')->where('id', $s['debit'])->update(['active' => true]);
        DB::table('companies')->where('id', $s['company'])->update(['status' => 'inactive']);
        $this->rejects(fn () => $this->validate($s), 'Active legal company');
    }

    public function test_closed_or_locked_period_and_closed_fiscal_year_are_rejected(): void
    {
        $s = $this->seedLedger();
        $this->balanced($s);
        DB::table('accounting_periods')->where('id', $s['period'])
            ->update(['state' => 'closed', 'locked_at' => now()]);
        $this->rejects(fn () => $this->validate($s), 'Fiscal year and accounting period');
        DB::table('accounting_periods')->where('id', $s['period'])
            ->update(['state' => 'locked']);
        $this->rejects(fn () => $this->validate($s), 'Fiscal year and accounting period');
        DB::table('accounting_periods')->where('id', $s['period'])
            ->update(['state' => 'open', 'locked_at' => null]);
        DB::table('fiscal_years')->where('id', $s['fiscal'])->update(['state' => 'closed']);
        $this->rejects(fn () => $this->validate($s), 'Fiscal year and accounting period');
    }

    public function test_company_scope_blocks_egypt_italy_access_in_both_directions(): void
    {
        $eg = $this->seedLedger('EG');
        $it = $this->seedLedger('IT');
        $this->balanced($eg);
        $this->balanced($it);
        $this->rejects(fn () => $this->validate($eg, $it['company']), 'Own-company draft');
        $this->rejects(fn () => $this->validate($it, $eg['company']), 'Own-company draft');
        $this->validate($eg);
        $this->validate($it);
    }

    public function test_multicurrency_snapshot_and_inactive_currency_fail_closed(): void
    {
        $s = $this->seedLedger();
        // P0-03 CHECK uses SQL three-valued logic and accepts NULL in this
        // incomplete FX case. Eligibility must fail closed independently.
        $this->addLine($s, 1, '10', '0', [
            'transaction_currency' => 'EUR', 'amount_currency' => '10',
            'fx_rate_snapshot' => null,
        ]);
        $this->addLine($s, 2, '0', '10');
        $this->rejects(fn () => $this->validate($s), 'Journal lines');
        DB::table('journal_lines')->where('entry_id', $s['journal'])
            ->where('line_no', 1)->update(['fx_rate_snapshot' => '1.000000000000']);
        $this->validate($s);
        DB::table('currencies')->where('code', 'EUR')->update(['active' => false]);
        $this->rejects(fn () => $this->validate($s), 'Inactive ledger currency');

    }

    public function test_rollback_preserves_draft_and_no_financial_side_effects(): void
    {
        $s = $this->seedLedger();
        $this->balanced($s);
        try {
            DB::transaction(function () use ($s): void {
                $this->validate($s);
                throw new RuntimeException('Synthetic caller rollback.');
            });
            $this->fail('Expected synthetic rollback.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Synthetic caller rollback.', $exception->getMessage());
        }
        $this->assertSame('draft', DB::table('journal_entries')->where('id', $s['journal'])->value('status'));
        $this->assertSame(0, DB::table('outbox_events')->count());
        $this->validate($s);
    }

    public function test_non_draft_journal_is_not_eligible_even_with_balanced_lines(): void
    {
        $s = $this->seedLedger();
        $this->balanced($s);
        // Only P0-03 exists on this independent base; P0-04A's DRAFT guards are unmerged.
        DB::table('journal_entries')->where('id', $s['journal'])->update([
            'status' => 'posted', 'posted_at' => now(),
        ]);
        $this->rejects(fn () => $this->validate($s), 'Own-company draft');
    }
}
