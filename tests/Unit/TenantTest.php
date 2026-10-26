<?php

declare(strict_types=1);

use App\Models\Tenant;

test('a Cloud tenant provides a database configuration', function (): void {
    $tenant = new Tenant(['id' => 'tenant-123']);

    expect($tenant->database())->toBeInstanceOf(\Stancl\Tenancy\DatabaseConfig::class);
});
