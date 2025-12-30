<?php

use App\Http\Middleware\SetLocaleMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Configure Sanctum stateful domains for SPA authentication
        $middleware->statefulApi();

        // Register locale middleware alias for use in controllers
        $middleware->alias([
            'locale' => SetLocaleMiddleware::class,
        ]);

        // Append CORS and locale middleware to API middleware group
        $middleware->api(append: [
            \Illuminate\Http\Middleware\HandleCors::class,
            SetLocaleMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
