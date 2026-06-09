<?php

use App\Services\PostHogTelemetryService;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

test('posthog telemetry builds a namespaced distinct id from app instance id', function () {
    config()->set('app.instance_id', 'tenant-alpha');
    config()->set('app.name', 'Fallback Name');

    $service = app(PostHogTelemetryService::class);

    expect($service->instanceKey())->toBe('tenant-alpha')
        ->and($service->distinctIdFor(7))->toBe('tenant-alpha:user:7');
});

test('posthog telemetry falls back to normalized app name', function () {
    config()->set('app.instance_id', null);
    config()->set('app.name', 'Fatturino Demo');

    $service = app(PostHogTelemetryService::class);

    expect($service->instanceKey())->toBe('fatturino-demo')
        ->and($service->distinctIdFor('15'))->toBe('fatturino-demo:user:15');
});

test('posthog telemetry filters expected exceptions from reporting', function () {
    $service = app(PostHogTelemetryService::class);

    expect($service->shouldReportException(ValidationException::withMessages(['email' => ['Required']])))
        ->toBeFalse()
        ->and($service->shouldReportException(new TokenMismatchException))
        ->toBeFalse()
        ->and($service->shouldReportException(new NotFoundHttpException))
        ->toBeFalse()
        ->and($service->shouldReportException(new RuntimeException('Boom')))
        ->toBeTrue();
});
