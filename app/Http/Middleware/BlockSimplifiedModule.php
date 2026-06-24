<?php

namespace App\Http\Middleware;

use App\Helpers\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BlockSimplifiedModule
{
    public function handle(Request $request, Closure $next, ?string $module = null): Response
    {
        if (! config('simplification.enabled')) {
            return $next($request);
        }

        $disabledModules = config('simplification.disabled_modules', []);

        if ($module === null || in_array($module, $disabledModules, true)) {
            return ApiResponse::forbidden('This module is disabled in the simplified ERP version.');
        }

        return $next($request);
    }
}
