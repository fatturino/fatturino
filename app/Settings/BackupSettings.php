<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class BackupSettings extends Settings
{
    public bool $enabled;

    // Schedule
    public string $frequency;

    public string $time;

    // Used only when frequency = 'weekly' (0 = Sunday, 6 = Saturday)
    public int $day_of_week;

    // Used only when frequency = 'monthly' (1–28)
    public int $day_of_month;

    // S3 credentials (self-hosted only, override .env AWS_*)
    public ?string $aws_access_key_id;

    public ?string $aws_secret_access_key;

    public ?string $aws_default_region;

    public ?string $aws_bucket;

    public ?string $aws_endpoint;

    public bool $aws_use_path_style_endpoint;

    public static function group(): string
    {
        return 'backup';
    }

    public static function encrypted(): array
    {
        return ['aws_secret_access_key'];
    }

    /**
     * Returns true when all required S3 credentials are populated.
     */
    public function hasCredentials(): bool
    {
        return filled($this->aws_access_key_id)
            && filled($this->aws_secret_access_key)
            && filled($this->aws_bucket);
    }
}
