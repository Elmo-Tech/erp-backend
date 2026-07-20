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
        if ($this->isCrossSiteLogin($request)) {
            return ApiResponse::error('CSRF token mismatch', 419);
        }

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

    private function isCrossSiteLogin(Request $request): bool
    {
        if (! $request->isMethod('POST') || ! $request->is('api/v1/auth/login')) {
            return false;
        }

        if (strtolower((string) $request->header('Sec-Fetch-Site')) === 'cross-site') {
            return true;
        }

        $origin = trim((string) $request->header('Origin'));

        if ($origin === '') {
            $referer = trim((string) $request->header('Referer'));

            if ($referer === '') {
                return ! $request->isJson();
            }

            $origin = $this->originFromUrl($referer);
        }

        return $origin === '' || ! $this->isAllowedOrigin($origin);
    }

    private function isAllowedOrigin(string $origin): bool
    {
        $origin = rtrim($origin, '/');
        $allowedOrigins = array_map(
            static fn (mixed $allowedOrigin): string => rtrim((string) $allowedOrigin, '/'),
            (array) config('cors.allowed_origins', [])
        );

        if (in_array($origin, $allowedOrigins, true)) {
            return true;
        }

        foreach ((array) config('cors.allowed_origins_patterns', []) as $pattern) {
            if (preg_match((string) $pattern, $origin) === 1) {
                return true;
            }
        }

        return false;
    }

    private function originFromUrl(string $url): string
    {
        $scheme = parse_url($url, PHP_URL_SCHEME);
        $host = parse_url($url, PHP_URL_HOST);

        if (! is_string($scheme) || ! is_string($host)) {
            return '';
        }

        $port = parse_url($url, PHP_URL_PORT);

        return strtolower($scheme).'://'.strtolower($host).(is_int($port) ? ':'.$port : '');
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
