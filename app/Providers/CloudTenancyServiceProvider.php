<?php

namespace App\Providers;

use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Stancl\Tenancy\Events;
use Stancl\Tenancy\Features\TenantConfig;
use Stancl\Tenancy\Listeners;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\TenancyServiceProvider;

class CloudTenancyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if (! config('cloud.enabled')) {
            return;
        }

        $this->validateConfiguration();
        TenantConfig::$storageToConfigMap = [
            ...TenantConfig::$storageToConfigMap,
            'demo_enabled' => 'demo.enabled',
        ];
        $this->app->register(TenancyServiceProvider::class);
    }

    public function boot(): void
    {
        if (! config('cloud.enabled')) {
            return;
        }

        Event::listen(Events\TenancyInitialized::class, Listeners\BootstrapTenancy::class);
        Event::listen(Events\TenancyEnded::class, Listeners\RevertToCentralContext::class);

        app(Router::class)->prependMiddlewareToGroup('web', InitializeTenancyByDomain::class);
        app(Router::class)->prependMiddlewareToGroup('api', InitializeTenancyByDomain::class);
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
