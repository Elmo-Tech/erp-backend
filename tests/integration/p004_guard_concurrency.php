<?php

declare(strict_types=1);

// P0-04A: direct PostgreSQL 18 or PgBouncer transaction-pool race harness.
// It deliberately does NOT implement or invoke a posting command.
// Synthetic, disposable DB only; never use on staging, production or real accounts.
if (getenv('APP_ENV') !== 'testing' || getenv('DB_DATABASE') !== 'elmo_erp_test'
    || ! in_array(getenv('DB_PORT') ?: '5432', ['5432', '16432'], true)) {
    fwrite(STDERR, "REFUSED: requires APP_ENV=testing, elmo_erp_test and approved test port.\n");
    exit(2);
}

$dsn = sprintf('pgsql:host=%s;port=%s;dbname=elmo_erp_test',
    getenv('DB_HOST') ?: '127.0.0.1', getenv('DB_PORT') ?: '5432');
$connect = static fn (): PDO => new PDO($dsn, getenv('DB_USERNAME') ?: 'elmo',
    getenv('DB_PASSWORD') ?: '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
$a = $connect();
$b = $connect();
if ((int) $a->query("SELECT current_setting('server_version_num')")->fetchColumn() < 180000) {
    throw new RuntimeException('PostgreSQL 18 is mandatory.');
}

$company = '00000000-0000-4000-8000-000000000401';
$fiscal = '00000000-0000-4000-8000-000000000402';
$period = '00000000-0000-4000-8000-000000000403';
$account = '00000000-0000-4000-8000-000000000404';
$draft = '00000000-0000-4000-8000-000000000405';
$historical = '00000000-0000-4000-8000-000000000406';
$lineA = '00000000-0000-4000-8000-000000000407';
$lineB = '00000000-0000-4000-8000-000000000408';
$lineC = '00000000-0000-4000-8000-000000000409';
$lineD = '00000000-0000-4000-8000-00000000040a';

$assert = static function (bool $condition, string $message): void {
    if (! $condition) {
        throw new RuntimeException($message);
    }
};
$rejects = static function (PDO $conn, string $sql, array $states) use ($assert): void {
    $conn->beginTransaction();
    try {
        $conn->exec("SET LOCAL lock_timeout = '300ms'");
        $conn->exec($sql);
    } catch (PDOException $e) {
        $conn->rollBack();
        $assert(in_array($e->getCode(), $states, true),
            'Unexpected SQLSTATE '.$e->getCode().' for '.$sql.': '.$e->getMessage());
        return;
    } catch (Throwable $e) {
        $conn->rollBack();
        throw $e;
    }
    $conn->rollBack();
    throw new RuntimeException('Expected SQLSTATE '.implode('/', $states).': '.$sql);
};

// Test-only DDL is transactionally rolled back on any error: it cannot leave
// disabled guards on failure. This is a fixture for historical data, NOT posting.
$guardedOwnerWrite = static function (PDO $conn, array $sql): void {
    $conn->beginTransaction();
    try {
        $conn->exec('ALTER TABLE public.journal_entries DISABLE TRIGGER p004_guard_journal_entries');
        $conn->exec('ALTER TABLE public.journal_lines DISABLE TRIGGER p004_guard_journal_lines');
        foreach ($sql as $statement) {
            $conn->exec($statement);
        }
        $conn->exec('ALTER TABLE public.journal_lines ENABLE TRIGGER p004_guard_journal_lines');
        $conn->exec('ALTER TABLE public.journal_entries ENABLE TRIGGER p004_guard_journal_entries');
        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollBack();
        throw $e;
    }
};
$seeded = false;
try {
    foreach (['p004_guard_journal_entry_mutation', 'p004_guard_journal_line_mutation'] as $name) {
        $statement = $a->prepare(<<<'SQL'
SELECT p.prosecdef, p.proconfig::text AS config,
       EXISTS (
           SELECT 1
           FROM aclexplode(coalesce(p.proacl, acldefault('f', p.proowner))) AS acl
           WHERE acl.grantee = 0 AND acl.privilege_type = 'EXECUTE'
       ) AS public_execute
FROM pg_proc AS p
JOIN pg_namespace AS n ON n.oid = p.pronamespace
WHERE n.nspname = 'public' AND p.proname = ?
SQL);
        $statement->execute([$name]);
        $fn = $statement->fetch();
        $assert((bool) $fn && ! $fn['prosecdef'] && ! $fn['public_execute']
            && str_contains($fn['config'], 'search_path=')
            && str_contains($fn['config'], 'pg_catalog'),
            "Function security contract failed: {$name}");
    }
    foreach (['p004_guard_journal_entries', 'p004_guard_journal_lines'] as $name) {
        $stmt = $a->prepare("SELECT tgenabled FROM pg_trigger WHERE tgname = ?");
        $stmt->execute([$name]);
        $assert($stmt->fetchColumn() === 'O', "Guard trigger absent or disabled: {$name}");
    }

    $a->exec("INSERT INTO currencies(code,name) VALUES('EGP','Synthetic EGP')
        ON CONFLICT (code) DO NOTHING");
    $a->exec("INSERT INTO companies(id,code,legal_name,country_code,base_currency_code,timezone,status,created_at,updated_at)
        VALUES('{$company}','P004-RACE-EG','Synthetic Egypt','EG','EGP','UTC','active',now(),now())");
    $seeded = true;
    $a->exec("INSERT INTO fiscal_years(id,company_id,label,starts_on,ends_on,state,created_at,updated_at)
        VALUES('{$fiscal}','{$company}','P004-RACE-FY','2026-01-01','2026-12-31','open',now(),now())");
    $a->exec("INSERT INTO accounting_periods(id,company_id,fiscal_year_id,starts_on,ends_on,state,created_at,updated_at)
        VALUES('{$period}','{$company}','{$fiscal}','2026-01-01','2026-03-31','open',now(),now())");
    $a->exec("INSERT INTO ledger_accounts(id,company_id,code,name,account_type,normal_balance,active,created_at,updated_at)
        VALUES('{$account}','{$company}','P004-CASH','Synthetic cash','asset','debit',true,now(),now())");
    $a->exec("INSERT INTO journal_entries(id,company_id,period_id,entry_date,status,created_at,updated_at)
        VALUES('{$draft}','{$company}','{$period}','2026-02-01','draft',now(),now()),
              ('{$historical}','{$company}','{$period}','2026-02-02','draft',now(),now())");
    $a->exec("INSERT INTO journal_lines(id,company_id,entry_id,account_id,line_no,debit_base,credit_base)
        VALUES('{$lineA}','{$company}','{$draft}','{$account}',1,10.0000,0),
              ('{$lineB}','{$company}','{$draft}','{$account}',2,0,10.0000),
              ('{$lineC}','{$company}','{$historical}','{$account}',3,5.0000,0)");

    // The normal path is fail-closed even with a balanced journal.
    $rejects($a, "UPDATE journal_entries SET status='posted',posted_at=now() WHERE id='{$draft}'", ['23514']);
    $rejects($a, "UPDATE journal_entries SET status='reversed',posted_at=now() WHERE id='{$draft}'", ['23514']);
    $rejects($b, "INSERT INTO journal_entries(id,company_id,period_id,entry_date,status,posted_at,created_at,updated_at)
        VALUES('{$lineD}','{$company}','{$period}','2026-02-03','posted',now(),now(),now())", ['23514']);

    // A holds the header row lock; B's INSERT/UPDATE/DELETE/reparent each
    // must wait on it and time out. Real independent connections and transactions.
    $a->beginTransaction();
    $a->exec("UPDATE journal_entries SET description='uncommitted' WHERE id='{$draft}'");
    foreach ([
        "UPDATE journal_lines SET debit_base=12 WHERE id='{$lineA}'",
        "DELETE FROM journal_lines WHERE id='{$lineB}'",
        "INSERT INTO journal_lines(id,company_id,entry_id,account_id,line_no,debit_base,credit_base)
             VALUES('{$lineD}','{$company}','{$draft}','{$account}',4,2,0)",
        "UPDATE journal_lines SET entry_id='{$draft}' WHERE id='{$lineC}'",
    ] as $sql) {
        $rejects($b, $sql, ['55P03']);
    }
    $a->rollBack();
    $assert($a->query("SELECT description FROM journal_entries WHERE id='{$draft}'")->fetchColumn() === null,
        'Header rollback was not atomic.');
    $assert($b->query("SELECT debit_base::text FROM journal_lines WHERE id='{$lineA}'")->fetchColumn() === '10.0000',
        'Blocked line edit leaked into committed data.');

    // Reparent opposing directions with ordered parent locks: no deadlock;
    // competing client times out and succeeds when first transaction rolls back.
    $a->beginTransaction();
    $a->exec("UPDATE journal_lines SET entry_id='{$historical}' WHERE id='{$lineA}'");
    $rejects($b, "UPDATE journal_lines SET entry_id='{$draft}' WHERE id='{$lineC}'", ['55P03']);
    $a->rollBack();
    $b->beginTransaction();
    $b->exec("UPDATE journal_lines SET entry_id='{$draft}' WHERE id='{$lineC}'");
    $b->rollBack();
    $assert($a->query("SELECT entry_id FROM journal_lines WHERE id='{$lineC}'")->fetchColumn() === $historical,
        'Reparent rollback changed committed parent.');

    // Represent a *pre-existing* posted row only via a synthetic migration-owner
    // fixture. The public runtime path still cannot transition a draft to posted.
    $guardedOwnerWrite($a, [
        "UPDATE journal_entries SET status='posted',posted_at=now() WHERE id='{$historical}'",
    ]);
    foreach ([
        "UPDATE journal_entries SET description='changed' WHERE id='{$historical}'",
        "DELETE FROM journal_entries WHERE id='{$historical}'",
        "UPDATE journal_lines SET debit_base=6 WHERE id='{$lineC}'",
        "DELETE FROM journal_lines WHERE id='{$lineC}'",
        "INSERT INTO journal_lines(id,company_id,entry_id,account_id,line_no,debit_base,credit_base)
             VALUES('{$lineD}','{$company}','{$historical}','{$account}',4,2,0)",
        "UPDATE journal_lines SET entry_id='{$historical}' WHERE id='{$lineA}'",
        "UPDATE journal_lines SET entry_id='{$draft}' WHERE id='{$lineC}'",
    ] as $sql) {
        $rejects($b, $sql, ['23514']);
    }
    $assert($a->query("SELECT status FROM journal_entries WHERE id='{$historical}'")->fetchColumn() === 'posted',
        'Historical posted journal changed.');
    $assert($a->query("SELECT count(*) FROM journal_lines WHERE entry_id='{$historical}'")->fetchColumn() == 1,
        'Posted journal lines were mutated.');

    fwrite(STDOUT, "PASS P0-04A: PostgreSQL 18 two-client locks, no blocked writes, ordered reparent, rollback, posted immutability, trigger/function metadata; port ".(getenv('DB_PORT') ?: '5432').".\n");
} finally {
    if ($a->inTransaction()) { $a->rollBack(); }
    if ($b->inTransaction()) { $b->rollBack(); }
    if ($seeded) {
        $guardedOwnerWrite($a, [
            "DELETE FROM journal_lines WHERE company_id='{$company}'",
            "DELETE FROM journal_entries WHERE company_id='{$company}'",
            "DELETE FROM ledger_accounts WHERE company_id='{$company}'",
            "DELETE FROM accounting_periods WHERE company_id='{$company}'",
            "DELETE FROM fiscal_years WHERE company_id='{$company}'",
            "DELETE FROM companies WHERE id='{$company}'",
        ]);
    }
}
