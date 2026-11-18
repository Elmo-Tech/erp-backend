<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * NO RefreshDatabase: owner creates schema and test fixtures beforehand.
 * This suite MUST be run with real elmo_runtime LOGIN, never a SET ROLE.
 */
final class RestrictedRuntimeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (config('database.connections.pgsql.username') !== 'elmo_runtime') {
            $this->markTestSkipped('Restricted suite runs only with real elmo_runtime LOGIN after owner migrations.');
        }
        $this->withHeaders([
            'Origin' => 'http://localhost:5173',
            'Referer' => 'http://localhost:5173/',
            'Accept' => 'application/json',
        ]);
        $this->assertSame('testing', app()->environment());
        $this->assertSame('elmo_erp_test', config('database.connections.pgsql.database'));
        $this->assertSame('elmo_runtime', DB::selectOne('SELECT session_user AS username')->username);
        $this->assertSame('database', config('session.driver'));
        $this->assertSame('database', config('queue.default'));
    }

    public function test_web_direct_access_to_sensitive_objects_is_denied(): void
    {
        foreach (['user_mfa_recovery_codes','audit_logs','outbox_events',
            'idempotency_requests','migrations','journal_entries','journal_lines'] as $table) {
            foreach (['INSERT','UPDATE','DELETE'] as $privilege) {
                $allowed=DB::selectOne('SELECT has_table_privilege(current_user, ?, ?) AS allowed',
                    ['public.'.$table, $privilege])->allowed;
                $this->assertFalse((bool)$allowed, "$table $privilege");
            }
        }
        $this->assertFalse((bool) DB::selectOne(
            "SELECT has_table_privilege(current_user,'public.users','UPDATE') AS allowed")->allowed);
        $this->assertFalse((bool) DB::selectOne(
            "SELECT has_table_privilege(current_user,'public.migrations','SELECT') AS allowed")->allowed);
        $this->assertFalse((bool) DB::selectOne(
            "SELECT has_table_privilege(current_user,'public.user_mfa_recovery_codes','SELECT') AS allowed")->allowed);
    }

    public function test_real_runtime_login_database_session_and_company_authorization(): void
    {
        $this->postJson('/api/v1/auth/login', [
            'email'=>'acl-web-eg@example.invalid','password'=>'synthetic-only-password'
        ])->assertOk();
        $this->getJson('/api/v1/auth/me')->assertOk();
        $egypt=DB::table('companies')->where('code','ACL-APP-EG')->value('id');
        $italy=DB::table('companies')->where('code','ACL-APP-IT')->value('id');
        $this->postJson('/api/v1/companies/switch',['company_id'=>$italy])->assertForbidden();
        $this->postJson('/api/v1/companies/switch',['company_id'=>$egypt])->assertOk();
        $this->getJson('/api/v1/companies/authorize/projects.view')->assertOk();
        $this->getJson('/api/v1/companies/authorize/finance.view')->assertForbidden();
        $this->postJson('/api/v1/auth/logout')->assertOk();
        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_mfa_recovery_is_single_use_via_restricted_function(): void
    {
        $this->postJson('/api/v1/auth/login', [
            'email'=>'acl-web-mfa@example.invalid','password'=>'synthetic-only-password'
        ])->assertStatus(202);
        $this->postJson('/api/v1/auth/mfa/verify',['code'=>'AABBCCDDEEFF0011'])->assertOk();
        $this->getJson('/api/v1/auth/me')->assertOk();
        $this->postJson('/api/v1/auth/logout')->assertOk();
        $this->postJson('/api/v1/auth/login', [
            'email'=>'acl-web-mfa@example.invalid','password'=>'synthetic-only-password'
        ])->assertStatus(202);
        $this->postJson('/api/v1/auth/mfa/verify',['code'=>'AABBCCDDEEFF0011'])->assertUnprocessable();
    }

    public function test_operator_preapproved_mfa_enrollment_consumes_grant_atomically(): void
    {
        $this->postJson('/api/v1/auth/login', [
            'email'=>'acl-web-enroll@example.invalid','password'=>'synthetic-only-password'
        ])->assertStatus(202);
        $secret=$this->postJson('/api/v1/auth/mfa/enroll/start')
            ->assertOk()->json('secret');
        $alphabet='ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bits='';
        foreach (str_split($secret) as $char) {
            $bits.=str_pad(decbin(strpos($alphabet,$char)),5,'0',STR_PAD_LEFT);
        }
        $binary='';
        foreach (str_split($bits,8) as $byte) {
            if (strlen($byte)===8) {$binary.=chr(bindec($byte));}
        }
        $step=intdiv(now()->timestamp,30);
        $digest=hash_hmac('sha1',pack('N2',0,$step),$binary,true);
        $offset=ord($digest[19]) & 0x0f;
        $code=str_pad((string)((unpack('N',substr($digest,$offset,4))[1] & 0x7fffffff)%1000000),6,'0',STR_PAD_LEFT);
        $response=$this->postJson('/api/v1/auth/mfa/enroll/confirm',['code'=>$code])
            ->assertOk()->assertJsonPath('status','authenticated');
        $this->assertCount(8,$response->json('recovery_codes'));
        $user=User::query()->where('email','acl-web-enroll@example.invalid')->firstOrFail();
        $this->assertNotNull($user->mfa_secret);
        $this->assertNotNull(DB::table('user_mfa_enrollment_authorizations')
            ->where('user_id',$user->id)->value('consumed_at'));
        $this->getJson('/api/v1/auth/me')->assertOk();
    }

    public function test_web_company_crud_and_database_queue_enqueue(): void
    {
        $id=(string)\Illuminate\Support\Str::uuid7();
        DB::table('companies')->insert([
            'id'=>$id,'code'=>'ACL-WEB-TEMP','legal_name'=>'Synthetic temporary',
            'country_code'=>'EG','base_currency_code'=>'EGP','timezone'=>'UTC',
            'status'=>'inactive','created_at'=>now(),'updated_at'=>now()
        ]);
        $this->assertSame(1, DB::table('companies')->where('id',$id)
            ->update(['legal_name'=>'Synthetic renamed','updated_at'=>now()]));
        $this->assertSame(1,DB::table('companies')->where('id',$id)->delete());
        Queue::connection('database')->pushRaw('{"synthetic":true}','acl-web-fixture');
        $this->assertSame(1,DB::table('jobs')->where('queue','acl-web-fixture')->count());
    }
}
