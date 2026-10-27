<?php

use App\Models\Tenant;
use App\Providers\CloudTenancyServiceProvider;
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
    ]);

    tenancy()->initialize($tenant);

    expect(config('database.default'))->toBe('tenant');

    tenancy()->end();

    expect(config('database.default'))->toBe('central');
});
