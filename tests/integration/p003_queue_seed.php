<?php

declare(strict_types=1);

// Disposable PostgreSQL test fixture for a real, single-process reused Laravel queue worker.
if (getenv('APP_ENV') !== 'testing' || getenv('DB_DATABASE') !== 'elmo_erp_test') {
    fwrite(STDERR, "REFUSED: P0-03B queue seed is test-only.\n");
    exit(2);
}
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Support\P003ContextQueueJob;

$eg = '00000000-0000-4000-8000-000000000091';
$it = '00000000-0000-4000-8000-000000000092';
DB::table('currencies')->insertOrIgnore([
    ['code'=>'EGP','name'=>'Synthetic EGP'], ['code'=>'EUR','name'=>'Synthetic EUR'],
]);
foreach ([[$eg,'EG','EGP'],[$it,'IT','EUR']] as [$id,$country,$currency]) {
    DB::table('companies')->insert([
        'id'=>$id,'code'=>'P003Q-'.$country,'legal_name'=>'Synthetic '.$country,
        'country_code'=>$country,'base_currency_code'=>$currency,'timezone'=>'UTC',
        'status'=>'active','created_at'=>now(),'updated_at'=>now(),
    ]);
}
$user = DB::table('users')->insertGetId([
    'name'=>'P003 Queue Synthetic', 'email'=>'p003-queue@example.invalid',
    'password'=>Hash::make('unused-test-password'),'is_active'=>true,
    'created_at'=>now(),'updated_at'=>now(),
]);
foreach ([$eg,$it] as $id) {
    $membership = (string) Illuminate\Support\Str::uuid7();
    DB::table('company_memberships')->insert([
        'id'=>$membership,'user_id'=>$user,'company_id'=>$id,
        'status'=>'active','created_at'=>now(),'updated_at'=>now(),
    ]);
    DB::table('company_role_assignments')->insert([
        'id'=>(string) Illuminate\Support\Str::uuid7(),
        'company_id'=>$id,'membership_id'=>$membership,'role_key'=>'viewer',
        'created_at'=>now(),'updated_at'=>now(),
    ]);
}
foreach ([[$eg,false],[$it,false],[$eg,true],[$eg,false],[$it,false]] as [$id,$revoke]) {
    P003ContextQueueJob::dispatch($user,$id,$revoke)->onConnection('database')->onQueue('p003b');
}
file_put_contents('/tmp/p003b-queue-user-id', (string) $user);
echo "Seeded five queue jobs (EG, IT, revoke EG, denied EG, IT).\n";
