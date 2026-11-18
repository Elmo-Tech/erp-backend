<?php

declare(strict_types=1);

// Raw PDO INSERT/UPDATE acceptance/rejection against direct PostgreSQL 18
// and the real PgBouncer transaction pool. Disposable synthetic database only.
if (getenv('APP_ENV') !== 'testing' || getenv('DB_DATABASE') !== 'elmo_erp_test'
    || ! in_array(getenv('DB_PORT'), ['5432', '16432'], true)) {
    fwrite(STDERR, "REFUSED: disposable PG18/PgBouncer environment required.\n");
    exit(2);
}

$port = getenv('DB_PORT');
$dsn = sprintf('pgsql:host=%s;port=%s;dbname=elmo_erp_test',
    getenv('DB_HOST') ?: '127.0.0.1', $port);
$connection = static fn (): PDO => new PDO($dsn,
    getenv('DB_USERNAME') ?: 'elmo', getenv('DB_PASSWORD') ?: '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => true, // pooled transaction-compatible
    ]);
$a = $connection();
$b = $connection();
$prefix = $port === '16432' ? 'e' : 'd';
$id = static fn (int $suffix): string => sprintf('00000000-0000-4000-8000-00000000%s%03d', $prefix, $suffix);
$eg = $id(101);
$it = $id(102);
$fyEg = $id(103);
$fyIt = $id(104);
$periodEg = $id(105);
$periodIt = $id(106);
$accountEg = $id(107);
$accountIt = $id(108);
$entryEg = $id(109);
$entryIt = $id(110);
$validEg = $id(111);
$validIt = $id(112);
$invalid = $id(113);
$check = static function (bool $ok, string $context): void {
    if (! $ok) {
        throw new RuntimeException('P0-04-FX PDO test failed: '.$context);
    }
};
$exec = static function (PDO $pdo, string $sql, array $params): void {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
};
$insertSql = <<<'SQL'
INSERT INTO journal_lines
(id,company_id,entry_id,account_id,line_no,debit_base,credit_base,
transaction_currency,amount_currency,fx_rate_snapshot)
VALUES (?,?,?,?,?,10.0000,0,?,?::numeric,?::numeric)
SQL;
$updateSql = <<<'SQL'
UPDATE journal_lines SET transaction_currency=?, amount_currency=?::numeric,
fx_rate_snapshot=?::numeric WHERE id=?
SQL;
$reject = static function (PDO $pdo, string $state, callable $work) use ($check): void {
    $pdo->beginTransaction();
    try {
        $work();
        $pdo->rollBack();
        throw new RuntimeException('SQL unexpectedly accepted invalid FX data.');
    } catch (PDOException $e) {
        $pdo->rollBack();
        $check($e->getCode() === $state,
            'expected SQLSTATE '.$state.', got '.$e->getCode());
    }
};

