<?php

use Stancl\Tenancy\TenancyServiceProvider;

test('self-hosted mode does not register tenancy services', function () {
    expect(config('cloud.enabled'))->toBeFalse()
        ->and(app()->getProviders(TenancyServiceProvider::class))->toBeEmpty();
});
