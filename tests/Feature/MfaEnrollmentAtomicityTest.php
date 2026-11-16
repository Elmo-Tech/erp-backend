<?php

namespace Tests\Feature;

use App\Domain\Auth\MfaService;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Tests\TestCase;

final class MfaEnrollmentAtomicityTest extends TestCase
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

    private function requirePostgres(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('DB failure-injection tests require PostgreSQL.');
        }
    }

    /** @return array{User, string} */
    private function pendingEnrollment(): array
    {
        $user = User::factory()->create(['password' => 'correct-password']);
        DB::table('global_role_assignments')->insert([
            'id' => (string) Str::uuid7(), 'user_id' => $user->id,
            'role_key' => 'central_management', 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('user_mfa_enrollment_authorizations')->insert([
            'user_id' => $user->id,
            'approved_reference' => 'synthetic-db-rollback-test',
            'expires_at' => now()->addMinutes(10), 'consumed_at' => null,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email, 'password' => 'correct-password',
        ])->assertStatus(202);
        $secret = $this->postJson('/api/v1/auth/mfa/enroll/start')
            ->assertOk()->json('secret');

        return [$user, $secret];
    }

    private function totp(string $secret): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bits = '';
        foreach (str_split($secret) as $letter) {
            $bits .= str_pad(decbin(strpos($alphabet, $letter)), 5, '0', STR_PAD_LEFT);
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

    private function failUserFactorWrite(): void
    {
        DB::unprepared(<<<'SQL'
CREATE FUNCTION test_reject_mfa_factor_write() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    RAISE EXCEPTION 'synthetic factor-write failure';
END;
$$;
SQL);
        DB::unprepared('CREATE TRIGGER test_mfa_factor_write_failure BEFORE UPDATE OF mfa_secret ON users FOR EACH ROW EXECUTE FUNCTION test_reject_mfa_factor_write()');
    }

    private function removeUserFactorFailure(): void
    {
        DB::unprepared('DROP TRIGGER test_mfa_factor_write_failure ON users');
        DB::unprepared('DROP FUNCTION test_reject_mfa_factor_write()');
    }

    private function failRecoveryDigestInsert(): void
    {
        DB::unprepared(<<<'SQL'
CREATE FUNCTION test_reject_mfa_recovery_insert() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    RAISE EXCEPTION 'synthetic recovery-digest insert failure';
END;
$$;
SQL);
        DB::unprepared('CREATE TRIGGER test_mfa_recovery_insert_failure BEFORE INSERT ON user_mfa_recovery_codes FOR EACH ROW EXECUTE FUNCTION test_reject_mfa_recovery_insert()');
    }

    private function removeRecoveryDigestFailure(): void
    {
        DB::unprepared('DROP TRIGGER test_mfa_recovery_insert_failure ON user_mfa_recovery_codes');
        DB::unprepared('DROP FUNCTION test_reject_mfa_recovery_insert()');
    }

    private function assertNoPartialEnrollment(User $user, string $secret): void
    {
        $rawUser = DB::table('users')->where('id', $user->id)->first();
        $this->assertNull($rawUser->mfa_secret, 'TOTP must not persist after rollback.');
        $this->assertNull($rawUser->mfa_last_step, 'TOTP step must not persist after rollback.');
        $grant = DB::table('user_mfa_enrollment_authorizations')->where('user_id', $user->id)->first();
        $this->assertNull($grant->consumed_at, 'Trusted activation grant must remain usable.');
        $this->assertTrue(now()->lessThan($grant->expires_at));
        $this->assertSame(0, DB::table('user_mfa_recovery_codes')->where('user_id', $user->id)->count());
        $this->assertSame($secret, Crypt::decryptString(session('erp.enrollment_secret')));
        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    private function assertRetrySucceeds(User $user, string $secret): void
    {
        $confirmed = $this->postJson('/api/v1/auth/mfa/enroll/confirm', [
            'code' => $this->totp($secret),
        ])->assertOk()->assertJsonPath('status', 'authenticated');
        $codes = $confirmed->json('recovery_codes');
        $this->assertCount(8, $codes);
        $this->assertCount(8, array_unique($codes));
        $this->assertSame(8, DB::table('user_mfa_recovery_codes')->where('user_id', $user->id)->count());
        $this->assertNotNull(DB::table('user_mfa_enrollment_authorizations')
            ->where('user_id', $user->id)->value('consumed_at'));
        $this->assertSame($secret, $user->fresh()->mfa_secret);
        $this->assertNotSame($secret, DB::table('users')->where('id', $user->id)->value('mfa_secret'));
        $this->assertNull(session('erp.enrollment_secret'));
        $this->getJson('/api/v1/auth/me')->assertOk()->assertDontSee($secret)->assertDontSee($codes[0]);
    }

    public function test_failed_user_factor_write_rolls_back_claim_and_retry_can_succeed(): void
    {
        $this->requirePostgres();
        [$user, $secret] = $this->pendingEnrollment();
        $this->failUserFactorWrite();
        Log::spy();

        $this->postJson('/api/v1/auth/mfa/enroll/confirm', [
            'code' => $this->totp($secret),
        ])->assertStatus(503)
            ->assertJsonPath('message', 'Enrollment could not be completed. Please retry.')
            ->assertDontSee($secret);
        Log::shouldHaveReceived('warning')
            ->once()->with('MFA enrollment persistence failed.', ['failure_type' => QueryException::class]);
        $this->assertNoPartialEnrollment($user, $secret);

        $this->removeUserFactorFailure();
        $this->assertRetrySucceeds($user, $secret);
    }

    public function test_failed_recovery_digest_insert_rolls_back_factor_and_claim_and_retry_succeeds(): void
    {
        $this->requirePostgres();
        [$user, $secret] = $this->pendingEnrollment();
        $this->failRecoveryDigestInsert();
        Log::spy();

        $this->postJson('/api/v1/auth/mfa/enroll/confirm', [
            'code' => $this->totp($secret),
        ])->assertStatus(503)
            ->assertJsonPath('message', 'Enrollment could not be completed. Please retry.')
            ->assertDontSee($secret);
        Log::shouldHaveReceived('warning')
            ->once()->with('MFA enrollment persistence failed.', ['failure_type' => QueryException::class]);
        $this->assertNoPartialEnrollment($user, $secret);

        $this->removeRecoveryDigestFailure();
        $this->assertRetrySucceeds($user, $secret);
    }

    public function test_failed_standalone_recovery_rotation_preserves_existing_code_digests(): void
    {
        $this->requirePostgres();
        $user = User::factory()->create();
        $mfa = new MfaService();
        $user->mfa_secret = $mfa->makeSecret();
        $user->save();
        $original = $mfa->issueRecoveryCodes($user);
        $before = DB::table('user_mfa_recovery_codes')
            ->where('user_id', $user->id)->orderBy('code_hash')->pluck('code_hash')->all();
        $this->assertCount(8, $before);

        $this->failRecoveryDigestInsert();
        try {
            $mfa->issueRecoveryCodes($user);
            $this->fail('Expected a synthetic PostgreSQL insert failure.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('synthetic recovery-digest insert failure', $exception->getMessage());
        }
        $after = DB::table('user_mfa_recovery_codes')
            ->where('user_id', $user->id)->orderBy('code_hash')->pluck('code_hash')->all();
        $this->assertSame($before, $after, 'Failed rotation must not delete or partially replace recovery digests.');

        $this->removeRecoveryDigestFailure();
        $this->assertTrue($mfa->verifyAndConsume($user, $original[0]));
        $this->assertFalse($mfa->verifyAndConsume($user, $original[0]));
    }
}
