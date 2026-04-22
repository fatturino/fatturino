<?php

use App\Enums\PaymentMethod;

test('each case has a non-empty label', function () {
    foreach (PaymentMethod::cases() as $case) {
        expect($case->label())->toBeString()->not->toBeEmpty();
    }
});

test('has exactly 23 cases', function () {
    expect(PaymentMethod::cases())->toHaveCount(23);
});

test('options returns 23 entries in id/name format', function () {
    $options = PaymentMethod::options();

    expect($options)->toHaveCount(23);
    expect($options[0])->toHaveKeys(['id', 'name']);
    expect($options[0]['id'])->toBe('MP01');
});

test('MP05 is bonifico bancario', function () {
    expect(PaymentMethod::MP05->label())->toBe('Bonifico bancario');
});
