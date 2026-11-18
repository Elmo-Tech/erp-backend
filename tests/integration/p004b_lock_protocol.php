<?php

declare(strict_types=1);

/**
 * P0-04B disposable two-session lock tests, direct PG18 or PgBouncer.
 * This DOES NOT execute a posting transition or grant authorization.
 * No SELECT FOR UPDATE on journal_lines is allowed.
 */
if (getenv('APP_ENV') !== 'testing' || getenv('DB_DATABASE') !== 'elmo_erp_test'
    || ! in_array(getenv('DB_PORT'), ['5432', '16432'], true)) {
    fwrite(STDERR, "REFUSED: synthetic PG18/PgBouncer test database required.\n");
    exit(2);
}

$dsn = sprintf('pgsql:host=%s;port=%s;dbname=elmo_erp_test',
    getenv('DB_HOST') ?: '127.0.0.1', getenv('DB_PORT'));
$connect = static fn (): PDO => new PDO($dsn,
    getenv('DB_USERNAME') ?: 'elmo', getenv('DB_PASSWORD') ?: '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => true,
    ]);
$a = $connect();
$b = $connect();
$prefix = getenv('DB_PORT') === '16432' ? 'b' : 'a';
$eg = "00000000-0000-4000-8000-00000000{$prefix}101";
$it = "00000000-0000-4000-8000-00000000{$prefix}102";
$fy = "00000000-0000-4000-8000-00000000{$prefix}103";
$period = "00000000-0000-4000-8000-00000000{$prefix}104";
$account = "00000000-0000-4000-8000-00000000{$prefix}105";
$journal = "00000000-0000-4000-8000-00000000{$prefix}106";
$line1 = "00000000-0000-4000-8000-00000000{$prefix}107";
$line2 = "00000000-0000-4000-8000-00000000{$prefix}108";

$mustTimeout = static function (PDO $db, string $sql): void {
    $db->beginTransaction();
    try {
        $db->exec("SET LOCAL lock_timeout = '250ms'");
        $db->exec($sql);
        throw new RuntimeException('Expected a lock timeout; lock did not conflict.');
    } catch (PDOException $e) {
        if ($e->getCode() !== '55P03') {
            throw $e;
        }
    } finally {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
    }
};

try {
    $a->exec("INSERT INTO currencies(code,name) VALUES ('EGP','Synthetic EGP'),('EUR','Synthetic EUR') ON CONFLICT DO NOTHING");
    $a->exec("INSERT INTO companies(id,code,legal_name,country_code,base_currency_code,timezone,status,created_at,updated_at) VALUES
        ('{$eg}','P004B-{$prefix}-EG','Synthetic Egypt','EG','EGP','UTC','active',now(),now()),
        ('{$it}','P004B-{$prefix}-IT','Synthetic Italy','IT','EUR','UTC','active',now(),now())");
    $a->exec("INSERT INTO fiscal_years(id,company_id,label,starts_on,ends_on,state,created_at,updated_at)
        VALUES ('{$fy}','{$eg}','FY-2026','2026-01-01','2026-12-31','open',now(),now())");
    $a->exec("INSERT INTO accounting_periods(id,company_id,fiscal_year_id,starts_on,ends_on,state,created_at,updated_at)
        VALUES ('{$period}','{$eg}','{$fy}','2026-01-01','2026-03-31','open',now(),now())");
    $a->exec("INSERT INTO ledger_accounts(id,company_id,code,name,account_type,normal_balance,active,created_at,updated_at)
        VALUES ('{$account}','{$eg}','SYN','Synthetic','asset','debit',true,now(),now())");
    $a->exec("INSERT INTO journal_entries(id,company_id,period_id,entry_date,status,created_at,updated_at)
        VALUES ('{$journal}','{$eg}','{$period}','2026-02-15','draft',now(),now())");
    $a->exec("INSERT INTO journal_lines(id,company_id,entry_id,account_id,line_no,debit_base,credit_base) VALUES
        ('{$line1}','{$eg}','{$journal}','{$account}',1,10,0),
        ('{$line2}','{$eg}','{$journal}','{$account}',2,0,10)");

    // Header is taken first. Validation never locks child row tuples.
    $a->beginTransaction();
    $a->exec("SELECT id FROM journal_entries WHERE company_id='{$eg}' AND id='{$journal}' FOR UPDATE");
    $mustTimeout($b, "SELECT id FROM journal_entries WHERE company_id='{$eg}' AND id='{$journal}' FOR UPDATE");
    $a->rollBack();

    // A concurrent period closure blocks the validator's FOR SHARE recheck.
    $a->beginTransaction();
    $a->exec("UPDATE accounting_periods SET state='closed',locked_at=now() WHERE id='{$period}'");
    $mustTimeout($b, "SELECT id FROM accounting_periods WHERE company_id='{$eg}' AND id='{$period}' FOR SHARE");
    $a->rollBack();
    $state = $b->query("SELECT state FROM accounting_periods WHERE id='{$period}'")->fetchColumn();
    if ($state !== 'open') {
        throw new RuntimeException('Period change survived rollback.');
    }

    // Concurrent account deactivation blocks the account shared lock.
    $a->beginTransaction();
    $a->exec("UPDATE ledger_accounts SET active=false WHERE id='{$account}'");
    $mustTimeout($b, "SELECT id FROM ledger_accounts WHERE company_id='{$eg}' AND id='{$account}' FOR SHARE");
    $a->rollBack();
    if ($b->query("SELECT active FROM ledger_accounts WHERE id='{$account}'")->fetchColumn() !== true
        && $b->query("SELECT active FROM ledger_accounts WHERE id='{$account}'")->fetchColumn() !== 't') {
        throw new RuntimeException('Account change survived rollback.');
    }

    // Exact NUMERIC aggregation is stable; no floating point.
    $aggregate = $b->query("SELECT SUM(debit_base)-SUM(credit_base) FROM journal_lines
        WHERE company_id='{$eg}' AND entry_id='{$journal}'")->fetchColumn();
    if (! in_array($aggregate, ['0', '0.0000'], true)) {
        throw new RuntimeException('Unexpected NUMERIC imbalance.');
    }
    echo "PASS: direct/pooled two-session header/period/account contention, rollback and exact SQL amounts.\n";
} finally {
    if ($a->inTransaction()) { $a->rollBack(); }
    if ($b->inTransaction()) { $b->rollBack(); }
    $a->exec("DELETE FROM journal_lines WHERE entry_id='{$journal}'");
    $a->exec("DELETE FROM journal_entries WHERE id='{$journal}'");
    $a->exec("DELETE FROM ledger_accounts WHERE id='{$account}'");
    $a->exec("DELETE FROM accounting_periods WHERE id='{$period}'");
    $a->exec("DELETE FROM fiscal_years WHERE id='{$fy}'");
    $a->exec("DELETE FROM companies WHERE id IN ('{$eg}','{$it}')");
}
