<?php

namespace App\Http\Controllers;

use App\Domain\Auth\MfaService;
use App\Domain\Companies\CompanyAuthorization;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

final class AuthController extends Controller
{
    public function login(Request $request, MfaService $mfa): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        $key = 'erp-login:'.hash('sha256', Str::lower($data['email']).'|'.$request->ip());
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return response()->json(['message' => 'Too many attempts. Try again later.'], 429);
        }

        $user = User::query()->where('email', $data['email'])->first();
        if (! $user || ! $user->is_active || ! Hash::check($data['password'], $user->password)) {
            RateLimiter::hit($key, 60);
            return response()->json(['message' => 'Invalid credentials.'], 422);
        }
        RateLimiter::clear($key);
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
        $secret = $mfa->makeSecret();
        $request->session()->put('erp.enrollment_secret', $secret);
        return response()->json(['secret' => $secret, 'otpauth_uri' => $mfa->provisioningUri($user, $secret)]);
    }

    public function enrollConfirm(Request $request, MfaService $mfa): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'regex:/^[0-9]{6}$/D']]);
        $user = $this->pendingUser($request);
        $secret = $request->session()->get('erp.enrollment_secret');
        if (! $user || ! is_string($secret) || $user->mfa_secret !== null) {
            return response()->json(['message' => 'Challenge expired.'], 401);
        }
        if ($this->challengeLimited($request) || ($step = $mfa->matchingStep($secret, $data['code'])) === null) {
            return response()->json(['message' => 'Invalid verification code.'], 422);
        }
        $user->mfa_secret = $secret;
        $user->mfa_last_step = $step;
        $user->save();
        $codes = $mfa->issueRecoveryCodes($user);
        $this->completeLogin($request, $user, true);
        return response()->json(['status' => 'authenticated', 'recovery_codes' => $codes]);
    }

    public function verify(Request $request, MfaService $mfa): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:64']]);
        $user = $this->pendingUser($request);
        if (! $user || $user->mfa_secret === null) {
            return response()->json(['message' => 'Challenge expired.'], 401);
        }
        if ($this->challengeLimited($request) || ! $mfa->verifyAndConsume($user, $data['code'])) {
            return response()->json(['message' => 'Invalid verification code.'], 422);
        }
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
            $request->session()->forget(['erp.pending_user', 'erp.enrollment_secret']);
            return null;
        }
        $id = $request->session()->get('erp.pending_user');
        return $id ? User::query()->where('is_active', true)->find($id) : null;
    }

    private function challengeLimited(Request $request): bool
    {
        $key = 'erp-mfa:'.hash('sha256', (string) $request->session()->get('erp.pending_user').'|'.$request->ip());
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return true;
        }
        RateLimiter::hit($key, 300);
        return false;
    }

    private function completeLogin(Request $request, User $user, bool $mfaVerified): void
    {
        Auth::guard('web')->login($user);
        $request->session()->regenerate();
        $request->session()->forget(['erp.pending_user', 'erp.pending_until', 'erp.enrollment_secret', 'erp.company_id']);
        $request->session()->put('erp.mfa_verified', $mfaVerified);
    }

    /** @return array<string, mixed> */
    private function identity(User $user, CompanyAuthorization $access): array
    {
        return [
            'user' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email],
            'companies' => $access->companyList($user),
            'global_management' => $access->isCentralManager($user),
        ];
    }
}
