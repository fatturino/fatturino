<?php

use App\Services\NullSdiProvider;

test('isConfigured returns false', function () {
    $provider = new NullSdiProvider;

    expect($provider->isConfigured())->toBeFalse();
});

test('isActivated returns false', function () {
    $provider = new NullSdiProvider;

    expect($provider->isActivated())->toBeFalse();
});

test('sendInvoice returns failure result', function () {
    $provider = new NullSdiProvider;
    $result = $provider->sendInvoice('<xml/>', 'IT00000000000_00001.xml');

    expect($result['success'])->toBeFalse();
    expect($result)->toHaveKey('error_message');
});

test('validateXml returns invalid result', function () {
    $provider = new NullSdiProvider;
    $result = $provider->validateXml('<xml/>');

    expect($result['valid'])->toBeFalse();
    expect($result['errors'])->not->toBeEmpty();
});

test('getSupplierInvoices returns failure result', function () {
    $provider = new NullSdiProvider;
    $result = $provider->getSupplierInvoices();

    expect($result['success'])->toBeFalse();
});

test('id returns none', function () {
    $provider = new NullSdiProvider;

    expect($provider->id())->toBe('none');
});
