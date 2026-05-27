<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class OpenApiSettings extends Settings
{
    public string $api_token;

    public bool $sandbox;

    public string $company_sdi_code;

    public bool $activated;

    public string $webhook_secret;

    public string $webhook_url;

    public static function group(): string
    {
        return 'openapi';
    }
}
