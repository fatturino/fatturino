<?php

use App\Logging\PostHogLogHandler;
use Monolog\Level;
use Monolog\LogRecord;

function logRecord(Level $level = Level::Warning, array $context = []): LogRecord
{
    return new LogRecord(
        datetime: new DateTimeImmutable('2026-07-28 08:00:00.123456 UTC'),
        channel: 'fe-openapi',
        level: $level,
        message: 'Provider call failed',
        context: $context,
    );
}

test('posthog log handler produces an OTLP record with Fatturino metadata', function () {
    config()->set('app.name', 'Fatturino');
    config()->set('app.env', 'production');
    config()->set('app.version', '1.2.3');
    config()->set('app.instance_id', 'cloud-eu-1');

    $payload = (new PostHogLogHandler('test-key', 'https://eu.i.posthog.com'))->payloadFor(logRecord(context: ['uuid' => 'invoice-123']));
    $resource = $payload['resourceLogs'][0]['resource']['attributes'];
    $record = $payload['resourceLogs'][0]['scopeLogs'][0]['logRecords'][0];

    expect($record)
        ->toMatchArray([
            'timeUnixNano' => '1785225600123456000',
            'severityNumber' => 13,
            'severityText' => 'WARNING',
            'body' => ['stringValue' => 'Provider call failed'],
        ])
        ->and($resource)->toContain(['key' => 'service.name', 'value' => ['stringValue' => 'Fatturino']])
        ->toContain(['key' => 'fatturino.instance_key', 'value' => ['stringValue' => 'cloud-eu-1']])
        ->toContain(['key' => 'context.uuid', 'value' => ['stringValue' => 'invoice-123']]);
});

test('posthog log handler redacts sensitive context recursively', function () {
    $handler = new PostHogLogHandler('test-key', 'https://eu.i.posthog.com');

    expect($handler->sanitize([
        'api_token' => 'top-secret',
        'nested' => ['authorization' => 'Bearer abc', 'uuid' => 'safe'],
        'response_body' => '<xml>fiscal data</xml>',
    ]))->toBe([
        'api_token' => '[redacted]',
        'nested' => ['authorization' => '[redacted]', 'uuid' => 'safe'],
        'response_body' => '[redacted]',
    ]);
});

test('logging defaults to stderr without PostHog and PostHog with a key', function () {
    putenv('POSTHOG_API_KEY');
    unset($_ENV['POSTHOG_API_KEY'], $_SERVER['POSTHOG_API_KEY']);
    $logging = require config_path('logging.php');

    expect($logging['default'])->toBe('stderr')
        ->and($logging['channels']['fe-openapi']['channels'])->toBe(['stderr']);

    putenv('POSTHOG_API_KEY=test-key');
    $_ENV['POSTHOG_API_KEY'] = 'test-key';
    $_SERVER['POSTHOG_API_KEY'] = 'test-key';
    try {
        $logging = require config_path('logging.php');

        expect($logging['default'])->toBe('posthog')
            ->and($logging['channels']['fe-openapi']['channels'])->toBe(['posthog']);
    } finally {
        putenv('POSTHOG_API_KEY');
        unset($_ENV['POSTHOG_API_KEY'], $_SERVER['POSTHOG_API_KEY']);
    }
});
