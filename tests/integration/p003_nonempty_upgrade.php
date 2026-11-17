<?php

declare(strict_types=1);

// Synthetic nonempty P0-03 upgrade/rollback fixture. NEVER use on real data.
if (getenv('APP_ENV') !== 'testing' || getenv('DB_DATABASE') !== 'elmo_erp_test') {
    fwrite(STDERR, "REFUSED: APP_ENV=testing and DB_DATABASE=elmo_erp_test required.\n");
    exit(2);
}

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

$stage = $argv[1] ?? '';
$ids = [
    'eg' => '00000000-0000-4000-8000-000000000301',
    'it' => '00000000-0000-4000-8000-000000000302',
    'member_eg' => '00000000-0000-4000-8000-000000000303',
    'member_it' => '00000000-0000-4000-8000-000000000304',
    'role_eg' => '00000000-0000-4000-8000-000000000305',
    'role_it' => '00000000-0000-4000-8000-000000000306',
    'fy_eg' => '00000000-0000-4000-8000-000000000311',
    'fy_it' => '00000000-0000-4000-8000-000000000312',
    'period_eg' => '00000000-0000-4000-8000-000000000321',
    'period_it' => '00000000-0000-4000-8000-000000000322',
    'account_eg' => '00000000-0000-4000-8000-000000000331',
    'account_it' => '00000000-0000-4000-8000-000000000332',
    'entry_eg' => '00000000-0000-4000-8000-000000000341',
    'entry_it' => '00000000-0000-4000-8000-000000000342',
    'line_eg' => '00000000-0000-4000-8000-000000000351',
    'line_it' => '00000000-0000-4000-8000-000000000352',
    'idem_eg' => '00000000-0000-4000-8000-000000000361',
    'idem_it' => '00000000-0000-4000-8000-000000000362',
];
$check = static function (bool $ok, string $label): void {
    if (! $ok) {
        throw new RuntimeException("P0-03 nonempty fixture failure: {$label}");
    }
};
$companies = [$ids['eg'], $ids['it']];

