<?php

use App\Enums\VatPayability;

test('each case has a non-empty label', function () {
    foreach (VatPayability::cases() as $case) {
        expect($case->label())->toBeString()->not->toBeEmpty();
    }
});

test('has exactly 3 cases I, D, S', function () {
    expect(VatPayability::cases())->toHaveCount(3);
    expect(VatPayability::I->value)->toBe('I');
    expect(VatPayability::D->value)->toBe('D');
    expect(VatPayability::S->value)->toBe('S');
});

test('S is scissione dei pagamenti', function () {
    expect(VatPayability::S->label())->toBe('Scissione dei pagamenti');
});

test('options returns 3 entries in id/name format', function () {
    $options = VatPayability::options();

    expect($options)->toHaveCount(3);
    expect($options[0])->toHaveKeys(['id', 'name']);
});
