<?php

use App\Http\Middleware\AuthenticateWithCookieToken;
use App\Http\Middleware\SetLocaleMiddleware;
use App\Http\Middleware\VerifyCookieCsrfToken;
use App\Providers\AuthServiceProvider;
use App\Providers\ModuleServiceProvider;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\HandleCors;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withProviders([
        AuthServiceProvider::class,
        ModuleServiceProvider::class,
    ])
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Configure Sanctum stateful domains for SPA authentication
        $middleware->statefulApi();

        // Register middleware aliases for use in controllers
        $middleware->alias([
            'locale' => SetLocaleMiddleware::class,
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
        ]);

        $middleware->prependToGroup('api', [
            AuthenticateWithCookieToken::class,
            VerifyCookieCsrfToken::class,
        ]);

        // Append CORS and locale middleware to API middleware group
        $middleware->appendToGroup('api', [
            HandleCors::class,
            SetLocaleMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
