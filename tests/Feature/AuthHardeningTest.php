<?php

namespace Tests\Feature;

use App\Domain\Auth\MfaService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Tests\TestCase;

final class AuthHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withHeaders([
            'Origin' => 'http://localhost:5173',
            'Referer' => 'http://localhost:5173/',
            'Accept' => 'application/json',
        ]);
    }

    private function privilegedUser(): User
    {
        $user = User::factory()->create(['password' => 'correct-password']);
        DB::table('global_role_assignments')->insert([
            'id' => (string) Str::uuid7(), 'user_id' => $user->id, 'role_key' => 'central_management',
            'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);
        return $user;
    }

    private function grant(User $user): void
    {
        DB::table('user_mfa_enrollment_authorizations')->insert([
            'user_id' => $user->id, 'approved_reference' => 'synthetic-ci-identity-check',
            'expires_at' => now()->addMinutes(10), 'consumed_at' => null,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function totp(string $secret): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bits = '';
        foreach (str_split($secret) as $character) {
            $bits .= str_pad(decbin(strpos($alphabet, $character)), 5, '0', STR_PAD_LEFT);
        }
        $key = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $key .= chr(bindec($byte));
            }
        }
        $counter = intdiv(now()->timestamp, 30);
        $digest = hash_hmac('sha1', pack('N2', ($counter >> 32) & 0xffffffff, $counter & 0xffffffff), $key, true);
        $offset = ord($digest[19]) & 0x0f;
        return str_pad((string) ((unpack('N', substr($digest, $offset, 4))[1] & 0x7fffffff) % 1000000), 6, '0', STR_PAD_LEFT);
    }

    public function test_mfa_secret_is_encrypted_in_database_and_hidden_from_serialization_and_me(): void
    {
        $user = $this->privilegedUser();
        $secret = (new MfaService())->makeSecret();
        $user->mfa_secret = $secret;
        $user->mfa_last_step = 55;
        $user->save();
        $raw = DB::table('users')->where('id', $user->id)->value('mfa_secret');
        $this->assertNotSame($secret, $raw);
        $this->assertStringNotContainsString($secret, $raw);
        $this->assertSame($secret, $user->fresh()->mfa_secret);
        $this->assertArrayNotHasKey('mfa_secret', $user->toArray());
        $this->assertArrayNotHasKey('mfa_last_step', $user->toArray());
        $this->assertStringNotContainsString($secret, $user->toJson());
        $codes = (new MfaService())->issueRecoveryCodes($user);
        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'correct-password'])->assertStatus(202);
        $this->postJson('/api/v1/auth/mfa/verify', ['code' => $codes[0]])->assertOk();
        $response = $this->getJson('/api/v1/auth/me')->assertOk();
        $response->assertDontSee($secret);
        $response->assertDontSee($raw);
        $response->assertDontSee('mfa_last_step');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control', ''));
    }

    public function test_activation_is_trusted_and_enrollment_secret_is_ciphertext_in_session(): void
    {
        $user = $this->privilegedUser();
        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'correct-password'])->assertStatus(202);
        $this->postJson('/api/v1/auth/mfa/enroll/start')->assertForbidden();
        $this->grant($user);
        $response = $this->postJson('/api/v1/auth/mfa/enroll/start')->assertOk();
        $secret = $response->json('secret');
        $cipher = session('erp.enrollment_secret');
        $this->assertIsString($cipher);
        $this->assertNotSame($secret, $cipher);
        $this->assertStringNotContainsString($secret, $cipher);
        $this->assertSame($secret, decrypt($cipher));
        $this->getJson('/api/v1/auth/mfa/status')->assertDontSee($secret);
        $this->getJson('/api/v1/auth/me')->assertUnauthorized();

        $this->postJson('/api/v1/auth/mfa/enroll/confirm', ['code' => 'not-six-digits'])->assertUnprocessable();
        $confirmed = $this->postJson('/api/v1/auth/mfa/enroll/confirm', ['code' => $this->totp($secret)])
            ->assertOk()->assertJsonPath('status', 'authenticated');
        $codes = $confirmed->json('recovery_codes');
        $this->assertCount(8, $codes);
        $this->assertArrayNotHasKey('secret', $confirmed->json());
        $this->assertSame($secret, $user->fresh()->mfa_secret);
        $this->assertNotSame($secret, DB::table('users')->where('id', $user->id)->value('mfa_secret'));
        $this->assertNotNull(DB::table('user_mfa_enrollment_authorizations')
            ->where('user_id', $user->id)->value('consumed_at'));
        $this->assertNull(session('erp.enrollment_secret'));
        $this->getJson('/api/v1/auth/me')->assertDontSee($secret)->assertDontSee($codes[0]);
        $this->postJson('/api/v1/auth/logout')->assertOk();

        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'correct-password'])->assertStatus(202);
        $this->postJson('/api/v1/auth/mfa/verify', ['code' => $codes[0]])->assertOk();
        $this->postJson('/api/v1/auth/logout')->assertOk();
        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'correct-password'])->assertStatus(202);
        $this->postJson('/api/v1/auth/mfa/verify', ['code' => $codes[0]])->assertUnprocessable();
    }

    public function test_expired_or_revoked_activation_cannot_enroll(): void
    {
        $user = $this->privilegedUser();
        $this->grant($user);
        DB::table('user_mfa_enrollment_authorizations')->where('user_id', $user->id)
            ->update(['expires_at' => now()->subMinute()]);
        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'correct-password'])->assertStatus(202);
        $this->postJson('/api/v1/auth/mfa/enroll/start')->assertForbidden();
        DB::table('user_mfa_enrollment_authorizations')->where('user_id', $user->id)
            ->update(['expires_at' => now()->addMinutes(10)]);
        $this->postJson('/api/v1/auth/mfa/enroll/start')->assertOk();
        DB::table('user_mfa_enrollment_authorizations')->where('user_id', $user->id)
            ->update(['consumed_at' => now()]);
        $this->postJson('/api/v1/auth/mfa/enroll/confirm', ['code' => '123456'])->assertForbidden();
        $this->assertNull($user->fresh()->mfa_secret);
    }

    public function test_mfa_challenge_expires_and_disabled_account_cannot_verify(): void
    {
        $user = $this->privilegedUser();
        $user->mfa_secret = (new MfaService())->makeSecret();
        $user->save();
        $codes = (new MfaService())->issueRecoveryCodes($user);
        $this->withSession(['erp.pending_user' => $user->id, 'erp.pending_until' => now()->subSecond()->timestamp]);
        $this->postJson('/api/v1/auth/mfa/verify', ['code' => $codes[0]])->assertUnauthorized();
        $this->getJson('/api/v1/auth/me')->assertUnauthorized();

        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'correct-password'])->assertStatus(202);
        DB::table('users')->where('id', $user->id)->update(['is_active' => false]);
        $this->postJson('/api/v1/auth/mfa/verify', ['code' => $codes[0]])->assertUnauthorized();
        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_login_ip_spray_exhaustion_is_independent_of_email(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => 'random-'.$i.'@example.test', 'password' => 'wrong',
            ])->assertUnprocessable();
        }
        $this->postJson('/api/v1/auth/login', [
            'email' => 'next-account@example.test', 'password' => 'wrong',
        ])->assertStatus(429);
    }

    public function test_mfa_brute_force_exhaustion_denies_even_correct_recovery_code(): void
    {
        $user = $this->privilegedUser();
        $user->mfa_secret = (new MfaService())->makeSecret();
        $user->save();
        $code = (new MfaService())->issueRecoveryCodes($user)[0];
        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'correct-password'])->assertStatus(202);
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/mfa/verify', ['code' => 'invalid-recovery-'.$i])->assertUnprocessable();
        }
        $this->postJson('/api/v1/auth/mfa/verify', ['code' => $code])->assertStatus(429);
        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
    }
}
