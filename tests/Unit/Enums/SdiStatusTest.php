<?php

use App\Enums\SdiStatus;

test('each case has a non-empty label', function () {
    foreach (SdiStatus::cases() as $case) {
        expect($case->label())->toBeString()->not->toBeEmpty();
    }
});

test('each case has a non-empty color', function () {
    foreach (SdiStatus::cases() as $case) {
        expect($case->color())->toBeString()->not->toBeEmpty();
    }
});

test('each case has a non-empty icon', function () {
    foreach (SdiStatus::cases() as $case) {
        expect($case->icon())->toBeString()->not->toBeEmpty();
    }
});

test('isEditable returns true only for Rejected and Error', function () {
    expect(SdiStatus::Rejected->isEditable())->toBeTrue();
    expect(SdiStatus::Error->isEditable())->toBeTrue();

    expect(SdiStatus::Sent->isEditable())->toBeFalse();
    expect(SdiStatus::Delivered->isEditable())->toBeFalse();
    expect(SdiStatus::NotDelivered->isEditable())->toBeFalse();
    expect(SdiStatus::Expired->isEditable())->toBeFalse();
    expect(SdiStatus::Accepted->isEditable())->toBeFalse();
    expect(SdiStatus::Refused->isEditable())->toBeFalse();
    expect(SdiStatus::Received->isEditable())->toBeFalse();
});

test('fromNotificationType maps SDI codes correctly', function () {
    expect(SdiStatus::fromNotificationType('NS'))->toBe(SdiStatus::Rejected);
    expect(SdiStatus::fromNotificationType('RC'))->toBe(SdiStatus::Delivered);
    expect(SdiStatus::fromNotificationType('MC'))->toBe(SdiStatus::NotDelivered);
    expect(SdiStatus::fromNotificationType('DT'))->toBe(SdiStatus::Expired);
    expect(SdiStatus::fromNotificationType('NE'))->toBe(SdiStatus::Accepted);
    expect(SdiStatus::fromNotificationType('AT'))->toBe(SdiStatus::Accepted);
    expect(SdiStatus::fromNotificationType('EC'))->toBe(SdiStatus::Refused);
});

test('fromNotificationType returns null for unknown codes', function () {
    expect(SdiStatus::fromNotificationType('XX'))->toBeNull();
    expect(SdiStatus::fromNotificationType(''))->toBeNull();
    expect(SdiStatus::fromNotificationType('ZZ'))->toBeNull();
});

test('fromNotificationType is case insensitive', function () {
    expect(SdiStatus::fromNotificationType('ns'))->toBe(SdiStatus::Rejected);
    expect(SdiStatus::fromNotificationType('rc'))->toBe(SdiStatus::Delivered);
});
