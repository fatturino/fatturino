<?php

use App\Settings\CompanySettings;
use App\Settings\OpenApiSettings;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

it('reconfigures webhook when provider status is already active', function () {
    config()->set('app.url', 'https://fatturino.test');

    $companySettings = app(CompanySettings::class);
    $companySettings->company_vat_number = 'IT12345678901';
    $companySettings->save();

    $settings = app(OpenApiSettings::class);
    $settings->api_token = 'token-openapi';
    $settings->sandbox = true;
    $settings->activated = false;
    $settings->webhook_secret = '';
    $settings->webhook_url = '';
    $settings->save();

    Http::fake([
        'https://test.sdi.openapi.it/business_registry_configurations/*' => Http::response([
            'success' => true,
            'data' => ['id' => 'cfg-1'],
        ], 200),
        'https://test.sdi.openapi.it/api_configurations' => Http::response([
            'success' => true,
            'data' => [],
        ], 200),
    ]);

    $exitCode = Artisan::call('openapi:reactivate');

    expect($exitCode)->toBe(0);

    $fresh = app(OpenApiSettings::class);
    expect($fresh->activated)->toBeTrue()
        ->and($fresh->webhook_secret)->not->toBe('')
        ->and(strlen($fresh->webhook_secret))->toBe(64);
});

it('fails when provider status is not active', function () {
    $companySettings = app(CompanySettings::class);
    $companySettings->company_vat_number = 'IT12345678901';
    $companySettings->save();

    $settings = app(OpenApiSettings::class);
    $settings->api_token = 'token-openapi';
    $settings->sandbox = true;
    $settings->activated = false;
    $settings->webhook_secret = '';
    $settings->save();

    Http::fake([
        'https://test.sdi.openapi.it/business_registry_configurations/*' => Http::response([
            'success' => false,
            'message' => 'Business registry configuration not found',
        ], 404),
    ]);

    $exitCode = Artisan::call('openapi:reactivate');

    expect($exitCode)->toBe(1);
});
