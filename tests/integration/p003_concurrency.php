<?php

declare(strict_types=1);

// P0-03: disposable PostgreSQL 18 two-connection race/worker-context smoke.
// NEVER run against staging/production or a database holding real accounts.
if (getenv('APP_ENV') !== 'testing' || getenv('DB_DATABASE') !== 'elmo_erp_test') {
    fwrite(STDERR, "REFUSED: concurrency harness requires APP_ENV=testing and elmo_erp_test.\n");
    exit(2);
}

$dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s',
    getenv('DB_HOST') ?: '127.0.0.1', getenv('DB_PORT') ?: '5432', getenv('DB_DATABASE'));
$user = getenv('DB_USERNAME') ?: 'elmo';
$password = getenv('DB_PASSWORD') ?: '';
$connect = static function () use ($dsn, $user, $password): PDO {
    return new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
};
$a = $connect();
$b = $connect();

$eg = '00000000-0000-4000-8000-000000000061';
$it = '00000000-0000-4000-8000-000000000062';
$fyEg = '00000000-0000-4000-8000-000000000071';
$fyIt = '00000000-0000-4000-8000-000000000072';
$periodA = '00000000-0000-4000-8000-000000000081';
$periodB = '00000000-0000-4000-8000-000000000082';
$periodIt = '00000000-0000-4000-8000-000000000083';

$expectSqlstate = static function (callable $work, array $allowed): void {
    try {
        $work();
    } catch (PDOException $exception) {
        if (in_array($exception->getCode(), $allowed, true)) {
            return;
        }
        throw $exception;
    }
    throw new RuntimeException('Expected PostgreSQL integrity/lock failure; query succeeded.');
};

try {
    // Two persistent connections: transaction-local GUC must not cross to peer or later transaction.
    $a->beginTransaction();
    $a->exec("SELECT set_config('app.company_id', '{$eg}', true)");
    if ($a->query("SELECT current_setting('app.company_id',true)")->fetchColumn() !== $eg) {
        throw new RuntimeException('Connection A missing its own transaction context.');
    }
    if ($b->query("SELECT current_setting('app.company_id',true)")->fetchColumn() !== null) {
        throw new RuntimeException('Company context unexpectedly leaked to connection B.');
    }
    $a->commit();
    $a->beginTransaction();
    if ($a->query("SELECT nullif(current_setting('app.company_id',true),'')")->fetchColumn() !== null) {
        throw new RuntimeException('Connection A reused previous company context.');
    }
    $a->rollBack();

    $a->exec("INSERT INTO currencies(code,name) VALUES('EGP','Synthetic EGP'),('EUR','Synthetic EUR') ON CONFLICT(code) DO NOTHING");
    $a->exec("INSERT INTO companies(id,code,legal_name,country_code,base_currency_code,timezone,status,created_at,updated_at) VALUES
      ('{$eg}','RACE-EG','Synthetic Egypt','EG','EGP','UTC','active',now(),now()),
      ('{$it}','RACE-IT','Synthetic Italy','IT','EUR','UTC','active',now(),now())");
    $a->exec("INSERT INTO fiscal_years(id,company_id,label,starts_on,ends_on,state,created_at,updated_at) VALUES
      ('{$fyEg}','{$eg}','FY-2026','2026-01-01','2026-12-31','open',now(),now()),
      ('{$fyIt}','{$it}','FY-2026','2026-01-01','2026-12-31','open',now(),now())");

    // Both writers attempt the exact same EG period through separate physical sessions.
    $a->beginTransaction();
    $a->exec("INSERT INTO accounting_periods(id,company_id,fiscal_year_id,starts_on,ends_on,state,created_at,updated_at)
      VALUES('{$periodA}','{$eg}','{$fyEg}','2026-01-01','2026-03-31','open',now(),now())");
    $b->beginTransaction();
    $b->exec("SET LOCAL lock_timeout = '250ms'");
    $expectSqlstate(static function () use ($b, $periodB, $eg, $fyEg): void {
        $b->exec("INSERT INTO accounting_periods(id,company_id,fiscal_year_id,starts_on,ends_on,state,created_at,updated_at)
          VALUES('{$periodB}','{$eg}','{$fyEg}','2026-02-01','2026-04-30','open',now(),now())");
    }, ['55P03']);
    $b->rollBack();
    $a->commit();

    $expectSqlstate(static function () use ($b, $periodB, $eg, $fyEg): void {
        $b->exec("INSERT INTO accounting_periods(id,company_id,fiscal_year_id,starts_on,ends_on,state,created_at,updated_at)
          VALUES('{$periodB}','{$eg}','{$fyEg}','2026-02-01','2026-04-30','open',now(),now())");
    }, ['23P01']);

    // Same dates for a DISTINCT legal company are legal.
    $b->exec("INSERT INTO accounting_periods(id,company_id,fiscal_year_id,starts_on,ends_on,state,created_at,updated_at)
      VALUES('{$periodIt}','{$it}','{$fyIt}','2026-01-01','2026-03-31','open',now(),now())");

    fwrite(STDOUT, "PASS: two physical PostgreSQL sessions; transaction context cleared; exclusion lock, post-commit denial, IT independent calendar.\n");
} finally {
    if ($a->inTransaction()) { $a->rollBack(); }
    if ($b->inTransaction()) { $b->rollBack(); }
    $a->exec("DELETE FROM accounting_periods WHERE company_id IN ('{$eg}', '{$it}')");
    $a->exec("DELETE FROM fiscal_years WHERE company_id IN ('{$eg}', '{$it}')");
    $a->exec("DELETE FROM companies WHERE id IN ('{$eg}', '{$it}')");
}
