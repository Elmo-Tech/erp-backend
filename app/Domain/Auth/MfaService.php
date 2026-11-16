<?php

namespace App\Domain\Auth;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class MfaService
{
    public function requiresMfa(User $user): bool
    {
        if (DB::table('global_role_assignments')->where('user_id', $user->id)
            ->where('status', 'active')->where('role_key', 'central_management')->exists()) {
            return true;
        }

        return DB::table('company_role_assignments as r')
            ->join('company_memberships as m', function ($join): void {
                $join->on('m.id', '=', 'r.membership_id')
                    ->on('m.company_id', '=', 'r.company_id');
            })
            ->join('companies as c', 'c.id', '=', 'm.company_id')
            ->where('m.user_id', $user->id)
            ->where('m.status', 'active')
            ->where('c.status', 'active')
            ->whereIn('r.role_key', ['company_manager', 'finance', 'hr', 'external_accountant'])
            ->exists();
    }

    public function makeSecret(): string
    {
        $bits = '';
        foreach (str_split(random_bytes(20)) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }
        $secret = '';
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        foreach (str_split($bits, 5) as $group) {
            $secret .= $alphabet[bindec($group)];
        }
        return $secret;
    }

    public function provisioningUri(User $user, string $secret): string
    {
        $label = rawurlencode('Elmo ERP:'.$user->email);
        return "otpauth://totp/{$label}?secret={$secret}&issuer=Elmo%20ERP&digits=6&period=30";
    }

    private function decodeSecret(string $secret): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bits = '';
        foreach (str_split(strtoupper($secret)) as $character) {
            $position = strpos($alphabet, $character);
            if ($position === false) {
                return '';
            }
            $bits .= str_pad(decbin($position), 5, '0', STR_PAD_LEFT);
        }
        $bytes = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $bytes .= chr(bindec($byte));
            }
        }
        return $bytes;
    }

    public function matchingStep(string $secret, string $code): ?int
    {
        if (! preg_match('/^[0-9]{6}$/D', $code)) {
            return null;
        }

        $key = $this->decodeSecret($secret);
        if ($key === '') {
            return null;
        }

        $now = intdiv(now()->timestamp, 30);
        for ($step = $now - 1; $step <= $now + 1; $step++) {
            $binary = pack('N2', ($step >> 32) & 0xffffffff, $step & 0xffffffff);
            $digest = hash_hmac('sha1', $binary, $key, true);
            $offset = ord($digest[19]) & 0x0f;
            $value = (unpack('N', substr($digest, $offset, 4))[1] & 0x7fffffff) % 1000000;
            if (hash_equals(str_pad((string) $value, 6, '0', STR_PAD_LEFT), $code)) {
                return $step;
            }
        }
        return null;
    }

    public function verifyAndConsume(User $user, string $code): bool
    {
        if ($user->mfa_secret !== null) {
            $step = $this->matchingStep($user->mfa_secret, $code);
            if ($step !== null) {
                $updated = DB::table('users')->where('id', $user->id)
                    ->where(fn ($query) => $query->whereNull('mfa_last_step')->orWhere('mfa_last_step', '<', $step))
                    ->update(['mfa_last_step' => $step]);
                return $updated === 1;
            }
        }

        // Constant-length hashed recovery secrets; single-use deletion is atomic.
        $hash = $this->recoveryHash($code);
        return DB::table('user_mfa_recovery_codes')
            ->where('user_id', $user->id)->where('code_hash', $hash)->delete() === 1;
    }

    private function recoveryHash(string $code): string
    {
        return hash_hmac('sha256', strtoupper(str_replace('-', '', $code)), (string) config('app.key'));
    }

    /** @return list<string> Plaintext codes are returned exactly once. */
    public function issueRecoveryCodes(User $user): array
    {
        // Also atomic when invoked outside initial enrollment (e.g. a future approved reset).
        // Nested calls share the parent Laravel DB transaction/savepoint.
        return DB::transaction(function () use ($user): array {
            $codes = [];
            $rows = [];
            $issuedAt = now();
            for ($index = 0; $index < 8; $index++) {
                $code = strtoupper(bin2hex(random_bytes(8)));
                $codes[] = $code;
                $rows[] = [
                    'id' => (string) Str::uuid7(),
                    'user_id' => $user->id,
                    'code_hash' => $this->recoveryHash($code),
                    'created_at' => $issuedAt,
                ];
            }

            DB::table('user_mfa_recovery_codes')->where('user_id', $user->id)->delete();
            DB::table('user_mfa_recovery_codes')->insert($rows);

            return $codes;
        });
    }
}
