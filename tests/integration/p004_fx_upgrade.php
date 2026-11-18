<?php

declare(strict_types=1);

// P0-04-FX synthetic populated-migration fixture.
// Only callable on the disposable CI database. No customer or real financial data.
if (getenv('APP_ENV') !== 'testing' || getenv('DB_DATABASE') !== 'elmo_erp_test'
    || getenv('DB_PORT') !== '5432') {
    fwrite(STDERR, "REFUSED: synthetic direct PostgreSQL 18 testing only.\n");
    exit(2);
}

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$stage = $argv[1] ?? '';
$ids = [
    'eg' => '00000000-0000-4000-8000-00000000c101',
    'it' => '00000000-0000-4000-8000-00000000c102',
    'fy_eg' => '00000000-0000-4000-8000-00000000c103',
    'fy_it' => '00000000-0000-4000-8000-00000000c104',
    'period_eg' => '00000000-0000-4000-8000-00000000c105',
    'period_it' => '00000000-0000-4000-8000-00000000c106',
    'account_eg' => '00000000-0000-4000-8000-00000000c107',
    'account_it' => '00000000-0000-4000-8000-00000000c108',
    'entry_eg' => '00000000-0000-4000-8000-00000000c109',
    'entry_it' => '00000000-0000-4000-8000-00000000c110',
    'line_eg' => '00000000-0000-4000-8000-00000000c111',
    'line_it' => '00000000-0000-4000-8000-00000000c112',
    'invalid' => '00000000-0000-4000-8000-00000000c113',
    'after_down' => '00000000-0000-4000-8000-00000000c114',
];
$assert = static function (bool $condition, string $why): void {
    if (! $condition) {
        throw new RuntimeException('P0-04-FX synthetic verification failed: '.$why);
    }
};
$state = static function (string $constraint): ?int {
    $row = DB::selectOne(<<<'SQL'
SELECT CASE WHEN convalidated THEN 1 ELSE 0 END AS validated
FROM pg_constraint WHERE conrelid='journal_lines'::regclass
  AND conname=? AND contype='c'
SQL, [$constraint]);

    return $row === null ? null : (int) $row->validated;
};
$applied = static fn (): bool => DB::table('migrations')
    ->where('migration', '2026_11_18_000008_harden_fx_snapshot_complete')->exists();

