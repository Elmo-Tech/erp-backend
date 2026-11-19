<?php
declare(strict_types=1);

/**
 * P0-04-ACL security regression: synthetic, owner-seeded users ONLY.
 * Prove whether one generic elmo_runtime SQL LOGIN can advance another
 * user's MFA replay state without a TOTP, Laravel user/session, or assurance.
 * Test always rolls back; discovery must FAIL CI, not count as protection.
 */
if (getenv('APP_ENV') !== 'testing' || getenv('DB_DATABASE') !== 'elmo_erp_test') {
    fwrite(STDERR, "REFUSED: synthetic test DB only\n");
    exit(2);
}
$password = getenv('RUNTIME_DB_PASSWORD');
if (!is_string($password) || $password === '') {
    fwrite(STDERR, "REFUSED: disposable runtime test credential absent\n");
    exit(2);
}
$db = new PDO(
    'pgsql:host=127.0.0.1;port=5432;dbname=elmo_erp_test',
    'elmo_runtime',
    $password,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);
if ($db->query('SELECT session_user')->fetchColumn() !== 'elmo_runtime') {
    throw new RuntimeException('A genuine restricted SQL LOGIN is required');
}
$db->beginTransaction();
try {
    $source = $db->query(
        "SELECT id FROM public.users WHERE email='acl-web-eg@example.invalid'"
    )->fetchColumn();
    $target = $db->query(
        "SELECT id, mfa_last_step, mfa_secret IS NOT NULL AS enrolled
         FROM public.users WHERE email='acl-web-mfa@example.invalid'"
    )->fetch();
    if (!$source || !$target || (string)$source === (string)$target['id']
        || $target['mfa_last_step'] !== null || !in_array($target['enrolled'], [true, 't', '1', 1], true)) {
        throw new RuntimeException('Missing distinct synthetic identities and fresh enrolled target');
    }
    // The SQL API has no authenticated-user or TOTP parameter; source identity is
    // deliberately NOT sent to the function, precisely the trust-boundary defect.
    $step = (int)$db->query(
        'SELECT floor(extract(epoch FROM clock_timestamp()) / 30)::bigint'
    )->fetchColumn();
    $stmt = $db->prepare('SELECT public.p004_consume_mfa_step(?::bigint, ?::bigint) AS ok');
    $stmt->execute([(int)$target['id'], $step]);
    $ok = $stmt->fetchColumn();
    $afterStmt = $db->prepare('SELECT mfa_last_step FROM public.users WHERE id=?');
    $afterStmt->execute([(int)$target['id']]);
    $after = $afterStmt->fetchColumn();
    $changed = $after !== null && (int)$after === $step;
    $accepted = in_array($ok, [true, 't', '1', 1], true);
} finally {
    // Never persist an artificial change to even the disposable fixture.
    if ($db->inTransaction()) {
        $db->rollBack();
    }
}
if ($accepted || $changed) {
    fwrite(STDERR, "SECURITY FAIL (CRITICAL): elmo_runtime advanced another synthetic user's mfa_last_step with a guessed current time step, NO TOTP and NO independent SQL identity proof; transaction ROLLED BACK.\n");
    exit(1);
}
echo "PASS: cross-user MFA step mutation denied by independently enforced DB proof\n";
