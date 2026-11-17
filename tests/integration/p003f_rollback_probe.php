<?php

declare(strict_types=1);
if (getenv('APP_ENV') !== 'testing' || getenv('DB_DATABASE') !== 'elmo_erp_test'
    || getenv('DB_USERNAME') !== 'p003e_web'
    || getenv('ERP_ROLE_PARTITION_REQUIRED') !== 'true'
    || getenv('ERP_MFA_DB_CONNECTION') !== 'pgsql_mfa') {
    fwrite(STDERR, "REFUSED: partitioned disposable MFA rollback test only.\n");
    exit(2);
}
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Domain\Auth\MfaService;
use App\Models\WebUser;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

$user = WebUser::query()->where('email','p003f-rollback@example.invalid')->firstOrFail();
$mfa = new MfaService();
try {
    $mfa->confirmEnrollment($user,$mfa->makeSecret(),intdiv(now()->timestamp,30));
    throw new RuntimeException('Rollback test unexpectedly committed MFA.');
} catch (QueryException $e) {
    // Only the deliberate synthetic recovery trigger is acceptable here.
    if (! str_contains($e->getMessage(), 'P003F synthetic recovery failure')) {
        throw new RuntimeException('Unexpected MFA rollback error type.');
    }
    // Do not log exception SQL/bindings or any factor material.
}
$db=DB::connection('pgsql_mfa');
$grant=$db->table('user_mfa_enrollment_authorizations')->where('user_id',$user->id)->value('consumed_at');
$factor=$db->table('users')->where('id',$user->id)->first(['mfa_secret','mfa_last_step']);
$codes=$db->table('user_mfa_recovery_codes')->where('user_id',$user->id)->count();
if ($grant !== null || $factor->mfa_secret !== null || $factor->mfa_last_step !== null || $codes !== 0) {
    throw new RuntimeException('MFA partial enrollment persisted after synthetic DB rollback.');
}
echo "PASS: real p003e_mfa transaction rolled back grant, encrypted factor, TOTP step and recovery hashes.\n";
