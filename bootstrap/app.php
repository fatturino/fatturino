<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RequireCapability;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;
use Sentry\Laravel\Integration;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'capability' => RequireCapability::class,
        ]);

        $middleware->web(append: [
            HandleInertiaRequests::class,
        ]);
        $middleware->api(prepend: [
            EnsureFrontendRequestsAreStateful::class,
        ]);
        // Trust all proxies — required for correct URL generation behind reverse proxy (Uncloud/Caddy)
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        Integration::handles($exceptions);
    })->create();
