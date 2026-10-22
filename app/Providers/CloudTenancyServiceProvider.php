<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Stancl\Tenancy\TenancyServiceProvider;

class CloudTenancyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if (! config('cloud.enabled')) {
            return;
        }

        $this->validateConfiguration();
        $this->app->register(TenancyServiceProvider::class);
    }

    private function validateConfiguration(): void
    {
        $required = [
            'CLOUD_CENTRAL_DB_CONNECTION' => config('database.connections.central.driver'),
            'CLOUD_CENTRAL_DB_DATABASE' => config('database.connections.central.database'),
            'CLOUD_CENTRAL_DOMAINS' => config('tenancy.central_domains'),
        ];

        $missing = array_keys(array_filter($required, fn (mixed $value): bool => blank($value)));

        if ($missing !== []) {
            throw new \LogicException('Cloud tenancy requires: '.implode(', ', $missing));
        }
    }
}
