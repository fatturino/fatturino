<?php

declare(strict_types=1);

namespace App\Tenancy;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Facades\Facade;
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

        $this->resetEncrypter();
    }

    public function revert(): void
    {
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
}