if ($stage === 'seed-invalid') {
    $assert($state('p004_fx_snapshot_complete_chk') === null, 'new CHECK must be rolled back first');
    DB::transaction(static function () use ($ids): void {
        DB::table('currencies')->insertOrIgnore([
            ['code' => 'EGP', 'name' => 'Synthetic EGP'],
            ['code' => 'EUR', 'name' => 'Synthetic EUR'],
        ]);
        foreach (['eg' => ['EG', 'EGP'], 'it' => ['IT', 'EUR']] as $region => [$country, $currency]) {
            DB::table('companies')->insert([
                'id' => $ids[$region], 'code' => 'P004FX-UP-'.strtoupper($region),
                'legal_name' => 'Synthetic '.$country, 'country_code' => $country,
                'base_currency_code' => $currency, 'timezone' => 'UTC',
                'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('fiscal_years')->insert([
                'id' => $ids['fy_'.$region], 'company_id' => $ids[$region], 'label' => 'FX-UP-FY',
                'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31',
                'state' => 'open', 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('accounting_periods')->insert([
                'id' => $ids['period_'.$region], 'company_id' => $ids[$region],
                'fiscal_year_id' => $ids['fy_'.$region],
                'starts_on' => '2026-01-01', 'ends_on' => '2026-03-31',
                'state' => 'open', 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('ledger_accounts')->insert([
                'id' => $ids['account_'.$region], 'company_id' => $ids[$region],
                'code' => 'FX-UP-CASH', 'name' => 'Synthetic',
                'account_type' => 'asset', 'normal_balance' => 'debit',
                'active' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('journal_entries')->insert([
                'id' => $ids['entry_'.$region], 'company_id' => $ids[$region],
                'period_id' => $ids['period_'.$region],
                'entry_date' => '2026-02-01', 'status' => 'draft',
                'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('journal_lines')->insert([
                'id' => $ids['line_'.$region], 'company_id' => $ids[$region],
                'entry_id' => $ids['entry_'.$region], 'account_id' => $ids['account_'.$region],
                'line_no' => 1, 'debit_base' => '10.0000', 'credit_base' => '0',
                'transaction_currency' => $region === 'eg' ? 'EUR' : null,
                'amount_currency' => $region === 'eg' ? '10.0000' : null,
                'fx_rate_snapshot' => $region === 'eg' ? '1.000000000000' : null,
            ]);
        }
        // Intentionally insert a historically accepted UNKNOWN tuple ONLY in
        // a disposable DB. It must survive the rejected migration unchanged.
        DB::table('journal_lines')->insert([
            'id' => $ids['invalid'], 'company_id' => $ids['eg'],
            'entry_id' => $ids['entry_eg'], 'account_id' => $ids['account_eg'],
            'line_no' => 2, 'debit_base' => '10.0000', 'credit_base' => '0',
            'transaction_currency' => 'EUR', 'amount_currency' => '10.0000',
            'fx_rate_snapshot' => null,
        ]);
    });
    echo "PASS: populated synthetic Egypt/Italy fixture with one legacy NULL FX row.\n";
    exit(0);
}

$assert($state('p003_currency_snapshot_chk') === 1, 'P0-03 legacy CHECK intact');
$assert(DB::table('companies')->whereIn('id', [$ids['eg'], $ids['it']])->count() === 2, 'both synthetic companies remain');
$assert(DB::table('journal_lines')->whereIn('id', [$ids['line_eg'], $ids['line_it']])->count() === 2, 'valid history preserved');

if ($stage === 'failed') {
    $assert($state('p004_fx_snapshot_complete_chk') === null, 'failed migrate left no new constraint');
    $assert(! $applied(), 'failed migrate not recorded as applied');
    $assert(DB::table('journal_lines')->where('id', $ids['invalid'])
        ->whereNull('fx_rate_snapshot')->where('transaction_currency', 'EUR')->exists(),
        'legacy invalid row unchanged after failed transaction');
    echo "PASS: invalid populated migration rejected; no new CHECK, no automatic remediation, legacy rows unchanged.\n";
    exit(0);
}
if ($stage === 'remove-synthetic-invalid') {
    $assert(DB::table('journal_lines')->where('id', $ids['invalid'])->delete() === 1,
        'delete only the explicitly synthetic bad row');
    echo "PASS: only disposable invalid fixture deleted; no financial history was rewritten.\n";
    exit(0);
}
if ($stage === 'up' || $stage === 'reup') {
    $assert($state('p004_fx_snapshot_complete_chk') === 1, 'new CHECK must be validated');
    $assert($applied(), 'migration registered');
    $assert(DB::table('journal_lines')->where('id', $ids['invalid'])->doesntExist(), 'bad test row removed');
    echo "PASS: {$stage} validated new FX CHECK, preserves P0-03 and populated Egypt/Italy records.\n";
    exit(0);
}
if ($stage === 'down') {
    $assert($state('p004_fx_snapshot_complete_chk') === null, 'only new constraint dropped');
    $assert(! $applied(), 'rollback removed migration record');
    // Prove legacy-state compatibility on rollback, then remove test-only row
    // before reapplying strict constraint.
    DB::transaction(static function () use ($ids): void {
        DB::table('journal_lines')->insert([
            'id' => $ids['after_down'], 'company_id' => $ids['it'],
            'entry_id' => $ids['entry_it'], 'account_id' => $ids['account_it'],
            'line_no' => 2, 'debit_base' => '10', 'credit_base' => '0',
            'transaction_currency' => 'EGP', 'amount_currency' => '10',
            'fx_rate_snapshot' => null,
        ]);
    });
    $assert(DB::table('journal_lines')->where('id', $ids['after_down'])
        ->whereNull('fx_rate_snapshot')->exists(), 'legacy rollback behavior restored');
    $assert(DB::table('journal_lines')->where('id', $ids['after_down'])->delete() === 1,
        'discard disposable rollback fixture');
    echo "PASS: rollback restored exact legacy CHECK behavior; synthetic-only dirty row discarded.\n";
    exit(0);
}
if ($stage === 'cleanup') {
    DB::transaction(static function () use ($ids): void {
        DB::table('journal_lines')->whereIn('company_id', [$ids['eg'], $ids['it']])->delete();
        DB::table('journal_entries')->whereIn('company_id', [$ids['eg'], $ids['it']])->delete();
        DB::table('ledger_accounts')->whereIn('company_id', [$ids['eg'], $ids['it']])->delete();
        DB::table('accounting_periods')->whereIn('company_id', [$ids['eg'], $ids['it']])->delete();
        DB::table('fiscal_years')->whereIn('company_id', [$ids['eg'], $ids['it']])->delete();
        DB::table('companies')->whereIn('id', [$ids['eg'], $ids['it']])->delete();
    });
    echo "PASS: P0-04-FX disposable data cleaned.\n";
    exit(0);
}
throw new InvalidArgumentException('Allowed stages: seed-invalid, failed, remove-synthetic-invalid, up, down, reup, cleanup');
