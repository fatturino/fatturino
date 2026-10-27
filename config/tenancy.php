<?php

declare(strict_types=1);
use Stancl\Tenancy\Bootstrappers\CacheTenancyBootstrapper;
use Stancl\Tenancy\Bootstrappers\DatabaseTenancyBootstrapper;
use Stancl\Tenancy\Bootstrappers\FilesystemTenancyBootstrapper;
use Stancl\Tenancy\Bootstrappers\QueueTenancyBootstrapper;
use Stancl\Tenancy\TenantDatabaseManagers\MySQLDatabaseManager;
use Stancl\Tenancy\TenantDatabaseManagers\PostgreSQLDatabaseManager;
use Stancl\Tenancy\TenantDatabaseManagers\SQLiteDatabaseManager;
use App\Models\Tenant;

return [
    'tenant_model' => Tenant::class,

    'central_domains' => array_values(array_filter(explode(',', (string) env('CLOUD_CENTRAL_DOMAINS', '')))),

    'bootstrappers' => [
        DatabaseTenancyBootstrapper::class,
        CacheTenancyBootstrapper::class,
        FilesystemTenancyBootstrapper::class,
        QueueTenancyBootstrapper::class,
    ],

    'database' => [
        'central_connection' => 'central',
        'template_tenant_connection' => env('CLOUD_TENANT_DB_CONNECTION', env('DB_CONNECTION', 'pgsql')),
        'prefix' => env('CLOUD_TENANT_DB_PREFIX', 'fatturino_tenant_'),
        'suffix' => '',
        'managers' => [
            'sqlite' => SQLiteDatabaseManager::class,
            'mysql' => MySQLDatabaseManager::class,
            'mariadb' => MySQLDatabaseManager::class,
            'pgsql' => PostgreSQLDatabaseManager::class,
        ],
    ],

    'cache' => [
        'tag_base' => 'fatturino-tenant',
    ],

    'filesystem' => [
        'suffix_base' => 'tenant',
        'disks' => ['local', 'public', 'documents'],
        'root_override' => [
            'local' => '%storage_path%/app/private',
            'public' => '%storage_path%/app/public',
            'documents' => '%storage_path%/app/private',
        ],
        'suffix_storage_path' => true,
        'asset_helper_tenancy' => false,
    ],

    'migration_parameters' => [
        '--force' => true,
        '--path' => [database_path('migrations'), database_path('settings')],
        '--realpath' => true,
    ],

    'seeder_parameters' => [
        '--class' => 'DatabaseSeeder',
        '--force' => true,
    ],
];
