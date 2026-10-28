<?php

use App\Contracts\EnvironmentCapabilities;
use App\Contracts\LoginCustomizer;
use App\Jobs\RunTenantScheduledTask;
use App\Models\Tenant;
use App\Providers\CloudTenancyServiceProvider;
use App\Services\DemoCapabilities;
use App\Services\DemoLoginCustomizer;
use App\Services\NullLoginCustomizer;
use App\Services\UnrestrictedCapabilities;
use App\Tenancy\ApplicationConfigurationTenancyBootstrapper;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Artisan;
use Stancl\Tenancy\TenancyServiceProvider;

test('self-hosted mode does not register tenancy services', function () {
    expect(config('cloud.enabled'))->toBeFalse()
        ->and(app()->getProviders(TenancyServiceProvider::class))->toBeEmpty();
});

test('tenant migrations include the complete schema before settings migrations', function () {
    $paths = config('tenancy.migration_parameters.--path');

    expect($paths)->toBe([database_path('migrations'), database_path('settings')])
        ->and(database_path('migrations/2026_01_01_000001_create_settings_table.php'))->toBeFile()
        ->and(database_path('settings/2026_01_02_000001_create_backup_settings.php'))->toBeFile();
});

test('cloud mode activates the tenant database connection during tenancy initialization', function () {
    config()->set('cloud.enabled', true);
    config()->set('database.connections.central', config('database.connections.sqlite'));
    config()->set('tenancy.database.central_connection', 'central');
    config()->set('tenancy.database.template_tenant_connection', 'sqlite');

    app()->register(TenancyServiceProvider::class);
    (new CloudTenancyServiceProvider(app()))->boot();

    $tenant = new Tenant([
        'id' => 'cloud-tenant',
        'tenancy_db_name' => ':memory:',
        'app_key' => 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=',
        'app_url' => 'https://cloud-tenant.fatturino.test',
        'app_instance_id' => 'cloud-tenant',
    ]);

    tenancy()->initialize($tenant);

    expect(config('database.default'))->toBe('tenant');

    tenancy()->end();

    expect(config('database.default'))->toBe('central');

    config()->set('database.default', 'sqlite');
    app('db')->setDefaultConnection('sqlite');
});

test('cloud mode applies and restores the tenant demo configuration', function () {
    config()->set('cloud.enabled', true);
    config()->set('database.connections.central', config('database.connections.sqlite'));
    config()->set('database.connections.central.database', ':memory:');
    config()->set('tenancy.central_domains', ['cloud.fatturino.test']);
    config()->set('tenancy.database.central_connection', 'central');
    config()->set('tenancy.database.template_tenant_connection', 'sqlite');
    config()->set('demo.enabled', false);

    $provider = new CloudTenancyServiceProvider(app());
    $provider->register();
    $provider->boot();

    $tenant = new Tenant([
        'id' => 'demo-tenant',
        'tenancy_db_name' => ':memory:',
        'demo_enabled' => true,
        'app_key' => 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=',
        'app_url' => 'https://demo.fatturino.test',
        'app_instance_id' => 'demo',
    ]);

    tenancy()->initialize($tenant);

    expect(config('demo.enabled'))->toBeTrue()
        ->and(app(LoginCustomizer::class))->toBeInstanceOf(DemoLoginCustomizer::class)
        ->and(app(EnvironmentCapabilities::class))->toBeInstanceOf(DemoCapabilities::class);

    tenancy()->end();

    expect(config('demo.enabled'))->toBeFalse()
        ->and(app(LoginCustomizer::class))->toBeInstanceOf(NullLoginCustomizer::class)
        ->and(app(EnvironmentCapabilities::class))->toBeInstanceOf(UnrestrictedCapabilities::class);

    config()->set('database.default', 'sqlite');
    app('db')->setDefaultConnection('sqlite');
});

