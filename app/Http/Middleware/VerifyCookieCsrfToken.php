<?php

namespace App\Http\Middleware;

use App\Helpers\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyCookieCsrfToken
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->requiresVerification($request)) {
            return $next($request);
        }

        $cookieToken = (string) ($this->cookieValue($request, 'csrf_token') ?? '');
        $headerToken = (string) $request->header('X-CSRF-TOKEN', '');

        if ($cookieToken === '' || $headerToken === '' || ! hash_equals($cookieToken, $headerToken)) {
            return ApiResponse::error('CSRF token mismatch', 419);
        }

        return $next($request);
    }

    private function requiresVerification(Request $request): bool
    {
        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return false;
        }

        if ($request->is('api/v1/auth/login')) {
            return false;
        }

        return $this->cookieValue($request, 'auth_token') !== null;
    }

    private function cookieValue(Request $request, string $name): ?string
    {
        if ($request->cookies->has($name)) {
            return rawurldecode((string) $request->cookies->get($name));
        }

        $rawCookieHeader = (string) $request->headers->get('Cookie', '');
        foreach (explode(';', $rawCookieHeader) as $cookie) {
            [$cookieName, $cookieValue] = array_pad(explode('=', trim($cookie), 2), 2, null);

            if ($cookieName === $name && $cookieValue !== null) {
                return rawurldecode($cookieValue);
            }
        }

        return null;
    }
}
