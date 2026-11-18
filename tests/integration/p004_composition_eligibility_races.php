<?php

declare(strict_types=1);

/**
 * P0-04 composition-only, two *real* PostgreSQL connections.
 * Executes the actual Laravel read-only eligibility method while a second
 * session tries to edit lines; NEVER executes a posting transition.
 */
if (getenv('APP_ENV') !== 'testing' || getenv('DB_DATABASE') !== 'elmo_erp_test'
    || ! in_array(getenv('DB_PORT'), ['5432', '16432'], true)) {
    fwrite(STDERR, "REFUSED: only disposable PG18/PgBouncer elmo_erp_test.\n");
    exit(2);
}
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Domain\Accounting\JournalPostingEligibilityValidator;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

$port = getenv('DB_PORT');
$dsn = sprintf('pgsql:host=%s;port=%s;dbname=elmo_erp_test',
    getenv('DB_HOST') ?: '127.0.0.1', $port);
$other = new PDO($dsn, getenv('DB_USERNAME') ?: 'elmo',
    getenv('DB_PASSWORD') ?: '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => true,
    ]);
$check = static function (bool $ok, string $why): void {
    if (! $ok) {
        throw new RuntimeException('P0-04 composition concurrency: '.$why);
    }
};
$tag = $port === '16432' ? 'c3' : 'c2';
$id = static fn (int $n): string => sprintf('00000000-0000-4000-8000-000000%s%04d', $tag, $n);
$eg = ['company' => $id(101), 'year' => $id(102), 'period' => $id(103),
    'account' => $id(104), 'entry' => $id(105), 'debit' => $id(106), 'credit' => $id(107),
    'other_entry' => $id(108), 'other_line' => $id(109)];
$it = ['company' => $id(201), 'year' => $id(202), 'period' => $id(203),
    'account' => $id(204), 'entry' => $id(205), 'debit' => $id(206), 'credit' => $id(207)];
$temporary = $id(301);
$validator = new JournalPostingEligibilityValidator();

// PgBouncer may reuse a backend between sequential autocommit queries.
// Pin both sessions at the same time before comparing server PIDs.
DB::beginTransaction();
$other->beginTransaction();
try {
    $check(DB::getPdo()->query('SELECT pg_backend_pid()')->fetchColumn() !==
        $other->query('SELECT pg_backend_pid()')->fetchColumn(), 'concurrent backend sessions not distinct');
} finally {
    $other->rollBack();
    DB::rollBack();
}

$rejected = static function (string $expected, callable $operation) use ($other, $check): void {
    $other->beginTransaction();
    try {
        $other->exec("SET LOCAL lock_timeout = '250ms'");
        $operation();
        throw new RuntimeException('Expected SQLSTATE '.$expected.' (write was accepted).');
    } catch (PDOException $e) {
        $check($e->getCode() === $expected,
            'expected '.$expected.', got '.$e->getCode().' / '.$e->getMessage());
    } finally {
        if ($other->inTransaction()) {
            $other->rollBack();
        }
    }
};
$exec = static function (PDO $db, string $sql, array $params): void {
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
};

