<?php

use App\Http\Middleware\RequireCapability;
use App\Http\Middleware\ValidateOpenApiWebhook;
use App\Services\PostHogTelemetryService;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

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
            'openapi.webhook' => ValidateOpenApiWebhook::class,
        ]);

        $middleware->api(prepend: [
            EnsureFrontendRequestsAreStateful::class,
        ]);
        $middleware->validateCsrfTokens(except: [
            'api/v1/openapi/webhook',
        ]);
        // Trust all proxies — required for correct URL generation behind reverse proxy (Uncloud/Caddy)
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->report(function (Throwable $throwable): void {
            $telemetry = app(PostHogTelemetryService::class);

            if (! $telemetry->shouldReportException($throwable)) {
                return;
            }

            $telemetry->captureException(
                $throwable,
                $telemetry->exceptionContext($throwable, app()->bound('request') ? request() : null),
                app()->bound('request') ? request()->user() : null
            );
        });
    })->create();
