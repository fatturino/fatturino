<?php

use App\Enums\FundType;

test('each case has a non-empty label', function () {
    foreach (FundType::cases() as $case) {
        expect($case->label())->toBeString()->not->toBeEmpty();
    }
});

test('has exactly 22 cases', function () {
    expect(FundType::cases())->toHaveCount(22);
});

test('defaultPercent returns 2.00 for TC04, TC09, TC11', function () {
    expect(FundType::TC04->defaultPercent())->toBe('2.00');
    expect(FundType::TC09->defaultPercent())->toBe('2.00');
    expect(FundType::TC11->defaultPercent())->toBe('2.00');
});

test('defaultPercent returns 5.00 for TC10', function () {
    expect(FundType::TC10->defaultPercent())->toBe('5.00');
});

test('defaultPercent returns 4.00 for all other cases', function () {
    $defaultFourPercent = [
        FundType::TC01, FundType::TC02, FundType::TC03, FundType::TC05,
        FundType::TC06, FundType::TC07, FundType::TC08, FundType::TC12,
        FundType::TC13, FundType::TC14, FundType::TC15, FundType::TC16,
        FundType::TC17, FundType::TC18, FundType::TC19, FundType::TC20,
        FundType::TC21, FundType::TC22,
    ];

    foreach ($defaultFourPercent as $type) {
        expect($type->defaultPercent())->toBe('4.00', "Expected TC{$type->value} to have 4.00");
    }
});

test('options returns array with 22 entries in id/name format', function () {
    $options = FundType::options();

    expect($options)->toHaveCount(22);
    expect($options[0])->toHaveKeys(['id', 'name']);
    expect($options[0]['id'])->toBe('TC01');
});
