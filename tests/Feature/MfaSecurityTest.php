<?php

namespace Tests\Feature;

use App\Domain\Auth\MfaService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class MfaSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_totp_is_six_digits_and_same_step_is_rejected_after_first_use(): void
    {
        $user = User::factory()->create();
        $mfa = new MfaService();
        $secret = $mfa->makeSecret();
        $user->mfa_secret = $secret;
        $user->save();

        $counter = intdiv(now()->timestamp, 30);
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bits = '';
        foreach (str_split($secret) as $character) {
            $bits .= str_pad(decbin(strpos($alphabet, $character)), 5, '0', STR_PAD_LEFT);
        }
        $binary = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $binary .= chr(bindec($byte));
            }
        }
        $digest = hash_hmac('sha1', pack('N2', 0, $counter), $binary, true);
        $offset = ord($digest[19]) & 0x0f;
        $code = str_pad((string) ((unpack('N', substr($digest, $offset, 4))[1] & 0x7fffffff) % 1000000), 6, '0', STR_PAD_LEFT);

        $this->assertNotNull($mfa->matchingStep($secret, $code));
        $this->assertTrue($mfa->verifyAndConsume($user, $code));
        $this->assertFalse($mfa->verifyAndConsume($user, $code));
        $this->assertNull($mfa->matchingStep($secret, '00000x'));
    }

    public function test_recovery_code_can_be_used_only_once(): void
    {
        $mfa = new MfaService();
        $user = User::factory()->create();
        $user->mfa_secret = $mfa->makeSecret();
        $user->save();
        $codes = $mfa->issueRecoveryCodes($user);
        $this->assertCount(8, $codes);
        $this->assertTrue($mfa->verifyAndConsume($user, $codes[3]));
        $this->assertFalse($mfa->verifyAndConsume($user, $codes[3]));
        $this->assertSame(7, DB::table('user_mfa_recovery_codes')->where('user_id', $user->id)->count());
    }

    public function test_active_global_privilege_requires_mfa_and_revocation_removes_requirement(): void
    {
        $mfa = new MfaService();
        $user = User::factory()->create();
        $this->assertFalse($mfa->requiresMfa($user));
        $id = (string) Str::uuid7();
        DB::table('global_role_assignments')->insert([
            'id' => $id, 'user_id' => $user->id, 'role_key' => 'central_management',
            'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->assertTrue($mfa->requiresMfa($user));
        DB::table('global_role_assignments')->where('id', $id)->update(['status' => 'revoked']);
        $this->assertFalse($mfa->requiresMfa($user));
    }
}
