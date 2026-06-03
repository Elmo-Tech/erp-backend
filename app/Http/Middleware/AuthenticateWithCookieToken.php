<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateWithCookieToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $this->cookieValue($request, 'auth_token');

        if (! $request->bearerToken() && $token !== null) {
            $request->headers->set('Authorization', 'Bearer '.$token);
        }

        return $next($request);
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
