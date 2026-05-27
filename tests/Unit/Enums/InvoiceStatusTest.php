<?php

use App\Enums\InvoiceStatus;

test('each case has a non-empty label', function () {
    foreach (InvoiceStatus::cases() as $case) {
        expect($case->label())->toBeString()->not->toBeEmpty();
    }
});

test('each case has a non-empty color', function () {
    foreach (InvoiceStatus::cases() as $case) {
        expect($case->color())->toBeString()->not->toBeEmpty();
    }
});

test('each case has a non-empty icon', function () {
    foreach (InvoiceStatus::cases() as $case) {
        expect($case->icon())->toBeString()->not->toBeEmpty();
    }
});

test('canValidateXml returns true only for Draft', function () {
    expect(InvoiceStatus::Draft->canValidateXml())->toBeTrue();
    expect(InvoiceStatus::Generated->canValidateXml())->toBeFalse();
    expect(InvoiceStatus::XmlValidated->canValidateXml())->toBeFalse();
    expect(InvoiceStatus::Sent->canValidateXml())->toBeFalse();
});

test('canSendToSdi returns true only for XmlValidated', function () {
    expect(InvoiceStatus::XmlValidated->canSendToSdi())->toBeTrue();
    expect(InvoiceStatus::Draft->canSendToSdi())->toBeFalse();
    expect(InvoiceStatus::Generated->canSendToSdi())->toBeFalse();
    expect(InvoiceStatus::Sent->canSendToSdi())->toBeFalse();
});

test('has exactly 4 cases', function () {
    expect(InvoiceStatus::cases())->toHaveCount(4);
});
