<?php

use App\Http\Middleware\RequireCapability;
use App\Models\User;
use App\Settings\OpenApiSettings;

it('does not overwrite openapi settings from request when managed by env is enabled', function () {
    config()->set('fe-openapi.managed_by_env', true);
    putenv('OPENAPI_SDI_API_TOKEN=env-token-123');
    putenv('OPENAPI_SDI_SANDBOX=1');

    $user = User::factory()->create();
    $settings = app(OpenApiSettings::class);
    $settings->api_token = 'original-token';
    $settings->sandbox = false;
    $settings->company_sdi_code = 'AAAAAAA';
    $settings->save();

    $this->withoutMiddleware(RequireCapability::class)
        ->actingAs($user)
        ->postJson('/api/openapi/save', [
            'api_token' => 'request-token',
            'sandbox' => false,
            'company_sdi_code' => 'BBBBBBB',
            'webhook_url' => 'https://example.invalid',
        ])
        ->assertOk()
        ->assertJson(['success' => true]);

    $fresh = app(OpenApiSettings::class);

    expect($fresh)->not->toBeNull()
        ->and($fresh->api_token)->toBe('original-token')
        ->and($fresh->sandbox)->toBeFalse();
});
