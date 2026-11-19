<?php
declare(strict_types=1);

// Owner-only synthetic fixtures for a pre-migrated disposable PG18 schema.
if (getenv('APP_ENV') !== 'testing' || !in_array(getenv('DB_DATABASE'), ['elmo_erp_test','elmo_erp_compose_ci'],true)
    || getenv('DB_USERNAME') !== 'elmo') {
    fwrite(STDERR, "REFUSED: synthetic owner-only test database required\n");
    exit(2);
}
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (\Illuminate\Support\Facades\DB::selectOne('SELECT session_user AS username')->username !== 'elmo') {
    fwrite(STDERR, "REFUSED: fixture connection is not the migration owner\n");
    exit(2);
}
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;

$mode = $argv[1] ?? '';
if (!in_array($mode, ['seed', 'cleanup'], true)) {
    fwrite(STDERR, "Usage: p004_laravel_fixture.php seed|cleanup\n");
    exit(2);
}
$codes = ['ACL-APP-EG', 'ACL-APP-IT'];
$emails = ['acl-web-eg@example.invalid','acl-web-mfa@example.invalid','acl-web-enroll@example.invalid'];
if ($mode === 'cleanup') {
    DB::transaction(static function () use ($codes, $emails): void {
        $users = DB::table('users')->whereIn('email', $emails)->pluck('id');
        DB::table('user_mfa_recovery_codes')->whereIn('user_id',$users)->delete();
        DB::table('user_mfa_enrollment_authorizations')->whereIn('user_id',$users)->delete();
        DB::table('global_role_assignments')->whereIn('user_id',$users)->delete();
        $memberships = DB::table('company_memberships')->whereIn('user_id',$users)->pluck('id');
        DB::table('company_role_assignments')->whereIn('membership_id',$memberships)->delete();
        DB::table('company_memberships')->whereIn('user_id',$users)->delete();
        DB::table('sessions')->whereIn('user_id',$users)->delete();
        DB::table('users')->whereIn('id',$users)->delete();
        DB::table('companies')->whereIn('code',$codes)->delete();
        DB::table('companies')->where('code','ACL-WEB-TEMP')->delete();
        DB::table('jobs')->where('queue','acl-web-fixture')->delete();
    });
    echo "PASS: synthetic owner fixture cleanup\n";
    exit;
}
DB::transaction(static function () use ($codes, $emails): void {
    foreach (['EGP'=>'Egyptian pound','EUR'=>'Euro'] as $code=>$name) {
        DB::table('currencies')->insertOrIgnore(['code'=>$code,'name'=>$name]);
    }
    foreach (['EG','IT'] as $index=>$country) {
        DB::table('companies')->insert([
            'id'=>(string) \Illuminate\Support\Str::uuid7(),
            'code'=>$codes[$index], 'legal_name'=>'Synthetic ACL '.$country,
            'country_code'=>$country, 'base_currency_code'=>$index===0?'EGP':'EUR',
            'timezone'=>'UTC', 'status'=>'active', 'created_at'=>now(), 'updated_at'=>now()
        ]);
    }
    foreach ($emails as $email) {
        $id=DB::table('users')->insertGetId([
            'name'=>'Synthetic ACL','email'=>$email,
            'password'=>password_hash('synthetic-only-password', PASSWORD_BCRYPT),
            'is_active'=>true,'created_at'=>now(),'updated_at'=>now()
        ]);
        if ($email===$emails[0]) {
            $company=DB::table('companies')->where('code',$codes[0])->value('id');
            $membership=(string) \Illuminate\Support\Str::uuid7();
            DB::table('company_memberships')->insert([
                'id'=>$membership,'user_id'=>$id,'company_id'=>$company,'status'=>'active',
                'created_at'=>now(),'updated_at'=>now()
            ]);
            DB::table('company_role_assignments')->insert([
                'id'=>(string) \Illuminate\Support\Str::uuid7(),'membership_id'=>$membership,
                'company_id'=>$company,'role_key'=>'project_member',
                'created_at'=>now(),'updated_at'=>now()
            ]);
        } else {
            DB::table('global_role_assignments')->insert([
                'id'=>(string) \Illuminate\Support\Str::uuid7(),'user_id'=>$id,
                'role_key'=>'central_management','status'=>'active',
                'created_at'=>now(),'updated_at'=>now()
            ]);
            if ($email===$emails[1]) {
                DB::table('users')->where('id',$id)->update([
                    'mfa_secret'=>Crypt::encryptString('JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP')
                ]);
                DB::table('user_mfa_recovery_codes')->insert([
                    'id'=>(string) \Illuminate\Support\Str::uuid7(),
                    'user_id'=>$id,
                    'code_hash'=>hash_hmac('sha256','AABBCCDDEEFF0011',(string)config('app.key')),
                    'created_at'=>now()
                ]);
            } else {
                DB::table('user_mfa_enrollment_authorizations')->insert([
                    'user_id'=>$id,'approved_reference'=>'synthetic-owner-only-operator',
                    'expires_at'=>now()->addMinutes(15),'created_at'=>now(),'updated_at'=>now()
                ]);
            }
        }
    }
});
echo "PASS: synthetic owner-created Laravel Auth/MFA/Company fixtures\n";
