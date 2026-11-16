<?php

namespace App\Http\Middleware;

use App\Domain\Auth\MfaService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final class EnsureActiveErpSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $request->hasSession() || ! Auth::guard('web')->check() || ! $user || ! $user->is_active) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // Enforce first-party web sessions only: a Sanctum bearer token cannot bypass cookie auth.
        // Disallow legacy/password-only sessions when a privileged grant changes.
        if ((new MfaService())->requiresMfa($user) && ! $request->session()->get('erp.mfa_verified', false)) {
            return response()->json(['message' => 'Multi-factor authentication required.'], 403);
        }

        return $next($request);
    }
}
