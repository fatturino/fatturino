<?php

use App\Enums\PaymentTerms;

test('each case has a non-empty label', function () {
    foreach (PaymentTerms::cases() as $case) {
        expect($case->label())->toBeString()->not->toBeEmpty();
    }
});

test('options returns 3 entries in id/name format', function () {
    $options = PaymentTerms::options();

    expect($options)->toHaveCount(3);
    expect($options[0])->toHaveKeys(['id', 'name']);
    expect($options[0]['id'])->toBe('TP01');
});
