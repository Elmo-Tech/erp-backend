<?php

declare(strict_types=1);
// Disposable migration-owner-only synthetic rollback fault injection.
if (getenv('APP_ENV') !== 'testing' || getenv('DB_DATABASE') !== 'elmo_erp_test'
    || getenv('DB_USERNAME') !== 'elmo' || getenv('DB_PORT') !== '5432') {
    fwrite(STDERR, "REFUSED: owner-only disposable rollback fixture.\n");
    exit(2);
}
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
$userId = DB::table('users')->insertGetId([
    'name'=>'Synthetic P003F MFA rollback', 'email'=>'p003f-rollback@example.invalid',
    'password'=>Hash::make('unused-test-only'), 'is_active'=>true,
    'created_at'=>now(), 'updated_at'=>now(),
]);
DB::table('user_mfa_enrollment_authorizations')->insert([
    'user_id'=>$userId, 'approved_reference'=>'synthetic-rollback-only',
    'expires_at'=>now()->addMinutes(20), 'created_at'=>now(), 'updated_at'=>now(),
]);
DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION public.p003f_reject_recovery_insert() RETURNS trigger
LANGUAGE plpgsql AS $$
BEGIN
  IF NEW.user_id = (SELECT id FROM public.users WHERE email = 'p003f-rollback@example.invalid') THEN
    RAISE EXCEPTION 'P003F synthetic recovery failure';
  END IF;
  RETURN NEW;
END;
$$;
CREATE TRIGGER p003f_recovery_failure BEFORE INSERT ON public.user_mfa_recovery_codes
FOR EACH ROW EXECUTE FUNCTION public.p003f_reject_recovery_insert();
SQL);
echo "PASS: disposable owner installed user-specific recovery rollback fault.\n";
