<?php

namespace App\Http\Controllers;

use App\Domain\Auth\MfaService;
use App\Domain\Companies\CompanyAuthorization;
use App\Models\User;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

final class AuthController extends Controller
{
    public function login(Request $request, MfaService $mfa): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        $email = Str::lower($data['email']);
        $ip = (string) $request->ip();
        $ipKey = 'erp-login-ip:'.hash('sha256', $ip);
        $accountKey = 'erp-login-account:'.hash('sha256', $email);
        $pairKey = 'erp-login-pair:'.hash('sha256', $email.'|'.$ip);
        if (RateLimiter::tooManyAttempts($ipKey, 20) ||
            RateLimiter::tooManyAttempts($accountKey, 5) ||
            RateLimiter::tooManyAttempts($pairKey, 5)) {
            return response()->json(['message' => 'Too many attempts. Try again later.'], 429);
        }

        $user = User::query()->where('email', $email)->first();
        if (! $user || ! $user->is_active || ! Hash::check($data['password'], $user->password)) {
            RateLimiter::hit($ipKey, 300);
            RateLimiter::hit($accountKey, 300);
            RateLimiter::hit($pairKey, 300);
            return response()->json(['message' => 'Invalid credentials.'], 422);
        }
        RateLimiter::clear($pairKey);
        Auth::guard('web')->logout();
        Auth::guard('sanctum')->forgetUser();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        $request->session()->regenerate();

        if ($mfa->requiresMfa($user) || $user->mfa_secret !== null) {
            $request->session()->put('erp.pending_user', $user->id);
            $request->session()->put('erp.pending_until', now()->addMinutes(5)->timestamp);
            return response()->json(['status' => 'mfa_required', 'enrollment_required' => $user->mfa_secret === null], 202);
        }

