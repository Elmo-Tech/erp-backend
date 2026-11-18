<?php
declare(strict_types=1);

/**
 * P0-04-ACL: disposable synthetic test, authentic password LOGIN.
 * seed/cleanup connect as migration owner. probe connects as elmo_runtime
 * with separate password, never SET ROLE. No production/staging/real data.
 */
if (getenv('APP_ENV') !== 'testing' || getenv('DB_DATABASE') !== 'elmo_erp_test') {
    fwrite(STDERR, "REFUSED: APP_ENV=testing and DB_DATABASE=elmo_erp_test required\n");
    exit(2);
}
set_exception_handler(static function (Throwable $e): void {
    fwrite(STDERR, 'FAIL P0-04-ACL: '.get_class($e).' '.$e->getMessage()."\n");
    exit(1);
});
$mode = $argv[1] ?? '';
if (!in_array($mode, ['seed', 'probe', 'worker-probe', 'cleanup'], true)) {
    throw new InvalidArgumentException('Usage: php p004_acl_runtime.php seed|probe|worker-probe|cleanup');
}
$ids = [
    'eg'=>'00000000-0000-4000-8000-000000000a01',
    'it'=>'00000000-0000-4000-8000-000000000a02',
    'fyeg'=>'00000000-0000-4000-8000-000000000a11',
    'fyit'=>'00000000-0000-4000-8000-000000000a12',
    'peg'=>'00000000-0000-4000-8000-000000000a21',
    'pit'=>'00000000-0000-4000-8000-000000000a22',
    'aeg'=>'00000000-0000-4000-8000-000000000a31',
    'ait'=>'00000000-0000-4000-8000-000000000a32',
    'jeg'=>'00000000-0000-4000-8000-000000000a41',
    'jit'=>'00000000-0000-4000-8000-000000000a42',
    'leg'=>'00000000-0000-4000-8000-000000000a51',
    'lit'=>'00000000-0000-4000-8000-000000000a52',
];
$connect = static function (string $principal): PDO {
    $runtime = $principal !== 'owner';
    $host = getenv($runtime ? 'RUNTIME_DB_HOST' : 'DB_HOST') ?: '127.0.0.1';
    $port = getenv($runtime ? 'RUNTIME_DB_PORT' : 'DB_PORT') ?: '5432';
    $user = $principal === 'web' ? 'elmo_runtime' : ($principal === 'worker' ? 'elmo_worker' : (getenv('DB_USERNAME') ?: 'elmo'));
    $password = getenv($principal === 'web' ? 'RUNTIME_DB_PASSWORD' : ($principal === 'worker' ? 'WORKER_DB_PASSWORD' : 'DB_PASSWORD'));
    if ($password === false || $password === '') {
        throw new RuntimeException('Missing test password');
    }
    return new PDO("pgsql:host={$host};port={$port};dbname=elmo_erp_test", $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => true,
    ]);
};
$check = static function (bool $ok, string $reason): void {
    if (!$ok) { throw new RuntimeException($reason); }
};
$expectDenied = static function (PDO $db, string $sql, string $label): void {
    try {
        $db->exec($sql);
    } catch (PDOException $e) {
        if ($e->getCode() === '42501') { return; }
        throw new RuntimeException("{$label} expected 42501, got ".$e->getCode(), previous: $e);
    }
    throw new RuntimeException("{$label} unexpectedly succeeded");
};
$owner = in_array($mode, ['seed', 'cleanup'], true) ? $connect('owner') : null;
if ($mode === 'seed') {
    $owner->beginTransaction();
    foreach ([['EGP','Synthetic EGP'],['EUR','Synthetic EUR']] as [$code,$name]) {
        $stmt=$owner->prepare('INSERT INTO currencies(code,name) VALUES(?,?) ON CONFLICT(code) DO NOTHING');
        $stmt->execute([$code,$name]);
    }
    foreach (['eg'=>['EG','EGP'],'it'=>['IT','EUR']] as $key=>[$country,$currency]) {
        $company=$ids[$key]; $fy=$ids['fy'.$key]; $period=$ids['p'.$key]; $account=$ids['a'.$key];
        $s=$owner->prepare("INSERT INTO companies(id,code,legal_name,country_code,base_currency_code,timezone,status,created_at,updated_at)
          VALUES(?,?,'Synthetic ACL company',?,?,'UTC','active',now(),now())");
        $s->execute([$company,'ACL-'.strtoupper($key),$country,$currency]);
        $s=$owner->prepare("INSERT INTO fiscal_years(id,company_id,label,starts_on,ends_on,state,created_at,updated_at)
          VALUES(?,?,'ACL-2026','2026-01-01','2026-12-31','open',now(),now())");
        $s->execute([$fy,$company]);
        $s=$owner->prepare("INSERT INTO accounting_periods(id,company_id,fiscal_year_id,starts_on,ends_on,state,created_at,updated_at)
          VALUES(?,?,?,'2026-01-01','2026-03-31','open',now(),now())");
        $s->execute([$period,$company,$fy]);
        $s=$owner->prepare("INSERT INTO ledger_accounts(id,company_id,code,name,account_type,normal_balance,active,created_at,updated_at)
          VALUES(?,?,'ACL-CASH','Synthetic cash','asset','debit',true,now(),now())");
        $s->execute([$account,$company]);
    }
    $owner->commit();
    echo "PASS: P0-04-ACL synthetic EG/IT prerequisites seeded by owner\n";
    exit;
}
if ($mode === 'cleanup') {
    $owner->beginTransaction();
    foreach (['journal_lines','journal_entries','ledger_accounts','accounting_periods','fiscal_years','companies'] as $table) {
        $sql="DELETE FROM {$table} WHERE company_id IN (?,?)";
        if ($table === 'companies') { $sql="DELETE FROM companies WHERE id IN (?,?)"; }
        $owner->prepare($sql)->execute([$ids['eg'],$ids['it']]);
    }
    $owner->commit();
    echo "PASS: P0-04-ACL synthetic EG/IT fixtures cleaned by owner\n";
    exit;
}
$db=$connect($mode === 'worker-probe' ? 'worker' : 'web');
$identity=$db->query("SELECT current_user,session_user")->fetch();
$expected = $mode === 'worker-probe' ? 'elmo_worker' : 'elmo_runtime';
$check($identity['current_user']===$expected && $identity['session_user']===$expected,
    'Not a real independent expected principal LOGIN');
$flags=$db->query("SELECT rolsuper,rolcreatedb,rolcreaterole,rolreplication,rolbypassrls,rolinherit
  FROM pg_roles WHERE rolname=current_user")->fetch();
foreach (['rolsuper','rolcreatedb','rolcreaterole','rolreplication','rolbypassrls','rolinherit'] as $flag) {
    $check(in_array($flags[$flag], [false,'f','0',0],true),"Unsafe role flag {$flag}");
}
$ownership=(int)$db->query("SELECT count(*) FROM pg_class WHERE relnamespace='public'::regnamespace
 AND relkind IN ('r','p','S','v','m','f') AND pg_get_userbyid(relowner)=current_user")->fetchColumn();
$check($ownership===0,'Runtime owns a schema object');
$members=(int)$db->query("SELECT count(*) FROM pg_auth_members WHERE member=(SELECT oid FROM pg_roles WHERE rolname=current_user)")->fetchColumn();
$check($members===0,'Runtime has forbidden role memberships');
$executable=(int)$db->query("SELECT count(*) FROM pg_proc
 WHERE pronamespace='public'::regnamespace
   AND has_function_privilege(current_user,oid,'EXECUTE')")->fetchColumn();
$expectedFunctions=$mode==='worker-probe' ? 0 : 3;
$check($executable===$expectedFunctions, "Unexpected executable public functions: {$executable}");

$check($db->query("SELECT pg_has_role(current_user,'elmo','MEMBER')")->fetchColumn() === false
 || $db->query("SELECT pg_has_role(current_user,'elmo','MEMBER')")->fetchColumn() === 'f',
 'Runtime is a member of migration owner role');
foreach (['journal_entries','journal_lines'] as $table) {
    foreach (['TRUNCATE','REFERENCES','TRIGGER'] as $priv) {
        $check(in_array($db->query("SELECT has_table_privilege(current_user,'public.{$table}','{$priv}')")->fetchColumn(), [false,'f','0',0], true),
            "Unexpected {$priv} privilege on {$table}");
    }
}
$check(in_array($db->query("SELECT has_database_privilege(current_user,current_database(),'CREATE')")->fetchColumn(), [false,'f','0',0], true),
    'Runtime has database CREATE');
$check(in_array($db->query("SELECT has_database_privilege(current_user,current_database(),'TEMP')")->fetchColumn(), [false,'f','0',0], true),
    'Runtime has TEMP');
$check(in_array($db->query("SELECT has_schema_privilege(current_user,'public','CREATE')")->fetchColumn(), [false,'f','0',0], true),
    'Runtime has schema CREATE');
$expectDenied($db,'TRUNCATE public.journal_lines','TRUNCATE');
$expectDenied($db,'ALTER TABLE public.journal_entries DISABLE TRIGGER ALL','trigger-disable');
$expectDenied($db,'ALTER TABLE public.journal_entries ADD COLUMN acl_unsafe integer','ALTER TABLE');
$expectDenied($db,'DROP TABLE public.journal_lines','DROP TABLE');
$expectDenied($db,'CREATE TABLE public.acl_unsafe (id int)','schema DDL');
$expectDenied($db,'CREATE TEMP TABLE acl_unsafe (id int)','TEMP DDL');
$expectDenied($db,'CREATE SCHEMA acl_unsafe','database DDL');
$expectDenied($db,'SET ROLE elmo','owner impersonation');
echo "PASS: authentic LOGIN, role flags, ownership, TRUNCATE, trigger-disable, DDL and owner impersonation forbidden\n";
if ($mode === 'probe') {
    foreach (['users', 'user_mfa_recovery_codes', 'user_mfa_enrollment_authorizations',
        'audit_logs', 'outbox_events', 'idempotency_requests', 'migrations',
        'journal_entries', 'journal_lines'] as $table) {
        foreach (['INSERT', 'UPDATE', 'DELETE'] as $priv) {
            $check(in_array($db->query("SELECT has_table_privilege(current_user,'public.{$table}','{$priv}')")->fetchColumn(),
                [false,'f','0',0], true), "Web has prohibited {$priv} on {$table}");
        }
    }
    $expectDenied($db, "UPDATE public.users SET mfa_secret='unsafe' WHERE id=-1", 'raw MFA factor update');
    $expectDenied($db, "DELETE FROM public.audit_logs WHERE false", 'audit deletion');
    $expectDenied($db, "INSERT INTO public.user_mfa_enrollment_authorizations(user_id,approved_reference,expires_at) VALUES(-1,'forged',now())", 'trusted grant issuance');
    $check(in_array($db->query("SELECT has_table_privilege(current_user,'public.migrations','SELECT')")->fetchColumn(), [false,'f','0',0],true), 'migration metadata leaked');
    $check(in_array($db->query("SELECT has_table_privilege(current_user,'public.user_mfa_recovery_codes','SELECT')")->fetchColumn(), [false,'f','0',0],true), 'MFA digest read leaked');
    echo "PASS: Web sensitive tables deny DML, migration metadata and recovery digests are not readable\n";
    exit;
}
foreach (['starts_on', 'ends_on', 'state', 'locked_at', 'company_id'] as $column) {
    $check(in_array($db->query("SELECT has_column_privilege(current_user,'public.accounting_periods','{$column}','UPDATE')")->fetchColumn(),
        [false,'f','0',0], true), "Worker can change accounting_periods.{$column}");
}
$check(in_array($db->query("SELECT has_column_privilege(current_user,'public.accounting_periods','updated_at','UPDATE')")->fetchColumn(),
    [true,'t','1',1], true), 'Worker lacks scoped timestamp UPDATE required for P0-03 SELECT FOR SHARE');
foreach (['eg','it'] as $key) {
    $stmt=$db->prepare("INSERT INTO journal_entries(id,company_id,period_id,entry_date,status,description,created_at,updated_at)
        VALUES(?,?,?,'2026-02-01','draft','ACL draft',now(),now())");
    $stmt->execute([$ids['j'.$key],$ids[$key],$ids['p'.$key]]);
    $stmt=$db->prepare("INSERT INTO journal_lines(id,company_id,entry_id,account_id,line_no,debit_base,credit_base)
        VALUES(?,?,?,?,1,10.0000,0)");
    $stmt->execute([$ids['l'.$key],$ids[$key],$ids['j'.$key],$ids['a'.$key]]);
}
$db->prepare("UPDATE journal_lines SET debit_base=11.0000 WHERE id=?")->execute([$ids['leg']]);
$db->prepare("UPDATE journal_entries SET description='ACL updated draft' WHERE id=?")->execute([$ids['jeg']]);
try {
    $db->prepare("UPDATE journal_lines SET account_id=? WHERE id=?")->execute([$ids['ait'],$ids['leg']]);
    throw new RuntimeException('EG line accepted Italy account');
} catch (PDOException $e) {
    $check($e->getCode()==='23503','EG/IT cross-company FK expected 23503');
}
try {
    $db->prepare("UPDATE journal_lines SET account_id=? WHERE id=?")->execute([$ids['aeg'],$ids['lit']]);
    throw new RuntimeException('IT line accepted Egypt account');
} catch (PDOException $e) {
    $check($e->getCode()==='23503','IT/EG cross-company FK expected 23503');
}
$db->prepare("DELETE FROM journal_lines WHERE id=?")->execute([$ids['leg']]);
$db->prepare("DELETE FROM journal_entries WHERE id=?")->execute([$ids['jeg']]);
$check((int)$db->query("SELECT count(*) FROM journal_lines WHERE id='{$ids['lit']}'")->fetchColumn()===1,
    'Italy draft unexpectedly lost');
$guards=(int)$db->query("SELECT count(*) FROM pg_trigger WHERE tgname IN
 ('p004_guard_journal_entries','p004_guard_journal_lines') AND NOT tgisinternal AND tgenabled='O'")->fetchColumn();
if ($guards===2) {
    try {
        $db->prepare("UPDATE journal_entries SET status='posted',posted_at=now() WHERE id=?")->execute([$ids['jit']]);
        throw new RuntimeException('Guard-enabled posting unexpectedly accepted');
    } catch(PDOException $e) { $check($e->getCode()==='23514','Expected immutable guard 23514'); }
    echo "PASS: guard-present posting transition denied\n";
} elseif ($guards===0) {
    echo "NOT VERIFIED: P0-04A guards are unmerged; baseline cannot prove posted immutability\n";
} else {
    throw new RuntimeException('Partial P0-04A guard install');
}
$db->prepare("DELETE FROM journal_lines WHERE id=?")->execute([$ids['lit']]);
$db->prepare("DELETE FROM journal_entries WHERE id=?")->execute([$ids['jit']]);
echo "PASS: distinct runtime LOGIN can edit/delete synthetic drafts; EG/IT composite FKs deny cross-company changes\\n";
