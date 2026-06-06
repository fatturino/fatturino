<?php

use App\Services\OpenApiSdiService;
use App\Settings\OpenApiSettings;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $settings = app(OpenApiSettings::class);
    $settings->api_token = 'test-token';
    $settings->sandbox = true;
    $settings->save();
});

test('send invoice returns readable error on connection failure', function () {
    Http::fake(function () {
        throw new ConnectionException('timeout');
    });

    $service = app(OpenApiSdiService::class);

    $result = $service->sendInvoice('<xml />');

    expect($result['success'])->toBeFalse()
        ->and($result['error_message'])->toContain('Failed to connect to OpenAPI SDI:')
        ->and($result['error_message'])->toContain('timeout');
});