        $this->completeLogin($request, $user, false);
        return response()->json($this->identity($user, new CompanyAuthorization()));
    }

    public function mfaStatus(Request $request): JsonResponse
    {
        $user = $this->pendingUser($request);
        if (! $user) {
            return response()->json(['message' => 'Challenge expired.'], 401);
        }

        return response()->json(['status' => 'mfa_required', 'enrollment_required' => $user->mfa_secret === null]);
    }

    public function enrollStart(Request $request, MfaService $mfa): JsonResponse
    {
        $user = $this->pendingUser($request);
        if (! $user) {
            return response()->json(['message' => 'Challenge expired.'], 401);
        }
        if ($user->mfa_secret !== null) {
            return response()->json(['message' => 'MFA already enrolled.'], 409);
        }
        if (! $this->hasEnrollmentGrant($user)) {
            return response()->json(['message' => 'Trusted activation required.'], 403);
        }
        $key = 'erp-enroll-start:'.$user->id;
        if (RateLimiter::tooManyAttempts($key, 3)) {
            return response()->json(['message' => 'Too many attempts. Try again later.'], 429);
        }
        RateLimiter::hit($key, 300);

        $secret = $mfa->makeSecret();
        // The session may use Redis/DB with SESSION_ENCRYPT=false: encrypt this value independently.
        $request->session()->put('erp.enrollment_secret', Crypt::encryptString($secret));
        return response()->json(['secret' => $secret, 'otpauth_uri' => $mfa->provisioningUri($user, $secret)]);
    }

    public function enrollConfirm(Request $request, MfaService $mfa): JsonResponse
    {
        $user = $this->pendingUser($request);
        if (! $user) {
            return response()->json(['message' => 'Challenge expired.'], 401);
        }
        if ($this->mfaThrottled($request, $user)) {
            return response()->json(['message' => 'Too many attempts. Try again later.'], 429);
        }
        $data = $request->validate(['code' => ['required', 'regex:/^[0-9]{6}$/D']]);
        if (! $this->hasEnrollmentGrant($user) || $user->mfa_secret !== null) {
            return response()->json(['message' => 'Trusted activation required.'], 403);
        }
        $encrypted = $request->session()->get('erp.enrollment_secret');
        try {
            $secret = is_string($encrypted) ? Crypt::decryptString($encrypted) : null;
        } catch (DecryptException) {
            $secret = null;
        }
        if (! is_string($secret)) {
            return response()->json(['message' => 'Challenge expired.'], 401);
        }
        $step = $mfa->matchingStep($secret, $data['code']);
        if ($step === null) {
            $this->hitMfaLimiters($request, $user);
            return response()->json(['message' => 'Invalid verification code.'], 422);
        }
        try {
            // One commit boundary for the grant, the encrypted factor and all recovery digests.
            // Lock the user before claiming the grant: concurrent confirmations cannot rebind a factor.
            $codes = DB::transaction(function () use ($user, $secret, $step, $mfa): ?array {
                if ($mfa->usesRestrictedRuntime()) {
                    return $mfa->enrollRestricted($user, $secret, $step);
                }
                $lockedUser = User::query()->whereKey($user->id)->lockForUpdate()->first();
                if (! $lockedUser || ! $lockedUser->is_active || $lockedUser->mfa_secret !== null) {
                    return null;
                }

                $claimed = DB::table('user_mfa_enrollment_authorizations')
                    ->where('user_id', $lockedUser->id)->whereNull('consumed_at')
                    ->where('expires_at', '>', now())
                    ->update(['consumed_at' => now(), 'updated_at' => now()]);
                if ($claimed !== 1) {
                    return null;
                }

                $lockedUser->mfa_secret = $secret;
                $lockedUser->mfa_last_step = $step;
                $lockedUser->save();

                $issued = $mfa->issueRecoveryCodes($lockedUser);
                if (count($issued) !== 8) {
                    throw new \UnexpectedValueException('Incomplete MFA recovery-code issuance.');
                }

                return $issued;
            });
        } catch (\Throwable $exception) {
            // Never log the exception object/message/SQL bindings: these may contain MFA material.
            Log::warning('MFA enrollment persistence failed.', ['failure_type' => $exception::class]);
            return response()->json(['message' => 'Enrollment could not be completed. Please retry.'], 503);
        }

        if ($codes === null) {
            return response()->json(['message' => 'Trusted activation required.'], 403);
        }

        $this->clearMfaLimiters($request, $user);
        $this->completeLogin($request, $user, true);
        return response()->json(['status' => 'authenticated', 'recovery_codes' => $codes]);
    }

    public function verify(Request $request, MfaService $mfa): JsonResponse
    {
        $user = $this->pendingUser($request);
        if (! $user || $user->mfa_secret === null) {
            return response()->json(['message' => 'Challenge expired.'], 401);
        }
        if ($this->mfaThrottled($request, $user)) {
            return response()->json(['message' => 'Too many attempts. Try again later.'], 429);
        }
        $data = $request->validate(['code' => ['required', 'string', 'max:64']]);
        if (! $mfa->verifyAndConsume($user, $data['code'])) {
            $this->hitMfaLimiters($request, $user);
            return response()->json(['message' => 'Invalid verification code.'], 422);
        }
        $this->clearMfaLimiters($request, $user);
        $this->completeLogin($request, $user, true);
        return response()->json($this->identity($user, new CompanyAuthorization()));
    }

    public function me(Request $request, CompanyAuthorization $access): JsonResponse
    {
        return response()->json($this->identity($request->user(), $access));
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();
        Auth::guard('sanctum')->forgetUser();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return response()->json(['status' => 'logged_out']);
    }

    private function pendingUser(Request $request): ?User
    {
        if ($request->session()->get('erp.pending_until', 0) < now()->timestamp) {
            $request->session()->forget(['erp.pending_user', 'erp.pending_until', 'erp.enrollment_secret']);
            return null;
        }
        $id = $request->session()->get('erp.pending_user');
        return $id ? User::query()->where('is_active', true)->find($id) : null;
    }

    private function hasEnrollmentGrant(User $user): bool
    {
        return DB::table('user_mfa_enrollment_authorizations')
            ->where('user_id', $user->id)->whereNull('consumed_at')
            ->where('expires_at', '>', now())->exists();
    }

    private function mfaKeys(Request $request, User $user): array
    {
        return [
            'erp-mfa-account:'.$user->id,
            'erp-mfa-ip:'.hash('sha256', (string) $request->ip()),
            'erp-mfa-challenge:'.hash('sha256', $request->session()->getId()),
        ];
    }

    private function mfaThrottled(Request $request, User $user): bool
    {
        [$account, $ip, $challenge] = $this->mfaKeys($request, $user);
        return RateLimiter::tooManyAttempts($account, 5) ||
            RateLimiter::tooManyAttempts($ip, 30) ||
            RateLimiter::tooManyAttempts($challenge, 5);
    }

    private function hitMfaLimiters(Request $request, User $user): void
    {
        foreach ($this->mfaKeys($request, $user) as $key) {
            RateLimiter::hit($key, 300);
        }
    }

    private function clearMfaLimiters(Request $request, User $user): void
    {
        [$account, , $challenge] = $this->mfaKeys($request, $user);
        RateLimiter::clear($account);
        RateLimiter::clear($challenge);
    }

    private function completeLogin(Request $request, User $user, bool $mfaVerified): void
    {
        Auth::guard('web')->login($user);
        $request->session()->regenerate();
        $request->session()->forget(['erp.pending_user', 'erp.pending_until', 'erp.enrollment_secret', 'erp.company_id']);
        $request->session()->put('erp.mfa_verified', $mfaVerified);
    }

    private function identity(User $user, CompanyAuthorization $access): array
    {
        return [
            'user' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email],
            'companies' => $access->companyList($user),
            'global_management' => $access->isCentralManager($user),
            'consolidation_authorized' => $access->canViewConsolidation($user),
        ];
    }
}
