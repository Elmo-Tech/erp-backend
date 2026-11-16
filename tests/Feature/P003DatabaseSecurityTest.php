<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class P003DatabaseSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function uuid(): string
    {
        return (string) Str::uuid7();
    }

    /** @return array{string, string} */
    private function seedCompanies(): array
    {
        DB::table('currencies')->insert([
            ['code' => 'EGP', 'name' => 'Egyptian Pound'],
            ['code' => 'EUR', 'name' => 'Euro'],
        ]);
        $eg = $this->uuid();
        $it = $this->uuid();
        foreach ([[$eg, 'EG', 'EGP'], [$it, 'IT', 'EUR']] as [$id, $country, $currency]) {
            DB::table('companies')->insert([
                'id' => $id, 'code' => "P003-{$country}",
                'legal_name' => "Synthetic {$country}", 'country_code' => $country,
                'base_currency_code' => $currency, 'timezone' => 'UTC',
                'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        return [$eg, $it];
    }

    private function fiscal(string $company): string
    {
        $id = $this->uuid();
        DB::table('fiscal_years')->insert([
            'id' => $id, 'company_id' => $company, 'label' => 'FY-2026',
            'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31',
            'state' => 'open', 'created_at' => now(), 'updated_at' => now(),
        ]);
        return $id;
    }

    private function period(string $company, string $fiscal, string $start = '2026-01-01', string $end = '2026-03-31'): string
    {
        $id = $this->uuid();
        DB::table('accounting_periods')->insert([
            'id' => $id, 'company_id' => $company, 'fiscal_year_id' => $fiscal,
            'starts_on' => $start, 'ends_on' => $end, 'state' => 'open',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        return $id;
    }

    private function account(string $company, string $code): string
    {
        $id = $this->uuid();
        DB::table('ledger_accounts')->insert([
            'id' => $id, 'company_id' => $company, 'code' => $code,
            'name' => $code, 'account_type' => 'asset',
            'normal_balance' => 'debit', 'active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        return $id;
    }

    private function entry(string $company, string $period, string $date = '2026-02-01'): string
    {
        $id = $this->uuid();
        DB::table('journal_entries')->insert([
            'id' => $id, 'company_id' => $company, 'period_id' => $period,
            'entry_date' => $date, 'status' => 'draft',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        return $id;
    }

    private function line(string $company, string $entry, string $account, int $number, string $debit = '10.0000', string $credit = '0'): array
    {
        return [
            'id' => $this->uuid(), 'company_id' => $company, 'entry_id' => $entry,
            'account_id' => $account, 'line_no' => $number,
            'debit_base' => $debit, 'credit_base' => $credit,
        ];
    }

    private function rejects(callable $write): void
    {
        try {
            // RefreshDatabase keeps a parent transaction open; a nested savepoint
            // avoids PostgreSQL's failed-transaction state poisoning next asserts.
            DB::transaction($write);
            $this->fail('Expected a PostgreSQL integrity constraint to reject this write.');
        } catch (QueryException $exception) {
            $this->assertMatchesRegularExpression('/SQLSTATE\[(23503|23505|23514|23P01)\]/', $exception->getMessage());
        }
    }

    public function test_direct_sql_rejects_egypt_italy_fiscal_and_journal_links_in_both_directions(): void
    {
        [$eg, $it] = $this->seedCompanies();
        $fiscal = [$eg => $this->fiscal($eg), $it => $this->fiscal($it)];
        $period = [$eg => $this->period($eg, $fiscal[$eg]), $it => $this->period($it, $fiscal[$it])];
        $account = [$eg => $this->account($eg, 'CASH'), $it => $this->account($it, 'CASH')];
        $entry = [$eg => $this->entry($eg, $period[$eg]), $it => $this->entry($it, $period[$it])];

        foreach ([[$eg, $it], [$it, $eg]] as [$own, $other]) {
            $this->rejects(fn () => $this->period($own, $fiscal[$other], '2026-04-01', '2026-06-30'));
            $this->rejects(fn () => $this->entry($own, $period[$other]));
            $this->rejects(fn () => DB::table('journal_lines')->insert(
                $this->line($own, $entry[$other], $account[$own], 1)
            ));
            $this->rejects(fn () => DB::table('journal_lines')->insert(
                $this->line($own, $entry[$own], $account[$other], 2)
            ));
            // An imported journal cannot relabel a foreign-company period.
            $this->rejects(fn () => DB::table('journal_entries')->where('id', $entry[$own])
                ->update(['period_id' => $period[$other]]));
        }
        $this->assertSame(0, DB::table('journal_lines')->count());
    }

    public function test_journal_sides_currency_dates_states_and_overlaps_are_enforced_in_sql(): void
    {
        [$eg, $it] = $this->seedCompanies();
        $fy = $this->fiscal($eg);
        $otherFy = $this->fiscal($it);
        $p = $this->period($eg, $fy);
        $this->period($it, $otherFy); // same calendar dates across different companies are legal.
        $acc = $this->account($eg, 'CASH');
        $j = $this->entry($eg, $p);

        $this->rejects(fn () => DB::table('journal_lines')->insert($this->line($eg, $j, $acc, 1, '0', '0')));
        $this->rejects(fn () => DB::table('journal_lines')->insert($this->line($eg, $j, $acc, 1, '10', '5')));
        $this->rejects(fn () => DB::table('journal_lines')->insert($this->line($eg, $j, $acc, 1, '-1', '0')));
        $this->rejects(fn () => DB::table('journal_lines')->insert(
            $this->line($eg, $j, $acc, 1) + ['transaction_currency' => 'EUR', 'amount_currency' => '10', 'fx_rate_snapshot' => '0']
        ));
        $this->rejects(fn () => DB::table('journal_lines')->insert($this->line($eg, $j, $acc, 0)));
        $this->rejects(fn () => $this->period($eg, $fy, '2026-03-31', '2026-04-30'));
        $this->rejects(fn () => $this->period($eg, $fy, '2025-12-01', '2025-12-31'));
        $this->rejects(fn () => $this->entry($eg, $p, '2026-04-01'));
        $this->rejects(fn () => DB::table('accounting_periods')->where('id', $p)->update(['ends_on' => '2026-01-31']));
        $this->rejects(fn () => DB::table('fiscal_years')->where('id', $fy)->update(['starts_on' => '2026-02-02']));
        $this->rejects(fn () => DB::table('journal_entries')->where('id', $j)->update(['status' => 'posted']));
        $this->rejects(fn () => DB::table('ledger_accounts')->where('id', $acc)->update(['normal_balance' => 'wrong']));

        DB::table('journal_lines')->insert($this->line($eg, $j, $acc, 1));
        $this->assertSame(1, DB::table('journal_lines')->count());
    }

    public function test_company_owned_tables_have_not_null_company_and_parent_candidate_keys(): void
    {
        $tables = ['company_memberships', 'company_role_assignments', 'fiscal_years',
            'accounting_periods', 'ledger_accounts', 'journal_entries', 'journal_lines',
            'idempotency_requests'];
        foreach ($tables as $table) {
            $notNull = DB::selectOne(
                'SELECT attnotnull AS value FROM pg_attribute WHERE attrelid = ?::regclass AND attname = ?',
                [$table, 'company_id']
            );
            $this->assertTrue((bool) $notNull->value, "{$table}.company_id must be non-null");
            $unique = DB::selectOne(<<<'SQL'
SELECT EXISTS (
  SELECT 1 FROM pg_constraint c
  WHERE c.conrelid = ?::regclass AND c.contype IN ('u','p')
    AND (SELECT array_agg(a.attname ORDER BY a.attname)
         FROM unnest(c.conkey) AS k(attnum)
         JOIN pg_attribute a ON a.attrelid = c.conrelid AND a.attnum = k.attnum)
        = ARRAY['company_id','id']::name[]
) AS value
SQL, [$table]);
            $this->assertTrue((bool) $unique->value, "{$table} needs UNIQUE(company_id,id)");
        }
    }
}
