<?php

declare(strict_types=1);

namespace App\Tenancy;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Storage;
use LogicException;
use Stancl\Tenancy\Contracts\TenancyBootstrapper;
use Stancl\Tenancy\Contracts\Tenant;

final class ApplicationConfigurationTenancyBootstrapper implements TenancyBootstrapper
{
    /** @var array<string, mixed> */
    private array $originalConfig = [];

    public function __construct(private readonly Repository $config) {}

    public function bootstrap(Tenant $tenant): void
    {
        $overrides = [
            'app.key' => $tenant->getAttribute('app_key'),
            'app.url' => $tenant->getAttribute('app_url'),
            'app.instance_id' => $tenant->getAttribute('app_instance_id'),
        ];

        if (array_filter($overrides, static fn (mixed $value): bool => ! is_string($value) || $value === '') !== []) {
            throw new LogicException('Tenant application configuration is incomplete.');
        }

        foreach ($overrides as $key => $value) {
            $this->originalConfig[$key] = $this->config->get($key);
            $this->config->set($key, $value);
        }

        $this->overrideBackupConfiguration((string) $overrides['app.instance_id']);
        $this->overrideDocumentStorageConfiguration($tenant, (string) $overrides['app.instance_id']);

        $openApiOverrides = array_filter([
            'fe-openapi.api_token' => $tenant->getAttribute('openapi_sdi_api_token'),
            'fe-openapi.sandbox' => $tenant->getAttribute('openapi_sdi_sandbox'),
        ], static fn (mixed $value): bool => $value !== null && $value !== '');

        foreach ($openApiOverrides as $key => $value) {
            $this->originalConfig[$key] = $this->config->get($key);
            $this->config->set($key, $value);
        }

        $this->resetEncrypter();
    }

    public function revert(): void
    {
        Storage::forgetDisk('documents');

        foreach ($this->originalConfig as $key => $value) {
            $this->config->set($key, $value);
        }

        $this->originalConfig = [];
        $this->resetEncrypter();
    }

    private function resetEncrypter(): void
    {
        app()->forgetInstance('encrypter');
        Facade::clearResolvedInstance('encrypter');
    }

    private function overrideBackupConfiguration(string $instanceId): void
    {
        $overrides = [
            'backup.backup.name' => 'backups/'.$instanceId,
            'backup.backup.source.databases' => ['tenant'],
        ];

        foreach ($overrides as $key => $value) {
            $this->originalConfig[$key] = $this->config->get($key);
            $this->config->set($key, $value);
        }
    }

    private function overrideDocumentStorageConfiguration(Tenant $tenant, string $instanceId): void
    {
        $root = $this->config->get('filesystems.disks.documents.driver') === 's3'
            ? $instanceId.'/documents'
            : storage_path('tenant'.$tenant->getTenantKey().'/app/private');

        $this->originalConfig['filesystems.disks.documents.root'] = $this->config->get('filesystems.disks.documents.root');
        $this->config->set('filesystems.disks.documents.root', $root);
        Storage::forgetDisk('documents');
    }
}