$seeded = false;
try {
    $check((int) DB::selectOne("SELECT current_setting('server_version_num')::int AS v")->v >= 180000,
        'PostgreSQL 18 required');
    DB::table('currencies')->insertOrIgnore([
        ['code' => 'EGP', 'name' => 'Synthetic EGP'],
        ['code' => 'EUR', 'name' => 'Synthetic EUR'],
    ]);
    foreach (['EG' => $eg, 'IT' => $it] as $country => $f) {
        DB::table('companies')->insert([
            'id' => $f['company'], 'code' => 'P004C-'.$tag.'-'.$country,
            'legal_name' => 'Synthetic '.$country, 'country_code' => $country,
            'base_currency_code' => $country === 'EG' ? 'EGP' : 'EUR',
            'timezone' => 'UTC', 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $seeded = true;
        DB::table('fiscal_years')->insert([
            'id' => $f['year'], 'company_id' => $f['company'], 'label' => 'FY-2026',
            'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31',
            'state' => 'open', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('accounting_periods')->insert([
            'id' => $f['period'], 'company_id' => $f['company'], 'fiscal_year_id' => $f['year'],
            'starts_on' => '2026-01-01', 'ends_on' => '2026-03-31',
            'state' => 'open', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('ledger_accounts')->insert([
            'id' => $f['account'], 'company_id' => $f['company'],
            'code' => 'P004C-CASH', 'name' => 'Synthetic cash',
            'account_type' => 'asset', 'normal_balance' => 'debit',
            'active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('journal_entries')->insert([
            'id' => $f['entry'], 'company_id' => $f['company'],
            'period_id' => $f['period'], 'entry_date' => '2026-02-01',
            'status' => 'draft', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('journal_lines')->insert([
            ['id' => $f['debit'], 'company_id' => $f['company'], 'entry_id' => $f['entry'],
                'account_id' => $f['account'], 'line_no' => 1,
                'debit_base' => '10.0000', 'credit_base' => '0',
                'transaction_currency' => $country === 'EG' ? 'EUR' : null,
                'amount_currency' => $country === 'EG' ? '10.0000' : null,
                'fx_rate_snapshot' => $country === 'EG' ? '1.000000000000' : null],
            ['id' => $f['credit'], 'company_id' => $f['company'], 'entry_id' => $f['entry'],
                'account_id' => $f['account'], 'line_no' => 2,
                'debit_base' => '0', 'credit_base' => '10.0000',
                'transaction_currency' => null, 'amount_currency' => null,
                'fx_rate_snapshot' => null],
        ]);
    }

    // Same-company reparent fixture: a second EG draft has a distinct line,
    // and the destination EG entry sorts first under the P0-04A header order.
    DB::table('journal_entries')->insert([
        'id' => $eg['other_entry'], 'company_id' => $eg['company'],
        'period_id' => $eg['period'], 'entry_date' => '2026-02-02',
        'status' => 'draft', 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('journal_lines')->insert([
        'id' => $eg['other_line'], 'company_id' => $eg['company'],
        'entry_id' => $eg['other_entry'], 'account_id' => $eg['account'],
        'line_no' => 3, 'debit_base' => '1.0000', 'credit_base' => '0',
    ]);

    // Actual internal validator runs inside an outer transaction. The nested
    // DB::transaction returns but its header FOR UPDATE lock survives to outer
    // transaction end. Other PostgreSQL backend is independent.
    DB::beginTransaction();
    try {
        $validator->assertEligible($eg['company'], $eg['entry']);
        foreach ([
            "UPDATE journal_lines SET debit_base=11 WHERE id='{$eg['debit']}'",
            "DELETE FROM journal_lines WHERE id='{$eg['credit']}'",
            "INSERT INTO journal_lines(id,company_id,entry_id,account_id,line_no,debit_base,credit_base)
                VALUES('{$temporary}','{$eg['company']}','{$eg['entry']}','{$eg['account']}',3,1,0)",
            "UPDATE journal_lines SET entry_id='{$eg['entry']}' WHERE id='{$eg['other_line']}'",
        ] as $sql) {
            // Reparent may also hit FK, but parent-header lock should be
            // encountered first in P0-04A trigger's deterministic ordering.
            $rejected('55P03', static fn () => $other->exec($sql));
        }
        $check(DB::table('journal_entries')->where('id', $eg['entry'])->value('status') === 'draft',
            'eligibility caused a status mutation');
        $check(DB::table('outbox_events')->count() === 0 &&
            DB::table('audit_logs')->count() === 0 &&
            DB::table('idempotency_requests')->count() === 0, 'eligibility had write side effects');

        // Different legal company: independent validator remains eligible.
        // No global journal header lock or cross-company activity required.
        $validator->assertEligible($it['company'], $it['entry']);
    } finally {
        DB::rollBack();
    }

    // Releasing the validator's outer lock permits draft edits again.
    $other->beginTransaction();
    $other->exec("UPDATE journal_lines SET debit_base=11 WHERE id='{$eg['debit']}'");
    $other->commit();
    try {
        $validator->assertEligible($eg['company'], $eg['entry']);
        throw new RuntimeException('Validator accepted a freshly unbalanced draft.');
    } catch (InvalidArgumentException $e) {
        $check(str_contains($e->getMessage(), 'Journal lines'), 'wrong imbalance rejection');
    }
    $other->beginTransaction();
    $other->exec("UPDATE journal_lines SET debit_base=10 WHERE id='{$eg['debit']}'");
    $other->commit();
    $validator->assertEligible($eg['company'], $eg['entry']);

    // Reverse lock ordering: competing client already holds header as a
    // consequence of P0-04A child UPDATE; real eligibility waits / times out.
    $other->beginTransaction();
    $other->exec("UPDATE journal_lines SET debit_base=11 WHERE id='{$eg['debit']}'");
    DB::beginTransaction();
    try {
        DB::statement("SET LOCAL lock_timeout = '250ms'");
        try {
            $validator->assertEligible($eg['company'], $eg['entry']);
            throw new RuntimeException('Validator unexpectedly skipped writer-owned header lock.');
        } catch (QueryException $e) {
            $check(str_contains($e->getMessage(), '55P03'), 'reverse lock wait SQLSTATE');
        }
    } finally {
        DB::rollBack();
        $other->rollBack();
    }
    $validator->assertEligible($eg['company'], $eg['entry']);

    // Direct SQL posting always fails closed, even after passing eligibility.
    $rejected('23514', static fn () => $other->exec(
        "UPDATE journal_entries SET status='posted',posted_at=now() WHERE id='{$eg['entry']}'"));
    // FX snapshot rejects missing rate even if base totals could balance.
    $rejected('23514', static fn () => $other->exec(
        "UPDATE journal_lines SET transaction_currency='EUR',amount_currency=10,
         fx_rate_snapshot=NULL WHERE id='{$eg['credit']}'"));
    $rejected('23503', static fn () => $other->exec(
        "UPDATE journal_lines SET account_id='{$it['account']}' WHERE id='{$eg['debit']}'"));

    $check(DB::table('journal_entries')->where('id', $eg['entry'])->value('status') === 'draft',
        'status changed after blocked posting');
    echo "PASS P0-04 combined: actual eligibility vs child INSERT/UPDATE/DELETE/reparent,
      55P03 lock order both directions, rollback, post-denial 23514, FX 23514,
      EG/IT 23503, recheck after line edit; PG18 port {$port}.
      No posting; future header->child deadlock scenario NOT PROVEN.\n";
} finally {
    if (DB::transactionLevel() > 0) {
        DB::rollBack();
    }
    if ($other->inTransaction()) {
        $other->rollBack();
    }
    if ($seeded) {
        foreach (['journal_lines', 'journal_entries', 'ledger_accounts',
            'accounting_periods', 'fiscal_years'] as $table) {
            DB::table($table)->whereIn('company_id', [$eg['company'], $it['company']])->delete();
        }
        DB::table('companies')->whereIn('id', [$eg['company'], $it['company']])->delete();
    }
}
