<?php

use App\Models\EiInboundLog;

test('it redacts retained webhook payloads after the configured retention period', function () {
    config()->set('fe-openapi.webhook_payload_retention_days', 7);

    $expired = createInboundLog([
        'raw_payload' => ['sensitive' => 'payload'],
        'created_at' => now()->subDays(8),
    ]);
    $recent = createInboundLog([
        'raw_payload' => ['recent' => 'payload'],
        'created_at' => now()->subDays(6),
    ]);

    $this->artisan('openapi:prune-webhook-payloads')
        ->expectsOutput('1 webhook payload(s) redacted.')
        ->assertExitCode(0);

    expect($expired->refresh()->raw_payload)->toBeNull()
        ->and($recent->refresh()->raw_payload)->toBe(['recent' => 'payload']);
});

test('it supports a dry run without changing retained webhook payloads', function () {
    $log = createInboundLog([
        'raw_payload' => ['sensitive' => 'payload'],
        'created_at' => now()->subDays(8),
    ]);

    $this->artisan('openapi:prune-webhook-payloads --dry-run')
        ->expectsOutput('1 webhook payload(s) would be redacted.')
        ->assertExitCode(0);

    expect($log->refresh()->raw_payload)->toBe(['sensitive' => 'payload']);
});

function createInboundLog(array $attributes): EiInboundLog
{
    return EiInboundLog::query()->create([
        'event_name' => 'supplier-invoice',
        'event_fingerprint' => (string) str()->uuid(),
        'processing_status' => 'processed',
        ...$attributes,
    ]);
}