test('cloud mode applies tenant application configuration and restores the central configuration', function () {
    config()->set('cloud.enabled', true);
    config()->set('database.connections.central', config('database.connections.sqlite'));
    config()->set('database.connections.central.database', ':memory:');
    config()->set('tenancy.central_domains', ['cloud.fatturino.test']);
    config()->set('tenancy.database.central_connection', 'central');
    config()->set('tenancy.database.template_tenant_connection', 'sqlite');
    config()->set('app.key', 'base64://////////////////////////////////////////8=');
    config()->set('app.url', 'https://central.fatturino.test');
    config()->set('app.instance_id', 'central');
    config()->set('fe-openapi.api_token', 'general-token');
    config()->set('fe-openapi.sandbox', false);
    config()->set('backup.backup.name', 'backups/central');
    config()->set('backup.backup.source.databases', ['central']);
    config()->set('filesystems.disks.documents.driver', 's3');
    config()->set('filesystems.disks.documents.root', 'central/documents');

    $provider = new CloudTenancyServiceProvider(app());
    $provider->register();
    $provider->boot();

    $tenant = new Tenant([
        'id' => 'configured-tenant',
        'tenancy_db_name' => ':memory:',
        'app_key' => 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=',
        'app_url' => 'https://configured.fatturino.test',
        'app_instance_id' => 'configured',
        'openapi_sdi_api_token' => 'tenant-token',
        'openapi_sdi_sandbox' => true,
    ]);

    tenancy()->initialize($tenant);

    expect(config('app.key'))->toBe($tenant->getAttribute('app_key'))
        ->and(config('app.url'))->toBe('https://configured.fatturino.test')
        ->and(config('app.instance_id'))->toBe('configured')
        ->and(config('fe-openapi.api_token'))->toBe('tenant-token')
        ->and(config('fe-openapi.sandbox'))->toBeTrue()
        ->and(config('backup.backup.name'))->toBe('backups/configured')
        ->and(config('backup.backup.source.databases'))->toBe(['tenant'])
        ->and(config('filesystems.disks.documents.root'))->toBe('configured/documents');

    tenancy()->end();

    expect(config('app.key'))->toBe('base64://////////////////////////////////////////8=')
        ->and(config('app.url'))->toBe('https://central.fatturino.test')
        ->and(config('app.instance_id'))->toBe('central')
        ->and(config('fe-openapi.api_token'))->toBe('general-token')
        ->and(config('fe-openapi.sandbox'))->toBeFalse()
        ->and(config('backup.backup.name'))->toBe('backups/central')
        ->and(config('backup.backup.source.databases'))->toBe(['central'])
        ->and(config('filesystems.disks.documents.root'))->toBe('central/documents');

    $inheritingTenant = new Tenant([
        'id' => 'inheriting-tenant',
        'tenancy_db_name' => ':memory:',
        'app_key' => 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=',
        'app_url' => 'https://inheriting.fatturino.test',
        'app_instance_id' => 'inheriting',
    ]);

    tenancy()->initialize($inheritingTenant);

    expect(config('fe-openapi.api_token'))->toBe('general-token')
        ->and(config('fe-openapi.sandbox'))->toBeFalse();

    tenancy()->end();

    config()->set('database.default', 'sqlite');
    app('db')->setDefaultConnection('sqlite');
});

test('cloud mode resets encryption when switching tenants', function () {
    config()->set('cloud.enabled', true);
    config()->set('database.connections.central', config('database.connections.sqlite'));
    config()->set('database.connections.central.database', ':memory:');
    config()->set('tenancy.central_domains', ['cloud.fatturino.test']);
    config()->set('tenancy.database.central_connection', 'central');
    config()->set('tenancy.database.template_tenant_connection', 'sqlite');

    $provider = new CloudTenancyServiceProvider(app());
    $provider->register();
    $provider->boot();

    $first = new Tenant([
        'id' => 'first-tenant',
        'tenancy_db_name' => ':memory:',
        'app_key' => 'base64:'.base64_encode(str_repeat('a', 32)),
        'app_url' => 'https://first.fatturino.test',
        'app_instance_id' => 'first',
    ]);
    $second = new Tenant([
        'id' => 'second-tenant',
        'tenancy_db_name' => ':memory:',
        'app_key' => 'base64:'.base64_encode(str_repeat('b', 32)),
        'app_url' => 'https://second.fatturino.test',
        'app_instance_id' => 'second',
    ]);

    tenancy()->initialize($first);
    $encrypted = app('encrypter')->encryptString('first tenant value');
    tenancy()->end();

    tenancy()->initialize($second);

    expect(fn (): string => app('encrypter')->decryptString($encrypted))->toThrow(DecryptException::class);

    tenancy()->end();
    config()->set('database.default', 'sqlite');
    app('db')->setDefaultConnection('sqlite');
});

test('Cloud scheduled jobs execute inside their tenant context', function () {
    config()->set('cloud.enabled', true);
    config()->set('database.connections.central', config('database.connections.sqlite'));
    config()->set('database.connections.central.database', ':memory:');
    config()->set('tenancy.central_domains', ['cloud.fatturino.test']);
    config()->set('tenancy.database.central_connection', 'central');
    config()->set('tenancy.database.template_tenant_connection', 'sqlite');
    config()->set('demo.enabled', false);

    $provider = new CloudTenancyServiceProvider(app());
    $provider->register();
    $provider->boot();

    $tenant = new Tenant([
        'id' => 'scheduled-tenant',
        'tenancy_db_name' => ':memory:',
        'app_key' => 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=',
        'app_url' => 'https://scheduled.fatturino.test',
        'app_instance_id' => 'scheduled',
    ]);

    tenancy()->initialize($tenant);

    Artisan::shouldReceive('call')
        ->once()
        ->with('openapi:reconcile')
        ->andReturn(0);

    (new RunTenantScheduledTask(RunTenantScheduledTask::OPENAPI_RECONCILE, 'scheduled-tenant'))->handle();

    expect(config('app.instance_id'))->toBe('scheduled');

    tenancy()->end();
    config()->set('database.default', 'sqlite');
    app('db')->setDefaultConnection('sqlite');
});

test('Cloud mode isolates local document storage per tenant', function () {
    config()->set('filesystems.disks.documents.driver', 'local');
    config()->set('filesystems.disks.documents.root', storage_path('app/private'));

    $tenant = new Tenant([
        'app_key' => 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=',
        'app_url' => 'https://local-documents.fatturino.test',
        'app_instance_id' => 'local-documents',
    ]);
    $tenant->setAttribute('id', 123);
    $bootstrapper = app(ApplicationConfigurationTenancyBootstrapper::class);

    $bootstrapper->bootstrap($tenant);

    expect(config('filesystems.disks.documents.root'))->toBe(storage_path('tenant123/app/private'));

    $bootstrapper->revert();

    expect(config('filesystems.disks.documents.root'))->toBe(storage_path('app/private'));
});
