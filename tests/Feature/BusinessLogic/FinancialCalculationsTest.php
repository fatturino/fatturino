<?php

use App\Enums\VatRate;
use App\Models\Invoice;
use App\Models\InvoiceLine;

test('invoice with no lines has all totals at zero', function () {
    $invoice = Invoice::factory()->create();

    expect($invoice->total_net)->toBe(0);
    expect($invoice->total_vat)->toBe(0);
    expect($invoice->total_gross)->toBe(0);
});

test('single line with 0 percent VAT exempt calculates correctly', function () {
    $invoice = Invoice::factory()->create();

    InvoiceLine::create([
        'invoice_id' => $invoice->id,
        'description' => 'Esente',
        'quantity' => 1,
        'unit_price' => 50000, // 500.00 EUR
        'total' => 50000,
        'vat_rate' => VatRate::N2_1->value,
    ]);

    $invoice->refresh();
    expect($invoice->total_net)->toBe(50000);
    expect($invoice->total_vat)->toBe(0);
    expect($invoice->total_gross)->toBe(50000);
});

test('multiple lines with different VAT rates sum correctly', function () {
    $invoice = Invoice::factory()->create();

    // Line 1: 100.00 EUR at 22%
    InvoiceLine::create([
        'invoice_id' => $invoice->id,
        'description' => 'At 22%',
        'quantity' => 1,
        'unit_price' => 10000,
        'total' => 10000,
        'vat_rate' => VatRate::R22->value,
    ]);

    // Line 2: 200.00 EUR at 10%
    InvoiceLine::create([
        'invoice_id' => $invoice->id,
        'description' => 'At 10%',
        'quantity' => 1,
        'unit_price' => 20000,
        'total' => 20000,
        'vat_rate' => VatRate::R10->value,
    ]);

    $invoice->refresh();
    expect($invoice->total_net)->toBe(30000);
    expect($invoice->total_vat)->toBe(2200 + 2000); // 22.00 + 20.00
    expect($invoice->total_gross)->toBe(34200);
});

test('fund contribution is calculated on original net', function () {
    $invoice = Invoice::factory()->create([
        'fund_enabled' => true,
        'fund_percent' => '4.00',
        'fund_vat_rate' => VatRate::R22->value,
    ]);

    InvoiceLine::create([
        'invoice_id' => $invoice->id,
        'description' => 'Consulenza',
        'quantity' => 1,
        'unit_price' => 100000, // 1000.00 EUR
        'total' => 100000,
        'vat_rate' => VatRate::R22->value,
    ]);

    $invoice->refresh();

    // Fund = 4% of 1000 = 40.00 EUR = 4000 cents
    expect($invoice->fund_amount)->toBe(4000);
    // Fund VAT = 22% of 40 = 8.80 EUR = 880 cents
    // Line VAT = 22% of 1000 = 220.00 EUR = 22000 cents
    expect($invoice->total_vat)->toBe(22000 + 880);
    // Gross = net + fund + all VAT
    expect($invoice->total_gross)->toBe(100000 + 4000 + 22000 + 880);
});

test('withholding tax base is the original net, not net plus fund', function () {
    $invoice = Invoice::factory()->create([
        'withholding_tax_enabled' => true,
        'withholding_tax_percent' => '20.00',
        'fund_enabled' => true,
        'fund_percent' => '4.00',
        'fund_vat_rate' => VatRate::R22->value,
    ]);

    InvoiceLine::create([
        'invoice_id' => $invoice->id,
        'description' => 'Test',
        'quantity' => 1,
        'unit_price' => 100000, // 1000.00 EUR
        'total' => 100000,
        'vat_rate' => VatRate::R22->value,
    ]);

    $invoice->refresh();

    // Withholding tax = 20% of 1000 only (not of 1000 + 40 fund)
    expect($invoice->withholding_tax_amount)->toBe(20000); // 200.00 EUR in cents
});

test('stamp duty does not change net or VAT totals', function () {
    $invoice = Invoice::factory()->withStampDuty()->create();

    InvoiceLine::create([
        'invoice_id' => $invoice->id,
        'description' => 'Test',
        'quantity' => 1,
        'unit_price' => 10000,
        'total' => 10000,
        'vat_rate' => VatRate::R22->value,
    ]);

    $invoice->refresh();
    expect($invoice->total_net)->toBe(10000);
    expect($invoice->total_vat)->toBe(2200);
    // Stamp duty does not affect calculateTotals, it is a separate field
    expect($invoice->stamp_duty_amount)->toBe(200);
});

test('deleting all lines resets totals to zero', function () {
    $invoice = Invoice::factory()->create();

    $line = InvoiceLine::create([
        'invoice_id' => $invoice->id,
        'description' => 'Test',
        'quantity' => 1,
        'unit_price' => 10000,
        'total' => 10000,
        'vat_rate' => VatRate::R22->value,
    ]);

    // Use a fresh instance to avoid Eloquent relationship caching issues
    InvoiceLine::find($line->id)->delete();

    $invoice->refresh();
    expect($invoice->total_net)->toBe(0);
    expect($invoice->total_gross)->toBe(0);
});

test('rounding: line total with fractional VAT is rounded to nearest cent', function () {
    // 100/3 EUR = 33.33... EUR -> VAT 22% = 7.333... -> rounded to 7
    $invoice = Invoice::factory()->create();

    InvoiceLine::create([
        'invoice_id' => $invoice->id,
        'description' => 'Test',
        'quantity' => 1,
        'unit_price' => 3333, // 33.33 EUR in cents
        'total' => 3333,
        'vat_rate' => VatRate::R22->value,
    ]);

    $invoice->refresh();
    // 22% of 3333 = 733.26 -> rounded to 733
    expect($invoice->total_vat)->toBe(733);
});
