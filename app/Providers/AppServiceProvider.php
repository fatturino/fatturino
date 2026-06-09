<?php

namespace App\Providers;

use App\Contracts\EnvironmentCapabilities;
use App\Contracts\LoginCustomizer;
use App\Contracts\SdiProvider;
use App\Services\DemoCapabilities;
use App\Services\DemoLoginCustomizer;
use App\Services\NullLoginCustomizer;
use App\Services\OpenApiSdiProvider;
use App\Services\OpenApiSdiService;
use App\Services\PostHogTelemetryService;
use App\Services\UnrestrictedCapabilities;
use App\Settings\BackupSettings;
use App\Settings\OpenApiSettings;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use PostHog\PostHog;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(OpenApiSdiService::class);
        $this->app->singleton(PostHogTelemetryService::class);
        $this->app->singleton(SdiProvider::class, OpenApiSdiProvider::class);

        $this->mergeConfigFrom(config_path('fe-openapi.php'), 'fe-openapi');
        $this->app->make('config')->set('logging.channels.fe-openapi', [
            'driver' => 'daily',
            'path' => storage_path('logs/plugin-fe-openapi.log'),
            'level' => 'debug',
            'days' => 14,
        ]);

        $this->registerEnvironmentBindings();

        // In managed environments OpenAPI settings are sourced from env only.
        $this->app->afterResolving(OpenApiSettings::class, function (OpenApiSettings $settings) {
            if ((bool) config('fe-openapi.managed_by_env')) {
                $settings->api_token = (string) config('fe-openapi.api_token', '');
                $settings->sandbox = (bool) config('fe-openapi.sandbox', false);
                $settings->company_sdi_code = (string) config('fe-openapi.company_sdi_code', $settings->company_sdi_code);
                $settings->webhook_url = (string) config('fe-openapi.webhook_url', $settings->webhook_url);
            }
        });

    }

    public function boot(): void
    {
        if (app()->isProduction()) {
            URL::forceHttps(true);
        }

        Blade::if('allowed', fn (string $action = '') => $action !== '' && app(EnvironmentCapabilities::class)->can($action));

        $this->applyBackupCredentials();
        $this->initializePostHog();

        $this->loadViewsFrom(resource_path('views/vendor/fe-openapi'), 'fe-openapi');
        $this->loadTranslationsFrom(lang_path('vendor/fe-openapi'), 'fe-openapi');
        $this->loadMigrationsFrom(base_path('database/settings'));

    }

    private function registerEnvironmentBindings(): void
    {
        if (config('demo.enabled')) {
            $this->app->singleton(EnvironmentCapabilities::class, DemoCapabilities::class);
            $this->app->singleton(LoginCustomizer::class, DemoLoginCustomizer::class);

            return;
        }

        $this->app->singleton(EnvironmentCapabilities::class, UnrestrictedCapabilities::class);
        $this->app->singleton(LoginCustomizer::class, NullLoginCustomizer::class);
    }

    private function applyBackupCredentials(): void
    {
        if (config('backup.managed_by_env')) {
            return;
        }

        try {
            $backup = app(BackupSettings::class);

            if (! $backup->hasCredentials()) {
                return;
            }

            config([
                'filesystems.disks.s3.key' => $backup->aws_access_key_id,
                'filesystems.disks.s3.secret' => $backup->aws_secret_access_key,
                'filesystems.disks.s3.region' => $backup->aws_default_region,
                'filesystems.disks.s3.bucket' => $backup->aws_bucket,
                'filesystems.disks.s3.endpoint' => $backup->aws_endpoint,
                'filesystems.disks.s3.use_path_style_endpoint' => $backup->aws_use_path_style_endpoint,
            ]);
        } catch (\Throwable) {
            // Settings table not yet created (first migration run) - skip silently.
        }
    }

    private function initializePostHog(): void
    {
        $apiKey = (string) config('services.posthog.api_key', '');
        if ($apiKey === '' || ! class_exists(PostHog::class)) {
            return;
        }

        PostHog::init($apiKey, [
            'host' => (string) config('services.posthog.host', 'https://eu.i.posthog.com'),
        ]);
    }
}
