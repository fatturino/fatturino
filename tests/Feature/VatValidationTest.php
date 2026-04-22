<?php

use App\Rules\ItalianTaxCode;
use App\Rules\ItalianVatNumber;
use Illuminate\Support\Facades\Validator;

// --- Partita IVA (ItalianVatNumber) ---

it('accepts a valid Italian VAT number', function (string $value) {
    $validator = Validator::make(
        ['vat' => $value],
        ['vat' => ['nullable', new ItalianVatNumber]]
    );

    expect($validator->passes())->toBeTrue();
})->with([
    '01234567897',       // Valid check digit 7
    '12345678903',       // Valid check digit 3
    'IT01234567897',     // With IT prefix
    '00000000000',       // Edge case: all zeros
]);

it('rejects an invalid Italian VAT number', function (string $value) {
    $validator = Validator::make(
        ['vat' => $value],
        ['vat' => ['nullable', new ItalianVatNumber]]
    );

    expect($validator->fails())->toBeTrue();
})->with([
    '01234567890',       // Wrong check digit (should be 7)
    '12345678901',       // Wrong check digit (should be 3)
    '1234567890',        // Too short (10 digits)
    '123456789012',      // Too long (12 digits)
    'ABCDEFGHIJK',       // Letters instead of digits
    '0123456789A',       // Mixed
]);

it('allows null or empty VAT number', function ($value) {
    $validator = Validator::make(
        ['vat' => $value],
        ['vat' => ['nullable', new ItalianVatNumber]]
    );

    expect($validator->passes())->toBeTrue();
})->with([null, '']);

// --- Codice Fiscale (ItalianTaxCode) ---

it('accepts a valid numeric tax code (company)', function (string $value) {
    $validator = Validator::make(
        ['tax' => $value],
        ['tax' => ['nullable', new ItalianTaxCode]]
    );

    expect($validator->passes())->toBeTrue();
})->with([
    '01234567897',       // Same algorithm as VAT
    '12345678903',
]);

it('accepts a valid personal tax code (16 chars)', function (string $value) {
    $validator = Validator::make(
        ['tax' => $value],
        ['tax' => ['nullable', new ItalianTaxCode]]
    );

    expect($validator->passes())->toBeTrue();
})->with([
    'RSSMRA85M01H501Q',  // Valid CF (check char Q)
    'BNCLRA92E45F205F',  // Valid CF (check char F)
]);

it('rejects an invalid personal tax code', function (string $value) {
    $validator = Validator::make(
        ['tax' => $value],
        ['tax' => ['nullable', new ItalianTaxCode]]
    );

    expect($validator->fails())->toBeTrue();
})->with([
    'RSSMRA85M01H501A',  // Wrong check character (should be Q)
    'BNCLRA92E45F205X',  // Wrong check character (should be F)
    '01234567890',        // Wrong check digit (company format)
    'ABC',                // Too short
    '12345',              // Random digits
]);

it('allows null or empty tax code', function ($value) {
    $validator = Validator::make(
        ['tax' => $value],
        ['tax' => ['nullable', new ItalianTaxCode]]
    );

    expect($validator->passes())->toBeTrue();
})->with([null, '']);
