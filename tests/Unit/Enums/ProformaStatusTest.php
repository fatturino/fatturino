<?php

use App\Enums\ProformaStatus;

test('each case has a non-empty label', function () {
    foreach (ProformaStatus::cases() as $case) {
        expect($case->label())->toBeString()->not->toBeEmpty();
    }
});

test('each case has a non-empty color', function () {
    foreach (ProformaStatus::cases() as $case) {
        expect($case->color())->toBeString()->not->toBeEmpty();
    }
});

test('each case has a non-empty icon', function () {
    foreach (ProformaStatus::cases() as $case) {
        expect($case->icon())->toBeString()->not->toBeEmpty();
    }
});

test('has exactly 4 cases with correct values', function () {
    expect(ProformaStatus::cases())->toHaveCount(4);
    expect(ProformaStatus::Draft->value)->toBe('draft');
    expect(ProformaStatus::Sent->value)->toBe('sent');
    expect(ProformaStatus::Converted->value)->toBe('converted');
    expect(ProformaStatus::Cancelled->value)->toBe('cancelled');
});
