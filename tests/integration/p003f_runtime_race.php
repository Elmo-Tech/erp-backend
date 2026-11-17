<?php

declare(strict_types=1);

// Concurrency: same PostgreSQL/PgBouncer system, separate runtime and owner principals.
// Demonstrates that a revocation waits on context row locks; the next transaction denies.
if (getenv('APP_ENV') !== 'testing' || getenv('DB_DATABASE') !== 'elmo_erp_test'
    || getenv('DB_USERNAME') !== 'p003e_web' || getenv('DB_PORT') !== '16432'
    || getenv('DB_PGBOUNCER_TRANSACTION') !== 'true'
    || getenv('ERP_ROLE_PARTITION_REQUIRED') !== 'true') {
    fwrite(STDERR, "REFUSED: P0-03F runtime race requires actual disposable pooled runtime role.\n");
    exit(2);
}
if (! function_exists('pcntl_fork')) {
    throw new RuntimeException('pcntl required');
}
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Domain\Companies\AuthenticatedDatabaseContext;
use App\Models\WebUser;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

$eg = '00000000-0000-4000-8000-0000000000c1';
$it = '00000000-0000-4000-8000-0000000000c2';
$check = DB::selectOne("SELECT current_user AS actor, nullif(current_setting('app.company_id', true),'') AS tenant");
if ($check->actor !== 'p003e_web' || $check->tenant !== null) {
    throw new RuntimeException('Runtime role or pooled baseline GUC mismatch');
}
$egActor = WebUser::query()->where('email', 'p003c-eg@example.invalid')->firstOrFail();
$itActor = WebUser::query()->where('email', 'p003c-it@example.invalid')->firstOrFail();
$context = app(AuthenticatedDatabaseContext::class);
foreach ([[$egActor, $eg, 'P003C-EG'], [$itActor, $it, 'P003C-IT']] as [$actor, $company, $expected]) {
    $result = $context->run($actor, $company, 'company.view', function (string $authorized) {
        return DB::table('companies')->where('id', $authorized)->value('code');
    });
    if ($result !== $expected) {
        throw new RuntimeException('Explicitly scoped company read returned wrong tenant');
    }
}
foreach ([[$egActor, $it], [$itActor, $eg]] as [$actor, $foreign]) {
    try {
        $context->run($actor, $foreign, 'company.view', fn () => null);
        throw new RuntimeException('EG/IT foreign membership bypass');
    } catch (AuthorizationException) {
        // Expected.
    }
}
// A raw custom GUC write must not authorize the OTHER tenant through the wrapper.
DB::selectOne("SELECT set_config('app.company_id', ?, false)", [$it]);
try {
    $context->run($egActor, $it, 'company.view', fn () => null);
    throw new RuntimeException('Writable GUC authorized a foreign company');
} catch (AuthorizationException) {
    DB::selectOne("SELECT set_config('app.company_id', '', false)");
}
$signal = sys_get_temp_dir().'/p003f-revoke-ready-'.getmypid();
$finished = $signal.'.finished';
@unlink($signal);
@unlink($finished);
// Close inherited connections BEFORE forking: each process opens a fresh connection.
DB::disconnect();
$child = pcntl_fork();
if ($child === -1) {
    throw new RuntimeException('Could not fork revoker');
}
if ($child === 0) {
    try {
        $pdo = new PDO('pgsql:host=127.0.0.1;port=5432;dbname=elmo_erp_test',
            'elmo', getenv('P003C_OWNER_PASSWORD') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $ready = false;
        for ($i = 0; $i < 200; $i++) {
            if (file_exists($signal)) { $ready = true; break; }
            usleep(20000);
        }
        if (! $ready) { throw new RuntimeException('Timed out waiting for active context'); }
        $stmt = $pdo->prepare("UPDATE company_memberships SET status='revoked',updated_at=now() WHERE user_id=(SELECT id FROM users WHERE email='p003c-eg@example.invalid') AND company_id=?");
        $stmt->execute([$eg]);
        if ($stmt->rowCount() !== 1) { throw new RuntimeException('Revocation did not update one membership'); }
        file_put_contents($finished, 'revoked');
        exit(0);
    } catch (Throwable $e) {
        fwrite(STDERR, 'REVOKER: '.$e->getMessage()."\n");
        exit(1);
    }
}
try {
    // Reopen runtime connection after fork.
    $egActor = WebUser::query()->where('email', 'p003c-eg@example.invalid')->firstOrFail();
    $value = $context->run($egActor, $eg, 'company.view', function (string $authorized) use ($signal, $finished) {
        file_put_contents($signal, 'locked');
        usleep(700000);
        if (file_exists($finished)) {
            throw new RuntimeException('Revocation bypassed active membership row lock');
        }
        return DB::table('companies')->where('id', $authorized)->value('code');
    });
    if ($value !== 'P003C-EG') {
        throw new RuntimeException('In-flight authenticated read changed tenant');
    }
    pcntl_waitpid($child, $status);
    if (! pcntl_wifexited($status) || pcntl_wexitstatus($status) !== 0 || ! file_exists($finished)) {
        throw new RuntimeException('Concurrent owner revocation failed after transaction');
    }
    try {
        $context->run($egActor, $eg, 'company.view', fn () => null);
        throw new RuntimeException('Revoked actor executed a new transaction');
    } catch (AuthorizationException) {
        // Expected. Must re-check live membership; stale user object cannot authorize.
    }
    if ($context->run($itActor, $it, 'company.view', fn (string $id) => $id) !== $it) {
        throw new RuntimeException('Other tenant access broken after EG revocation');
    }
    if (DB::selectOne("SELECT nullif(current_setting('app.company_id', true),'') AS tenant")->tenant !== null) {
        throw new RuntimeException('Pooled transaction leaked GUC');
    }
    echo "PASS: runtime EG/IT scoped reads, forged GUC blocked by server authorization, racing owner revocation serialized, next transaction denied, no pooled GUC leak.\n";
} finally {
    @unlink($signal);
    @unlink($finished);
}
