<?php

declare(strict_types=1);

// Disposable rights *simulation*, NOT a test of the real (unconfigured)
// application login. Owner CI role is privileged; never run on real data.
if (getenv('APP_ENV') !== 'testing' || getenv('DB_DATABASE') !== 'elmo_erp_test'
    || getenv('DB_PORT') !== '5432') {
    fwrite(STDERR, "REFUSED: direct, synthetic PostgreSQL 18 only.\n");
    exit(2);
}
$pdo = new PDO(sprintf('pgsql:host=%s;port=5432;dbname=elmo_erp_test',
    getenv('DB_HOST') ?: '127.0.0.1'),
    getenv('DB_USERNAME') ?: 'elmo', getenv('DB_PASSWORD') ?: '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
$mustDeny = static function (string $sql) use ($pdo): void {
    $pdo->exec('SAVEPOINT p004_acl_test');
    try {
        $pdo->exec($sql);
        throw new RuntimeException('Synthetic restricted role unexpectedly executed: '.$sql);
    } catch (PDOException $error) {
        if ($error->getCode() !== '42501') {
            throw $error;
        }
        $pdo->exec('ROLLBACK TO SAVEPOINT p004_acl_test');
    }
};
$role = 'p004_composition_acl_probe';
$pdo->beginTransaction();
try {
    // The CREATE ROLE and all grants are transactionally rolled back.
    $pdo->exec('CREATE ROLE '.$role.' NOLOGIN');
    $pdo->exec('GRANT USAGE ON SCHEMA public TO '.$role);
    $pdo->exec('GRANT SELECT, INSERT, UPDATE, DELETE ON public.journal_entries, public.journal_lines TO '.$role);
    $pdo->exec('SET LOCAL ROLE '.$role);
    if ($pdo->query("SELECT current_user")->fetchColumn() !== $role) {
        throw new RuntimeException('Synthetic SET LOCAL ROLE not active.');
    }
    foreach (['journal_entries', 'journal_lines'] as $table) {
        $stmt = $pdo->prepare('SELECT has_table_privilege(current_user, ?::regclass, ?)');
        $stmt->execute(['public.'.$table, 'TRUNCATE']);
        if ((bool) $stmt->fetchColumn()) {
            throw new RuntimeException('Probe role unexpectedly has TRUNCATE on '.$table);
        }
        $mustDeny('TRUNCATE TABLE public.'.$table);
        $mustDeny('ALTER TABLE public.'.$table.' DISABLE TRIGGER ALL');
    }
    // The runtime credential is NOT defined or tested here; this only proves
    // a restricted role can be denied explicit TRUNCATE / DDL privileges.
    echo "PASS: disposable synthetic role denied TRUNCATE and trigger-disabling DDL (42501).
Actual runtime role/owner ACL: NOT VERIFIED.\n";
} finally {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
}
