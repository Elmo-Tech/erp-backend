<?php

declare(strict_types=1);

// Owner-only disposable fixture for real HTTP / revocation tests.
if (getenv('APP_ENV') !== 'testing' || getenv('DB_DATABASE') !== 'elmo_erp_test'
    || getenv('DB_USERNAME') !== 'elmo' || getenv('DB_PORT') !== '5432') {
    fwrite(STDERR, "REFUSED: P0-03C seed requires isolated migration-owner CI.\n");
    exit(2);
}
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

$eg = '00000000-0000-4000-8000-0000000000c1';
$it = '00000000-0000-4000-8000-0000000000c2';
DB::table('currencies')->insertOrIgnore([
    ['code' => 'EGP', 'name' => 'Synthetic EGP'],
    ['code' => 'EUR', 'name' => 'Synthetic EUR'],
]);
foreach ([[$eg, 'EG', 'EGP'], [$it, 'IT', 'EUR']] as [$companyId, $country, $currency]) {
    DB::table('companies')->insert([
        'id' => $companyId, 'code' => 'P003C-'.$country,
        'legal_name' => 'P003C synthetic '.$country,
        'country_code' => $country, 'base_currency_code' => $currency,
        'timezone' => 'UTC', 'status' => 'active',
        'created_at' => now(), 'updated_at' => now(),
    ]);
}
foreach ([[$eg, 'eg'], [$it, 'it']] as [$companyId, $slug]) {
    $userId = DB::table('users')->insertGetId([
        'name' => 'P003C synthetic '.$slug,
        'email' => 'p003c-'.$slug.'@example.invalid',
        'password' => Hash::make('p003c-test-only'),
        'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $membershipId = (string) Str::uuid7();
    DB::table('company_memberships')->insert([
        'id' => $membershipId, 'user_id' => $userId, 'company_id' => $companyId,
        'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('company_role_assignments')->insert([
        'id' => (string) Str::uuid7(), 'membership_id' => $membershipId,
        'company_id' => $companyId, 'role_key' => 'viewer',
        'created_at' => now(), 'updated_at' => now(),
    ]);
}
echo "PASS: owner seeded synthetic EG and IT users with separate memberships.\n";
