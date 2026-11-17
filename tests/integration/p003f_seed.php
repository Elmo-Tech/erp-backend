<?php

declare(strict_types=1);

// P0-03F disposable owner-only fixture. Never use against actual accounts.
if (getenv('APP_ENV') !== 'testing' || getenv('DB_DATABASE') !== 'elmo_erp_test'
    || getenv('DB_USERNAME') !== 'elmo' || getenv('DB_PORT') !== '5432') {
    fwrite(STDERR, "REFUSED: owner-only disposable P0-03F fixture required.\n");
    exit(2);
}
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

// Earlier P0-03C revocation race intentionally revoked the synthetic EG actor.
// Restore ONLY the disposable synthetic membership for new-role HTTP/worker tests.
DB::table('company_memberships')
    ->where('company_id', '00000000-0000-4000-8000-0000000000c1')
    ->where('user_id', DB::table('users')->where('email', 'p003c-eg@example.invalid')->value('id'))
    ->update(['status' => 'active']);

$user = DB::table('users')->insertGetId([
    'name' => 'P003F synthetic privileged',
    'email' => 'p003f-mfa@example.invalid',
    'password' => Hash::make('p003f-test-only'),
    'is_active' => true,
    'created_at' => now(), 'updated_at' => now(),
]);
DB::table('global_role_assignments')->insert([
    'id' => (string) Str::uuid7(), 'user_id' => $user,
    'role_key' => 'central_management', 'status' => 'active',
    'created_at' => now(), 'updated_at' => now(),
]);
DB::table('user_mfa_enrollment_authorizations')->insert([
    'user_id' => $user, 'approved_reference' => 'disposable-test-owner-fixture',
    'expires_at' => now()->addMinutes(20),
    'created_at' => now(), 'updated_at' => now(),
]);
echo "PASS: disposable MFA first-enrollment user and synthetic owner-issued grant.\n";