if ($stage === 'seed') {
    DB::transaction(static function () use ($ids): void {
        foreach ([['EGP', 'Egyptian Pound'], ['EUR', 'Euro']] as [$currency, $name]) {
            DB::table('currencies')->updateOrInsert(['code' => $currency], ['name' => $name]);
        }
        foreach (['eg' => ['EG', 'EGP'], 'it' => ['IT', 'EUR']] as $region => [$country, $currency]) {
            $company = $ids[$region];
            DB::table('companies')->insert([
                'id' => $company, 'code' => 'P003-UP-'.strtoupper($region),
                'legal_name' => 'P003 Synthetic '.strtoupper($region),
                'country_code' => $country, 'base_currency_code' => $currency,
                'timezone' => 'UTC', 'status' => 'active',
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $userId = DB::table('users')->insertGetId([
                'name' => 'P003 Synthetic '.strtoupper($region),
                'email' => 'p003-upgrade-'.$region.'@example.invalid',
                'password' => Hash::make('synthetic-only-not-a-login'),
                'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $member = $ids['member_'.$region];
            DB::table('company_memberships')->insert([
                'id' => $member, 'user_id' => $userId, 'company_id' => $company,
                'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('company_role_assignments')->insert([
                'id' => $ids['role_'.$region], 'company_id' => $company,
                'membership_id' => $member, 'role_key' => 'company_manager',
                'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('fiscal_years')->insert([
                'id' => $ids['fy_'.$region], 'company_id' => $company,
                'label' => 'P003-UP-FY2026', 'starts_on' => '2026-01-01',
                'ends_on' => '2026-12-31', 'state' => 'open',
                'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('accounting_periods')->insert([
                'id' => $ids['period_'.$region], 'company_id' => $company,
                'fiscal_year_id' => $ids['fy_'.$region],
                'starts_on' => '2026-01-01', 'ends_on' => '2026-03-31',
                'state' => 'open', 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('ledger_accounts')->insert([
                'id' => $ids['account_'.$region], 'company_id' => $company,
                'code' => 'P003-UP-CASH', 'name' => 'Synthetic cash',
                'account_type' => 'asset', 'normal_balance' => 'debit',
                'active' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('journal_entries')->insert([
                'id' => $ids['entry_'.$region], 'company_id' => $company,
                'period_id' => $ids['period_'.$region],
                'entry_date' => '2026-02-01', 'status' => 'draft',
                'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('journal_lines')->insert([
                'id' => $ids['line_'.$region], 'company_id' => $company,
                'entry_id' => $ids['entry_'.$region],
                'account_id' => $ids['account_'.$region],
                'line_no' => 1, 'debit_base' => '10.0000', 'credit_base' => '0',
            ]);
            DB::table('idempotency_requests')->insert([
                'id' => $ids['idem_'.$region], 'company_id' => $company,
                'actor_user_id' => $userId,
                'route_signature' => 'p003-upgrade-fixture',
                'idempotency_key' => 'p003-'.$region, 'request_hash' => str_repeat('a', 64),
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    });
    echo "PASS: nonempty synthetic EG and IT legal/accounting/auth rows seeded before P0-03 migration.\n";
    exit(0);
}

if (! in_array($stage, ['up', 'down', 'cleanup'], true)) {
    throw new InvalidArgumentException('Use seed, up, down, or cleanup');
}

if ($stage === 'cleanup') {
    DB::transaction(static function () use ($ids, $companies): void {
        DB::table('idempotency_requests')->whereIn('company_id', $companies)->delete();
        DB::table('journal_lines')->whereIn('company_id', $companies)->delete();
        DB::table('journal_entries')->whereIn('company_id', $companies)->delete();
        DB::table('ledger_accounts')->whereIn('company_id', $companies)->delete();
        DB::table('accounting_periods')->whereIn('company_id', $companies)->delete();
        DB::table('fiscal_years')->whereIn('company_id', $companies)->delete();
        DB::table('company_role_assignments')->whereIn('company_id', $companies)->delete();
        DB::table('company_memberships')->whereIn('company_id', $companies)->delete();
        DB::table('companies')->whereIn('id', $companies)->delete();
        DB::table('users')->whereIn('email', [
            'p003-upgrade-eg@example.invalid', 'p003-upgrade-it@example.invalid',
        ])->delete();
    });
    echo "PASS: synthetic upgrade rows removed.\n";
    exit(0);
}

foreach (['companies', 'company_memberships', 'company_role_assignments', 'fiscal_years',
    'accounting_periods', 'ledger_accounts', 'journal_entries', 'journal_lines',
    'idempotency_requests'] as $table) {
    $check(($table === 'companies'
        ? DB::table('companies')->whereIn('id', $companies)->count()
        : DB::table($table)->whereIn('company_id', $companies)->count()) === 2,
        "{$table} synthetic rows survived {$stage}");
}
$hasConstraint = static fn (string $name): bool =>
    DB::table('pg_constraint')->where('conname', $name)->exists();
foreach (['p003_line_company_id_uniq', 'p003_period_no_overlap', 'p003_one_side_chk'] as $constraint) {
    $check($hasConstraint($constraint) === ($stage === 'up'), "{$constraint} {$stage} existence");
}

if ($stage === 'up') {
    foreach ([['eg', 'it'], ['it', 'eg']] as [$own, $foreign]) {
        try {
            DB::transaction(static function () use ($ids, $own, $foreign): void {
                DB::table('journal_lines')->insert([
                    'id' => (string) Illuminate\Support\Str::uuid7(),
                    'company_id' => $ids[$own], 'entry_id' => $ids['entry_'.$foreign],
                    'account_id' => $ids['account_'.$own], 'line_no' => 2,
                    'debit_base' => '5.0000', 'credit_base' => '0',
                ]);
            });
            throw new RuntimeException("Cross-company {$own} -> {$foreign} unexpectedly accepted");
        } catch (QueryException $e) {
            $check(str_contains($e->getMessage(), '23503'), 'SQLSTATE 23503 cross-company FK');
        }
    }
}
echo "PASS: {$stage} retains nonempty EG/IT rows and expected P0-03 constraint state.\n";
