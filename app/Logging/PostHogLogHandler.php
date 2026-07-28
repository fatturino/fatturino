<?php

namespace App\Logging;

use DateTimeInterface;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;

final class PostHogLogHandler extends AbstractProcessingHandler
{
    private const MAX_STRING_LENGTH = 2048;

    private const MAX_DEPTH = 5;

    private const MAX_ITEMS = 50;

    /** @var list<string> */
    private const SENSITIVE_KEYS = [
        'authorization',
        'cookie',
        'password',
        'secret',
        'token',
        'api_key',
        'payload',
        'body',
        'xml',
        'raw_payload',
        'raw_response',
        'response',
        'smtp_password',
        'aws_secret_access_key',
        'iban',
    ];

    private bool $unavailable = false;

    public function __construct(
        private readonly string $token,
        private readonly string $host,
        int|string|Level $level = Level::Debug,
    ) {
        parent::__construct($level, true);
    }

    protected function write(LogRecord $record): void
    {
        if ($this->unavailable || $this->token === '') {
            $this->writeToStderr($record);

            return;
        }

        $payload = json_encode($this->payloadFor($record), JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($payload === false || ! $this->send($payload)) {
            $this->unavailable = true;
            $this->writeToStderr($record);
        }
    }

    /** @return array<string, mixed> */
    public function payloadFor(LogRecord $record): array
    {
        $attributes = [
            ['key' => 'service.name', 'value' => ['stringValue' => (string) config('app.name', 'fatturino')]],
            ['key' => 'service.version', 'value' => ['stringValue' => (string) config('app.version', '')]],
            ['key' => 'deployment.environment.name', 'value' => ['stringValue' => (string) config('app.env', '')]],
            ['key' => 'fatturino.instance_key', 'value' => ['stringValue' => $this->instanceKey()]],
            ['key' => 'log.channel', 'value' => ['stringValue' => $record->channel]],
        ];

        foreach ($this->sanitize($record->context) as $key => $value) {
            $attributes[] = ['key' => 'context.'.$key, 'value' => ['stringValue' => $this->stringify($value)]];
        }

        return [
            'resourceLogs' => [[
                'resource' => ['attributes' => array_slice($attributes, 0, self::MAX_ITEMS)],
                'scopeLogs' => [[
                    'scope' => ['name' => 'fatturino.laravel'],
                    'logRecords' => [[
                        'timeUnixNano' => $this->nanoseconds($record->datetime),
                        'severityNumber' => $this->severityNumber($record->level),
                        'severityText' => $record->level->getName(),
                        'body' => ['stringValue' => $this->truncate($record->message)],
                    ]],
                ]],
            ]],
        ];
    }

    /** @return array<string|int, mixed> */
    public function sanitize(array $context): array
    {
        return $this->sanitizeValue($context, 0);
    }

    private function send(string $payload): bool
    {
        $url = rtrim($this->host, '/').'/i/v1/logs';
        $context = stream_context_create(['http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\nAuthorization: Bearer {$this->token}\r\n",
            'content' => $payload,
            'timeout' => 2,
            'ignore_errors' => true,
        ]]);

        $result = @file_get_contents($url, false, $context);
        $statusLine = $http_response_header[0] ?? '';

        return $result !== false && preg_match('/\s2\d\d\s/', $statusLine) === 1;
    }

    private function writeToStderr(LogRecord $record): void
    {
        $line = sprintf("[%s] %s.%s: %s %s\n", $record->datetime->format('Y-m-d H:i:s'), $record->channel, $record->level->getName(), $record->message, json_encode($this->sanitize($record->context), JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE));
        @file_put_contents('php://stderr', $line, FILE_APPEND);
    }

    private function sanitizeValue(mixed $value, int $depth): mixed
    {
        if ($depth >= self::MAX_DEPTH) {
            return '[truncated]';
        }
        if (is_array($value)) {
            $result = [];
            foreach (array_slice($value, 0, self::MAX_ITEMS, true) as $key => $item) {
                $result[$key] = $this->isSensitiveKey((string) $key) ? '[redacted]' : $this->sanitizeValue($item, $depth + 1);
            }

            return $result;
        }
        if ($value instanceof \Throwable) {
            return ['class' => $value::class, 'message' => $this->truncate($value->getMessage())];
        }
        if (is_object($value)) {
            return ['class' => $value::class];
        }

        return is_string($value) ? $this->truncate($value) : $value;
    }

    private function isSensitiveKey(string $key): bool
    {
        $key = strtolower($key);
        foreach (self::SENSITIVE_KEYS as $sensitive) {
            if (str_contains($key, $sensitive)) {
                return true;
            }
        }

        return false;
    }

    private function stringify(mixed $value): string
    {
        if (is_scalar($value) || $value === null) {
            return $this->truncate((string) $value);
        }

        return $this->truncate((string) json_encode($value, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE));
    }

    private function truncate(string $value): string
    {
        return mb_strimwidth($value, 0, self::MAX_STRING_LENGTH, '…');
    }

    private function instanceKey(): string
    {
        return (string) (config('app.instance_id') ?: config('app.name', 'fatturino'));
    }

    private function nanoseconds(DateTimeInterface $dateTime): string
    {
        return (string) (((int) $dateTime->format('U')) * 1_000_000_000 + ((int) $dateTime->format('u')) * 1_000);
    }

    private function severityNumber(Level $level): int
    {
        return match ($level) {
            Level::Debug => 5,
            Level::Info => 9,
            Level::Notice => 10,
            Level::Warning => 13,
            Level::Error => 17,
            Level::Critical => 18,
            Level::Alert => 21,
            Level::Emergency => 24,
        };
    }
}
