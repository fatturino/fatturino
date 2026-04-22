<?php

use App\Enums\Capability;

test('each case has a non-empty string value', function () {
    foreach (Capability::cases() as $case) {
        expect($case->value)->toBeString()->not->toBeEmpty();
    }
});

test('has exactly 15 capabilities', function () {
    expect(Capability::cases())->toHaveCount(15);
});

test('EditCompanySettings has the correct string value', function () {
    expect(Capability::EditCompanySettings->value)->toBe('edit-company-settings');
});

test('SendToSdi has the correct string value', function () {
    expect(Capability::SendToSdi->value)->toBe('send-to-sdi');
});
