<?php

namespace App\Providers;

use App\Contracts\EnvironmentCapabilities;
use App\Contracts\LoginCustomizer;
use App\Contracts\SdiProvider;
use App\Enums\MenuItem;
use App\Services\MenuRegistry;
use App\Services\NullLoginCustomizer;
use App\Services\NullSdiProvider;
use App\Services\PluginRegistry;
use App\Services\UnrestrictedCapabilities;
use App\Settings\BackupSettings;
use App\Settings\MonitoringSettings;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->bootPlugins();

        // Defaults — singletonIf so plugin providers (registered before app providers) win
        $this->app->singletonIf(EnvironmentCapabilities::class, UnrestrictedCapabilities::class);
        $this->app->singletonIf(LoginCustomizer::class, NullLoginCustomizer::class);
        $this->app->singletonIf(SdiProvider::class, NullSdiProvider::class);

        // Plugin registries. Plugins call register()/add() in their boot().
        $this->app->singleton(MenuRegistry::class);
        $this->app->singleton(PluginRegistry::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (app()->isProduction()) {
            URL::forceHttps(true);
        }

        // Blade directive: @allowed('action') ... @endallowed
        Blade::if('allowed', fn (string $action = '') => $action !== '' && app(EnvironmentCapabilities::class)->can($action));

        // Audit log access restricted to admin users
        Gate::define('viewAuditLog', fn ($user) => (bool) ($user->is_admin ?? false));

        $this->registerCoreMenu();
        $this->applyBackupCredentials();
        $this->applyMonitoringCredentials();
    }

    /**
     * Inject S3 credentials from BackupSettings into the filesystem config at runtime,
     * so self-hosted users can configure S3 from the UI instead of editing .env.
     * Skipped when env manages backups (fatturino-cloud) or when the settings table
     * does not exist yet (first-run migrations).
     */
    /**
     * Inject the Sentry DSN from MonitoringSettings into the sentry config at runtime,
     * so self-hosted users can configure error tracking from the UI instead of editing .env.
     * Skipped when env manages monitoring (fatturino-cloud) or when the settings table
     * does not exist yet (first-run migrations).
     */
    private function applyMonitoringCredentials(): void
    {
        if (config('monitoring.managed_by_env')) {
            return;
        }

        try {
            $monitoring = app(MonitoringSettings::class);

            if (! $monitoring->isConfigured()) {
                return;
            }

            config([
                'sentry.dsn' => $monitoring->dsn,
                'sentry.environment' => $monitoring->environment,
                'sentry.traces_sample_rate' => $monitoring->traces_sample_rate,
            ]);
        } catch (\Throwable) {
            // Settings table not yet created (first migration run) — skip silently.
        }
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
            // Settings table not yet created (first migration run) — skip silently.
        }
    }

    /**
     * Register core menu items. Plugins can add items relative to these IDs.
     */
    /**
     * Dynamically load plugins found in the plugins/ directory.
     *
     * Reads each plugin's composer.json to register PSR-4 autoloading and
     * service providers at boot time — no modifications to the root composer.json
     * or bootstrap/providers.php are needed when installing plugins.
     */
    private function bootPlugins(): void
    {
        $manifests = glob(base_path('plugins/*/composer.json')) ?: [];
        if (empty($manifests)) {
            return;
        }

        /** @var \Composer\Autoload\ClassLoader $loader */
        $loader = require base_path('vendor/autoload.php');

        foreach ($manifests as $manifestPath) {
            $pluginDir = dirname($manifestPath);
            $manifest = json_decode(file_get_contents($manifestPath), true);

            foreach ($manifest['autoload']['psr-4'] ?? [] as $namespace => $path) {
                $loader->addPsr4($namespace, $pluginDir.'/'.$path);
            }

            foreach ($manifest['extra']['laravel']['providers'] ?? [] as $provider) {
                $this->app->register($provider);
            }
        }
    }

    private function registerCoreMenu(): void
    {
        $menu = app(MenuRegistry::class);

        $menu->add(MenuItem::Dashboard, __('app.nav.dashboard'), 'o-home', '/dashboard');
        $menu->add(MenuItem::Contacts, __('app.nav.contacts'), 'o-users', '/contacts');

        $menu->sub(MenuItem::Sales, __('app.nav.sales'), 'o-document-text');
        $menu->add(MenuItem::SellInvoices, __('app.nav.sell_invoices'), 'o-document-text', '/sell-invoices', parent: MenuItem::Sales);
        $menu->add(MenuItem::CreditNotes, __('app.nav.credit_notes'), 'o-receipt-refund', '/credit-notes', parent: MenuItem::Sales);
        $menu->add(MenuItem::Proforma, __('app.nav.proforma'), 'o-clipboard-document-list', '/proforma', parent: MenuItem::Sales);

        $menu->sub(MenuItem::Purchases, __('app.nav.purchases'), 'o-shopping-cart');
        $menu->add(MenuItem::PurchaseInvoices, __('app.nav.purchase_invoices'), 'o-shopping-cart', '/purchase-invoices', parent: MenuItem::Purchases);
        $menu->add(MenuItem::SelfInvoices, __('app.nav.self_invoices'), 'o-globe-alt', '/self-invoices', parent: MenuItem::Purchases);

        $menu->sub(MenuItem::Configuration, __('app.nav.configuration'), 'o-cog-6-tooth');
        $menu->add(MenuItem::Sequences, __('app.nav.sequences'), 'o-list-bullet', '/sequences', parent: MenuItem::Configuration);
        $menu->add(MenuItem::CompanySettings, __('app.nav.company_settings'), 'o-building-office', '/company-settings', parent: MenuItem::Configuration);
        $menu->add(MenuItem::InvoiceSettings, __('app.nav.invoice_settings'), 'o-document-duplicate', '/invoice-settings', parent: MenuItem::Configuration);
        $menu->add(MenuItem::ElectronicInvoiceSettings, __('app.nav.electronic_invoice_settings'), 'o-cpu-chip', '/electronic-invoice-settings', parent: MenuItem::Configuration);
        $menu->add(MenuItem::EmailSettings, __('app.nav.email_settings'), 'o-envelope', '/email-settings', parent: MenuItem::Configuration);

        // Show the Services page when at least one service is not managed externally
        if (! config('backup.managed_by_env') || ! config('monitoring.managed_by_env')) {
            $menu->add(MenuItem::Services, __('app.nav.services'), 'o-cog-8-tooth', '/services', parent: MenuItem::Configuration);
        }

        $menu->add(MenuItem::Imports, __('app.nav.imports'), 'o-arrow-down-tray', '/imports', parent: MenuItem::Configuration);
        $menu->add(MenuItem::Plugins, __('app.nav.plugins'), 'o-puzzle-piece', '/plugins', parent: MenuItem::Configuration);
        $menu->add(MenuItem::AuditLog, __('app.nav.audit_log'), 'o-clipboard-document-check', '/audit-log', parent: MenuItem::Configuration, gate: 'viewAuditLog');
    }
}
