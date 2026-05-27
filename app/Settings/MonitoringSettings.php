<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class MonitoringSettings extends Settings
{
    public bool $enabled;

    // DSN for a Sentry-compatible error tracking service (Sentry SaaS, GlitchTip, etc.)
    public ?string $dsn;

    // Application environment label sent with each error report
    public string $environment;

    // Performance tracing sample rate (0.0 = disabled, 1.0 = 100%)
    public float $traces_sample_rate;

    public static function group(): string
    {
        return 'monitoring';
    }

    public static function encrypted(): array
    {
        return ['dsn'];
    }

    public function isConfigured(): bool
    {
        return $this->enabled && filled($this->dsn);
    }
}
