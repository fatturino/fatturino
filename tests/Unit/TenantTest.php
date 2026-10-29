<?php

declare(strict_types=1);

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Stancl\Tenancy\Database\Models\Domain;
use Stancl\Tenancy\DatabaseConfig;

test('a Cloud tenant provides a database configuration', function (): void {
    $tenant = new Tenant(['id' => 'tenant-123']);

    expect($tenant->database())->toBeInstanceOf(DatabaseConfig::class);
});

test('a Cloud tenant provides a domain relationship', function (): void {
    config()->set('database.connections.central', config('database.connections.sqlite'));
    config()->set('tenancy.domain_model', Domain::class);
    $tenant = new Tenant(['id' => 'tenant-123']);

    expect($tenant->domains())->toBeInstanceOf(HasMany::class)
        ->and($tenant->domains()->getRelated())->toBeInstanceOf(config('tenancy.domain_model'));
});
