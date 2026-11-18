<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class P004FxSnapshotConstraintTest extends TestCase
{
    use RefreshDatabase;

    private function uuid(): string
    {
        return (string) Str::uuid7();
    }

    /** @return array<string, array{company:string,entry:string,account:string}> */
    private function seedFxCompanies(): array
    {
        DB::table('currencies')->insert([
            ['code' => 'EGP', 'name' => 'Synthetic EGP'],
            ['code' => 'EUR', 'name' => 'Synthetic EUR'],
        ]);

        $fixtures = [];
        foreach (['EG' => 'EGP', 'IT' => 'EUR'] as $country => $currency) {
            $company = $this->uuid();
            $year = $this->uuid();
            $period = $this->uuid();
            $account = $this->uuid();
            $entry = $this->uuid();

            DB::table('companies')->insert([
                'id' => $company, 'code' => 'FX-'.$country,
                'legal_name' => 'Synthetic '.$country, 'country_code' => $country,
                'base_currency_code' => $currency, 'timezone' => 'UTC',
                'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('fiscal_years')->insert([
                'id' => $year, 'company_id' => $company, 'label' => 'FX-FY-2026',
                'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31',
                'state' => 'open', 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('accounting_periods')->insert([
                'id' => $period, 'company_id' => $company, 'fiscal_year_id' => $year,
                'starts_on' => '2026-01-01', 'ends_on' => '2026-03-31',
                'state' => 'open', 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('ledger_accounts')->insert([
                'id' => $account, 'company_id' => $company, 'code' => 'FX-TEST',
                'name' => 'Synthetic account', 'account_type' => 'asset',
                'normal_balance' => 'debit', 'active' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('journal_entries')->insert([
                'id' => $entry, 'company_id' => $company, 'period_id' => $period,
                'entry_date' => '2026-02-01', 'status' => 'draft',
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $fixtures[$country] = compact('company', 'entry', 'account');
        }

        return $fixtures;
    }

    /** @param array{company:string,entry:string,account:string} $owner */
    private function rawInsert(
        array $owner, int $line, ?string $currency, ?string $amount, ?string $rate,
        ?string $id = null, ?string $entry = null
    ): string {
        $id ??= $this->uuid();

        DB::insert(<<<'SQL'
INSERT INTO journal_lines
(id, company_id, entry_id, account_id, line_no, debit_base, credit_base,
 transaction_currency, amount_currency, fx_rate_snapshot)
VALUES (?, ?, ?, ?, ?, ?::numeric, ?::numeric, ?, ?::numeric, ?::numeric)
SQL, [$id, $owner['company'], $entry ?? $owner['entry'], $owner['account'],
            $line, '10.0000', '0', $currency, $amount, $rate]);

        return $id;
    }

    private function rawUpdate(string $id, ?string $currency, ?string $amount, ?string $rate): void
    {
        DB::update(<<<'SQL'
UPDATE journal_lines
SET transaction_currency = ?, amount_currency = ?::numeric, fx_rate_snapshot = ?::numeric
WHERE id = ?
SQL, [$currency, $amount, $rate, $id]);
    }

    private function rejectsWithSqlstate(callable $operation, string $code = '23514'): void
    {
        try {
            // Savepoint isolates a failed SQL statement from RefreshDatabase's
            // surrounding transaction, especially with PostgreSQL 18.
            DB::transaction($operation);
            $this->fail('Expected PostgreSQL SQLSTATE '.$code.' but statement succeeded.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('SQLSTATE['.$code.']', $exception->getMessage());
        }
    }

    public function test_new_and_legacy_constraints_both_exist_and_new_one_is_validated(): void
    {
        $checks = DB::select(<<<'SQL'
SELECT conname,
       CASE WHEN convalidated THEN 1 ELSE 0 END AS valid,
       pg_get_constraintdef(oid) AS definition
FROM pg_constraint
WHERE conrelid = 'journal_lines'::regclass
  AND conname IN ('p003_currency_snapshot_chk', 'p004_fx_snapshot_complete_chk')
ORDER BY conname
SQL);
        $this->assertCount(2, $checks);
        $byName = [];
        foreach ($checks as $check) {
            $byName[$check->conname] = $check;
            $this->assertSame(1, (int) $check->valid);
        }
        $this->assertArrayHasKey('p003_currency_snapshot_chk', $byName);
        $this->assertArrayHasKey('p004_fx_snapshot_complete_chk', $byName);
        $this->assertStringContainsString('IS TRUE', $byName['p004_fx_snapshot_complete_chk']->definition);
    }

    public function test_raw_sql_insert_accepts_only_complete_or_entirely_absent_fx_for_egypt_and_italy(): void
    {
        foreach ($this->seedFxCompanies() as $region => $owner) {
            $foreignCurrency = $region === 'EG' ? 'EUR' : 'EGP';
            $this->rawInsert($owner, 1, null, null, null);
            $this->rawInsert($owner, 2, $foreignCurrency, '20.0000', '1.250000000000');
            $this->rawInsert($owner, 3, $foreignCurrency, '9999999999999999.9999', '999999999999.999999999999');

            $cases = [
                ['EUR', '20.0000', null], // P0-03 UNKNOWN bypass: the regression
                ['EUR', null, '1.250000000000'],
                ['EUR', null, null],
                [null, '20.0000', '1.250000000000'],
                [null, '20.0000', null],
                [null, null, '1.250000000000'],
                ['EUR', '0.0000', '1.250000000000'],
                ['EUR', '-2.0000', '1.250000000000'],
                ['EUR', '20.0000', '0.000000000000'],
                ['EUR', '20.0000', '-0.500000000000'],
            ];
            foreach ($cases as [$currency, $amount, $rate]) {
                $this->rejectsWithSqlstate(
                    fn () => $this->rawInsert($owner, 4, $currency, $amount, $rate)
                );
            }
            $this->assertSame(3, DB::table('journal_lines')
                ->where('company_id', $owner['company'])->count());
        }
    }

    public function test_raw_sql_update_accepts_both_valid_forms_and_rejects_all_partial_fx_changes(): void
    {
        foreach ($this->seedFxCompanies() as $owner) {
            $id = $this->rawInsert($owner, 1, null, null, null);
            $this->rawUpdate($id, 'EUR', '10.0000', '1.000000000000');
            $this->assertSame('EUR', trim((string) DB::table('journal_lines')->where('id', $id)->value('transaction_currency')));
            $this->rawUpdate($id, null, null, null);
            $this->assertNull(DB::table('journal_lines')->where('id', $id)->value('fx_rate_snapshot'));

            foreach ([
                ['EUR', '10.0000', null], ['EUR', null, '1.000000000000'],
                [null, '10.0000', null], [null, null, '1.000000000000'],
                ['EUR', '0', '1'], ['EUR', '10', '0'],
                ['EUR', '-1', '1'], ['EUR', '10', '-1'],
            ] as [$currency, $amount, $rate]) {
                $this->rejectsWithSqlstate(fn () => $this->rawUpdate($id, $currency, $amount, $rate));
                $this->assertNull(DB::table('journal_lines')->where('id', $id)->value('transaction_currency'));
                $this->assertNull(DB::table('journal_lines')->where('id', $id)->value('amount_currency'));
                $this->assertNull(DB::table('journal_lines')->where('id', $id)->value('fx_rate_snapshot'));
            }

            $this->rawUpdate($id, 'EUR', '10.0000', '1.000000000000');
            $this->rejectsWithSqlstate(fn () => $this->rawUpdate($id, 'EUR', '10.0000', null));
            $this->assertSame('1.000000000000',
                (string) DB::table('journal_lines')->where('id', $id)->value('fx_rate_snapshot'));
        }
    }

    public function test_cross_company_journal_links_remain_rejected_in_both_directions(): void
    {
        $owners = $this->seedFxCompanies();
        foreach ([['EG', 'IT'], ['IT', 'EG']] as [$own, $other]) {
            // P0-04A guard fails closed before the journal FK on a missing
            // same-company parent; this is 23514, not the old 23503.
            $this->rejectsWithSqlstate(
                fn () => $this->rawInsert($owners[$own], 1, 'EUR', '10', '1',
                    entry: $owners[$other]['entry']),
                '23514'
            );
            // FK remains independently enforceable when own journal exists
            // but the referenced account belongs to the opposite company.
            $wrongAccount = $owners[$own];
            $wrongAccount['account'] = $owners[$other]['account'];
            $this->rejectsWithSqlstate(
                fn () => $this->rawInsert($wrongAccount, 1, 'EUR', '10', '1'),
                '23503'
            );
        }
        $this->assertSame(0, DB::table('journal_lines')->count());
    }
}
