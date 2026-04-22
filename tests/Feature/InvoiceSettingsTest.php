<?php

use App\Enums\VatRate;
use App\Models\Sequence;
use App\Settings\InvoiceSettings;

test('invoice settings can be accessed', function () {
    $settings = app(InvoiceSettings::class);

    expect($settings)->toBeInstanceOf(InvoiceSettings::class);
});

test('invoice settings have all default fields', function () {
    $settings = app(InvoiceSettings::class);

    // Check sequence defaults exist (can be null)
    expect($settings)->toHaveProperty('default_sequence_sales');
    expect($settings)->toHaveProperty('default_sequence_purchase');
    expect($settings)->toHaveProperty('default_sequence_quote');
    expect($settings)->toHaveProperty('default_sequence_credit_notes');
    expect($settings)->toHaveProperty('default_sequence_proforma');

    // Check VAT and tax defaults
    expect($settings)->toHaveProperty('default_vat_rate');
    expect($settings->withholding_tax_enabled)->toBeBool();

    // Check stamp duty settings
    expect($settings->auto_stamp_duty)->toBeBool();
    expect($settings->stamp_duty_threshold)->toBeString();

    // Check payment defaults
    expect($settings)->toHaveProperty('default_payment_method');
    expect($settings)->toHaveProperty('default_payment_terms');

    // Check VAT payability
    expect($settings->default_vat_payability)->toBeString();
    expect($settings->default_split_payment)->toBeBool();

    // Check bank details
    expect($settings)->toHaveProperty('default_bank_name');
    expect($settings)->toHaveProperty('default_bank_iban');

    // Check other settings
    expect($settings)->toHaveProperty('default_notes');
    expect($settings->yearly_numbering_reset)->toBeBool();
});

test('invoice settings can be updated', function () {
    $settings = app(InvoiceSettings::class);

    // Create a test sequence and VAT rate
    $sequence = Sequence::create([
        'name'    => 'Test Sequence',
        'pattern' => 'TST-{SEQ}',
        'type'    => 'electronic_invoice',
    ]);

    // Update settings
    $settings->default_sequence_sales = $sequence->id;
    $settings->default_vat_rate_id = VatRate::R22->value;
    $settings->withholding_tax_enabled = true;
    $settings->default_payment_method = 'MP05';
    $settings->save();

    // Refresh settings
    $settings = app(InvoiceSettings::class);

    expect($settings->default_sequence_sales)->toBe($sequence->id);
    expect($settings->default_vat_rate_id)->toBe(VatRate::R22->value);
    expect($settings->withholding_tax_enabled)->toBeTrue();
    expect($settings->default_payment_method)->toBe('MP05');
});

test('invoice settings default payment method accepts valid codes', function () {
    $settings = app(InvoiceSettings::class);

    $validMethods = ['MP01', 'MP05', 'MP08', 'MP23'];

    foreach ($validMethods as $method) {
        $settings->default_payment_method = $method;
        $settings->save();

        $settings = app(InvoiceSettings::class);
        expect($settings->default_payment_method)->toBe($method);
    }
});

test('invoice settings default vat payability accepts valid codes', function () {
    $settings = app(InvoiceSettings::class);

    $validCodes = ['I', 'D', 'S', 'N'];

    foreach ($validCodes as $code) {
        $settings->default_vat_payability = $code;
        $settings->save();

        $settings = app(InvoiceSettings::class);
        expect($settings->default_vat_payability)->toBe($code);
    }
});

test('invoice settings stamp duty threshold can be customized', function () {
    $settings = app(InvoiceSettings::class);

    // Set custom threshold
    $settings->stamp_duty_threshold = '100.00';
    $settings->save();

    $settings = app(InvoiceSettings::class);
    expect($settings->stamp_duty_threshold)->toBe('100.00');

    // Set back to default
    $settings->stamp_duty_threshold = '77.47';
    $settings->save();

    $settings = app(InvoiceSettings::class);
    expect($settings->stamp_duty_threshold)->toBe('77.47');
});

test('invoice settings can set all sequence defaults', function () {
    $sequences = [
        'sales'        => Sequence::create(['name' => 'Sales',    'type' => 'electronic_invoice']),
        'purchase'     => Sequence::create(['name' => 'Purchase', 'type' => 'purchase']),
        'quote'        => Sequence::create(['name' => 'Quote',    'type' => 'quote']),
        'credit_notes' => Sequence::create(['name' => 'Credit',   'type' => 'electronic_invoice']),
        'proforma'     => Sequence::create(['name' => 'Proforma', 'type' => 'proforma']),
    ];

    $settings = app(InvoiceSettings::class);
    $settings->default_sequence_sales = $sequences['sales']->id;
    $settings->default_sequence_purchase = $sequences['purchase']->id;
    $settings->default_sequence_quote = $sequences['quote']->id;
    $settings->default_sequence_credit_notes = $sequences['credit_notes']->id;
    $settings->default_sequence_proforma = $sequences['proforma']->id;
    $settings->save();

    $settings = app(InvoiceSettings::class);
    expect($settings->default_sequence_sales)->toBe($sequences['sales']->id);
    expect($settings->default_sequence_purchase)->toBe($sequences['purchase']->id);
    expect($settings->default_sequence_quote)->toBe($sequences['quote']->id);
    expect($settings->default_sequence_credit_notes)->toBe($sequences['credit_notes']->id);
    expect($settings->default_sequence_proforma)->toBe($sequences['proforma']->id);
});

test('invoice settings use correct group name', function () {
    expect(InvoiceSettings::group())->toBe('invoice');
});
