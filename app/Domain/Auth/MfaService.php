<?php

namespace App\Domain\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Connection;
use LogicException;
use RuntimeException;
use Illuminate\Support\Str;

final class MfaService
{
    /**
     * P0-03F: factor material stays off the Web SQL principal. This connection
     * remains in the same PHP process; it is not a compromise-proof MFA broker.
     */
    private function factorDb(): Connection
    {
        $name = config('erp_runtime.mfa_connection');
        if (config('erp_runtime.partition_required') && $name !== 'pgsql_mfa') {
            throw new LogicException('Split runtime requires a dedicated MFA connection.');
        }

        return DB::connection($name ?: null);
    }

    public function hasFactor(User $user): bool
    {
        return $this->factorDb()->table('users')->where('id', $user->getKey())
            ->whereNotNull('mfa_secret')->value('id') !== null;
    }

    public function hasEnrollmentGrant(User $user): bool
    {
        return $this->factorDb()->table('user_mfa_enrollment_authorizations')
            ->where('user_id', $user->getKey())->whereNull('consumed_at')
            ->where('expires_at', '>', now())->exists();
    }

    /**
     * The same MFA connection owns factor, one-time grant and recovery inserts.
     * A concurrent confirmation is serialized by the grant's conditional UPDATE.
     * Returning null before any write or throwing rolls back the entire operation.
     *
     * @return list<string>|null
     */
    public function confirmEnrollment(User $user, string $secret, int $step): ?array
    {
        $db = $this->factorDb();

        try {
            return $db->transaction(function () use ($db, $user, $secret, $step): ?array {
                $claimed = $db->table('user_mfa_enrollment_authorizations')
                    ->where('user_id', $user->getKey())->whereNull('consumed_at')
                    ->where('expires_at', '>', now())->update(['consumed_at' => now()]);
                if ($claimed !== 1) {
                    return null;
                }

                $updated = $db->table('users')->where('id', $user->getKey())
                    ->where('is_active', true)->whereNull('mfa_secret')
                    ->update([
                        'mfa_secret' => Crypt::encryptString($secret),
                        'mfa_last_step' => $step,
                    ]);
                if ($updated !== 1) {
                    // The grant UPDATE must also roll back if the factor was already set.
                    throw new MfaEnrollmentConflict();
                }

                $codes = $this->issueRecoveryCodes($user);
                if (count($codes) !== 8) {
                    throw new RuntimeException('Incomplete MFA recovery-code issuance.');
                }

                return $codes;
            }, 1);
        } catch (MfaEnrollmentConflict) {
            return null;
        }
    }

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
        // Never depend on a stale or all-column WebUser object for MFA state.
        $db = $this->factorDb();
        $encrypted = $db->table('users')->where('id', $user->getKey())
            ->where('is_active', true)->value('mfa_secret');
        if ($encrypted === null) {
            return false;
        }
        $step = $this->matchingStep(Crypt::decryptString($encrypted), $code);
        if ($step !== null) {
            return $db->table('users')->where('id', $user->getKey())
                ->where('is_active', true)
                ->where(fn ($q) => $q->whereNull('mfa_last_step')->orWhere('mfa_last_step', '<', $step))
                ->update(['mfa_last_step' => $step]) === 1;
        }

        // Single-use recovery code: a single DELETE is an atomic consume.
        return $db->table('user_mfa_recovery_codes')
            ->where('user_id', $user->getKey())
            ->where('code_hash', $this->recoveryHash($code))->delete() === 1;
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
        return $this->factorDb()->transaction(function () use ($user): array {
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

            $db = $this->factorDb();
            $db->table('user_mfa_recovery_codes')->where('user_id', $user->id)->delete();
            $db->table('user_mfa_recovery_codes')->insert($rows);

            return $codes;
        });
    }
}
