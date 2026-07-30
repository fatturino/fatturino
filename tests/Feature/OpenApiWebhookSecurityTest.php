<?php

use App\Models\EiInboundLog;
use App\Settings\OpenApiSettings;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function () {
    $settings = app(OpenApiSettings::class);
    $settings->webhook_secret = 'webhook-secret';
    $settings->save();

    RateLimiter::clear('openapi-webhook:'.hash('sha256', '127.0.0.1'));
});

test('unauthorized webhook requests do not create inbound records', function () {
    $dumpFilesBefore = glob(base_path('../webhooks/*.json')) ?: [];

    $this->postJson(route('openapi.webhook'), validWebhookPayload())
        ->assertUnauthorized();

    expect(EiInboundLog::query()->count())->toBe(0)
        ->and(glob(base_path('../webhooks/*.json')) ?: [])->toBe($dumpFilesBefore);
});

test('webhook requests require JSON content type', function () {
    $this->call('POST', route('openapi.webhook'), [], [], [], [
        'HTTP_AUTHORIZATION' => 'Bearer webhook-secret',
        'CONTENT_TYPE' => 'text/plain',
    ], 'event=supplier-invoice')
        ->assertUnsupportedMediaType();

    expect(EiInboundLog::query()->count())->toBe(0);
});

test('oversized webhook requests are rejected before persistence', function () {
    config()->set('fe-openapi.webhook_max_body_bytes', 32);

    $this->call('POST', route('openapi.webhook'), [], [], [], [
        'HTTP_AUTHORIZATION' => 'Bearer webhook-secret',
        'CONTENT_TYPE' => 'application/json',
    ], json_encode(validWebhookPayload(), JSON_THROW_ON_ERROR))
        ->assertStatus(413);

    expect(EiInboundLog::query()->count())->toBe(0);
});

test('malformed webhook JSON is rejected before persistence', function () {
    $this->call('POST', route('openapi.webhook'), [], [], [], [
        'HTTP_AUTHORIZATION' => 'Bearer webhook-secret',
        'CONTENT_TYPE' => 'application/json',
    ], '{invalid-json')
        ->assertStatus(400);

    expect(EiInboundLog::query()->count())->toBe(0);
});

test('webhook rate limit rejects excess authenticated requests before persistence', function () {
    config()->set('fe-openapi.webhook_max_requests_per_minute', 1);

    $this->withHeader('Authorization', 'Bearer webhook-secret')
        ->postJson(route('openapi.webhook'), validWebhookPayload())
        ->assertOk();

    $this->withHeader('Authorization', 'Bearer webhook-secret')
        ->postJson(route('openapi.webhook'), validWebhookPayload())
        ->assertStatus(429);

    expect(EiInboundLog::query()->count())->toBe(1);
});

function validWebhookPayload(): array
{
    return [
        'event' => 'unknown-event',
        'data' => [],
    ];
}