try {
    $catalog = $a->query(<<<'SQL'
SELECT conname, CASE WHEN convalidated THEN 1 ELSE 0 END AS valid
FROM pg_constraint WHERE conrelid='journal_lines'::regclass
 AND conname IN ('p003_currency_snapshot_chk', 'p004_fx_snapshot_complete_chk')
SQL)->fetchAll();
    $check(count($catalog) === 2, 'both old and new CHECK constraints must exist');
    foreach ($catalog as $row) {
        $check((int) $row['valid'] === 1, $row['conname'].' not validated');
    }

    $a->beginTransaction();
    $a->exec("INSERT INTO currencies(code,name) VALUES('EGP','Synthetic EGP'),('EUR','Synthetic EUR') ON CONFLICT DO NOTHING");
    $exec($a, "INSERT INTO companies(id,code,legal_name,country_code,base_currency_code,timezone,status,created_at,updated_at)
        VALUES (? ,?,'Synthetic EG','EG','EGP','UTC','active',now(),now()),
               (? ,?,'Synthetic IT','IT','EUR','UTC','active',now(),now())",
        [$eg, 'FX-SQL-'.$prefix.'-EG', $it, 'FX-SQL-'.$prefix.'-IT']);
    $exec($a, "INSERT INTO fiscal_years(id,company_id,label,starts_on,ends_on,state,created_at,updated_at)
        VALUES (?,?,'FX-SQL','2026-01-01','2026-12-31','open',now(),now()),
               (?,?,'FX-SQL','2026-01-01','2026-12-31','open',now(),now())",
        [$fyEg,$eg,$fyIt,$it]);
    $exec($a, "INSERT INTO accounting_periods(id,company_id,fiscal_year_id,starts_on,ends_on,state,created_at,updated_at)
        VALUES (?,?,?,'2026-01-01','2026-03-31','open',now(),now()),
               (?,?,?,'2026-01-01','2026-03-31','open',now(),now())",
        [$periodEg,$eg,$fyEg,$periodIt,$it,$fyIt]);
    $exec($a, "INSERT INTO ledger_accounts(id,company_id,code,name,account_type,normal_balance,active,created_at,updated_at)
        VALUES (?,?,'FX-SQL','Synthetic','asset','debit',true,now(),now()),
               (?,?,'FX-SQL','Synthetic','asset','debit',true,now(),now())",
        [$accountEg,$eg,$accountIt,$it]);
    $exec($a, "INSERT INTO journal_entries(id,company_id,period_id,entry_date,status,created_at,updated_at)
        VALUES (?,?,?,'2026-02-01','draft',now(),now()),
               (?,?,?,'2026-02-01','draft',now(),now())",
        [$entryEg,$eg,$periodEg,$entryIt,$it,$periodIt]);
    $a->commit();

    $fixtures = [
        [$eg, $entryEg, $accountEg, $validEg, 'EUR'],
        [$it, $entryIt, $accountIt, $validIt, 'EGP'],
    ];
    foreach ($fixtures as $n => [$company, $entry, $account, $valid, $currency]) {
        $client = $n === 0 ? $a : $b;
        $client->beginTransaction();
        $exec($client, $insertSql, [$valid, $company, $entry, $account, 1, null, null, null]);
        $client->commit();

        $client->beginTransaction();
        $exec($client, $updateSql, [$currency, '10.0000', '1.000000000000', $valid]);
        $client->commit();

        foreach ([
            [$currency, '10.0000', null],
            [$currency, null, '1.000000000000'],
            [$currency, null, null],
            [null, '10.0000', '1.000000000000'],
            [$currency, '0', '1'],
            [$currency, '10', '0'],
            [$currency, '-1', '1'],
            [$currency, '10', '-1'],
        ] as [$c, $amount, $rate]) {
            $reject($client, '23514',
                fn () => $exec($client, $insertSql,
                    [$invalid,$company,$entry,$account,2,$c,$amount,$rate]));
            $reject($client, '23514',
                fn () => $exec($client, $updateSql, [$c,$amount,$rate,$valid]));
        }
        $client->beginTransaction();
        $exec($client, $updateSql, [null,null,null,$valid]);
        $client->commit();

        // Rejected updates must not change the existing valid base-only tuple.
        $state = $b->prepare('SELECT transaction_currency,amount_currency,fx_rate_snapshot FROM journal_lines WHERE id=?');
        $state->execute([$valid]);
        $row = $state->fetch();
        $check($row && $row['transaction_currency'] === null
            && $row['amount_currency'] === null && $row['fx_rate_snapshot'] === null,
            'rejected update must leave valid line unchanged');
    }

    // P0-04A child trigger checks own-company parent *before* journal FK.
    $reject($a, '23514', fn () => $exec($a, $insertSql,
        [$invalid,$eg,$entryIt,$accountEg,2,null,null,null]));
    $reject($b, '23514', fn () => $exec($b, $insertSql,
        [$invalid,$it,$entryEg,$accountIt,2,null,null,null]));
    // Independently verify the composite account FK still emits 23503.
    $reject($a, '23503', fn () => $exec($a, $insertSql,
        [$invalid,$eg,$entryEg,$accountIt,2,null,null,null]));
    $reject($b, '23503', fn () => $exec($b, $insertSql,
        [$invalid,$it,$entryIt,$accountEg,2,null,null,null]));

    echo "PASS: {$port} raw PG18/PgBouncer inserts, updates, 23514, EG/IT FK isolation and validated pg_constraint.\n";
} finally {
    if ($a->inTransaction()) { $a->rollBack(); }
    if ($b->inTransaction()) { $b->rollBack(); }
    foreach ([
        ["DELETE FROM journal_lines WHERE company_id IN (?,?)", [$eg,$it]],
        ["DELETE FROM journal_entries WHERE company_id IN (?,?)", [$eg,$it]],
        ["DELETE FROM ledger_accounts WHERE company_id IN (?,?)", [$eg,$it]],
        ["DELETE FROM accounting_periods WHERE company_id IN (?,?)", [$eg,$it]],
        ["DELETE FROM fiscal_years WHERE company_id IN (?,?)", [$eg,$it]],
        ["DELETE FROM companies WHERE id IN (?,?)", [$eg,$it]],
    ] as [$sql, $params]) {
        $exec($a, $sql, $params);
    }
}
