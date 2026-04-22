<?php

use App\Enums\SalesDocumentType;

test('each case has a non-empty label', function () {
    foreach (SalesDocumentType::cases() as $case) {
        expect($case->label())->toBeString()->not->toBeEmpty();
    }
});

test('TD01 is the standard invoice type', function () {
    expect(SalesDocumentType::TD01->label())->toBe('Fattura');
});

test('options returns array in id/name format', function () {
    $options = SalesDocumentType::options();

    expect($options)->not->toBeEmpty();
    expect($options[0])->toHaveKeys(['id', 'name']);
    expect($options[0]['id'])->toBe('TD01');
});
