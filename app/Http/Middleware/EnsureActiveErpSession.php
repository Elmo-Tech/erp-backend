<?php

namespace App\Http\Middleware;

use App\Domain\Auth\MfaService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureActiveErpSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user || ! $user->is_active) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // Disallow legacy/password-only sessions when a privileged grant changes.
        if ((new MfaService())->requiresMfa($user) && ! $request->session()->get('erp.mfa_verified', false)) {
            return response()->json(['message' => 'Multi-factor authentication required.'], 403);
        }

        return $next($request);
    }
}
