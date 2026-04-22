<?php

use App\Enums\PaymentStatus;

test('each case has a non-empty label', function () {
    foreach (PaymentStatus::cases() as $case) {
        expect($case->label())->toBeString()->not->toBeEmpty();
    }
});

test('each case has a non-empty color', function () {
    foreach (PaymentStatus::cases() as $case) {
        expect($case->color())->toBeString()->not->toBeEmpty();
    }
});

test('each case has a non-empty icon', function () {
    foreach (PaymentStatus::cases() as $case) {
        expect($case->icon())->toBeString()->not->toBeEmpty();
    }
});

test('has the correct case values', function () {
    expect(PaymentStatus::Unpaid->value)->toBe('unpaid');
    expect(PaymentStatus::Partial->value)->toBe('partial');
    expect(PaymentStatus::Paid->value)->toBe('paid');
    expect(PaymentStatus::Overdue->value)->toBe('overdue');
});
