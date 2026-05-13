<?php

use App\Enums\VatRate;
use App\Models\Contact;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\User;
use App\Settings\InvoiceSettings;

test('invoice totals are calculated correctly', function () {
    // Create necessary data
    $user = User::factory()->create();
    $contact = Contact::create(['name' => 'Test Client']);
    $product = Product::create([
        'name' => 'Test Product',
        'price' => 10000, // 100.00 EUR in cents
        'vat_rate' => VatRate::R22->value,
    ]);

    // Create Invoice
    $invoice = Invoice::create([
        'number' => '001',
        'date' => now(),
        'contact_id' => $contact->id,
    ]);

    // Add Invoice Line (all monetary values in cents)
    $invoice->lines()->create([
        'product_id' => $product->id,
        'description' => $product->name,
        'quantity' => 2,
        'unit_price' => 10000, // 100.00 EUR in cents
        'vat_rate' => VatRate::R22->value,
        'total' => 20000, // 2 * 10000 = 20000 cents (200.00 EUR)
    ]);

    // Refresh invoice to get updated totals
    $invoice->refresh();

    // Assertions (all values in cents)
    // Net: 2 * 10000 = 20000 cents (200.00 EUR)
    // VAT: 20000 * 0.22 = 4400 cents (44.00 EUR)
    // Gross: 20000 + 4400 = 24400 cents (244.00 EUR)
    expect($invoice->total_net)->toEqual(20000);
    expect($invoice->total_vat)->toEqual(4400);
    expect($invoice->total_gross)->toEqual(24400);
});

test('invoice calculates withholding tax correctly', function () {
    // Create necessary data
    $contact = Contact::create(['name' => 'Test Client']);
    $product = Product::create([
        'name' => 'Test Product',
        'price' => 10000, // 100.00 EUR in cents
        'vat_rate' => VatRate::R22->value,
    ]);

    // Create Invoice with withholding tax enabled
    $invoice = Invoice::create([
        'number' => '001',
        'date' => now(),
        'contact_id' => $contact->id,
        'withholding_tax_enabled' => true,
        'withholding_tax_percent' => 20, // 20% withholding tax
    ]);

    // Add Invoice Line
    $invoice->lines()->create([
        'product_id' => $product->id,
        'description' => $product->name,
        'quantity' => 2,
        'unit_price' => 10000, // 100.00 EUR in cents
        'vat_rate' => VatRate::R22->value,
        'total' => 20000, // 200.00 EUR in cents
    ]);

    // Refresh invoice to get updated totals
    $invoice->refresh();

    // Assertions
    // Net: 20000 cents (200.00 EUR)
    // Withholding tax: 20000 * 0.20 = 4000 cents (40.00 EUR)
    expect($invoice->total_net)->toEqual(20000);
    expect($invoice->withholding_tax_amount)->toEqual(4000);
});

test('invoice applies stamp duty automatically when total exceeds threshold', function () {
    // Enable auto stamp duty in settings
    $settings = app(InvoiceSettings::class);
    $settings->auto_stamp_duty = true;
    $settings->stamp_duty_threshold = '77.47';
    $settings->save();

    // Create necessary data
    $contact = Contact::create(['name' => 'Test Client']);
    $product = Product::create([
        'name' => 'Expensive Product',
        'price' => 10000, // 100.00 EUR in cents
        'vat_rate' => VatRate::R22->value,
    ]);

    // Create Invoice with stamp duty applied (as user would toggle in the form)
    $invoice = Invoice::create([
        'number' => '001',
        'date' => now(),
        'contact_id' => $contact->id,
        'stamp_duty_applied' => true,
        'stamp_duty_amount' => 200,
    ]);

    // Add Invoice Line
    $invoice->lines()->create([
        'product_id' => $product->id,
        'description' => $product->name,
        'quantity' => 1,
        'unit_price' => 10000, // 100.00 EUR in cents
        'vat_rate' => VatRate::R22->value,
        'total' => 10000,
    ]);

    // Refresh invoice to get updated totals
    $invoice->refresh();

    // Assertions
    // Net: 10000 cents (100.00 EUR)
    // VAT: 2200 cents (22.00 EUR)
    // Gross: 12200 cents (122.00 EUR) - exceeds 77.47 threshold
    // Stamp duty: 200 cents (2.00 EUR) - set by user in form
    expect($invoice->total_gross)->toEqual(12200);
    expect($invoice->stamp_duty_applied)->toBeTrue();
    expect($invoice->stamp_duty_amount)->toEqual(200);
});

test('invoice calculates fund contribution correctly', function () {
    $contact = Contact::create(['name' => 'Test Client']);

    // Create invoice with fund (cassa previdenziale) enabled
    $invoice = Invoice::create([
        'number' => '001',
        'date' => now(),
        'contact_id' => $contact->id,
        'fund_enabled' => true,
        'fund_type' => 'TC22', // INPS Gestione Separata
        'fund_percent' => 4,
        'fund_vat_rate' => VatRate::R22->value,
        'fund_has_deduction' => false,
    ]);

    // Add line: €1000.00 net
    $invoice->lines()->create([
        'description' => 'Consulenza',
        'quantity' => 1,
        'unit_price' => 100000, // 1000.00 EUR in cents
        'vat_rate' => VatRate::R22->value,
        'total' => 100000,
    ]);

    $invoice->refresh();

    // Net: 100000 cents (1000.00 EUR)
    // Fund: 100000 * 4% = 4000 cents (40.00 EUR)
    // Line VAT: 100000 * 22% = 22000 cents (220.00 EUR)
    // Fund VAT: 4000 * 22% = 880 cents (8.80 EUR)
    // Total VAT: 22000 + 880 = 22880 cents (228.80 EUR)
    // Gross: 100000 + 4000 + 22880 = 126880 cents (1268.80 EUR)
    expect($invoice->total_net)->toEqual(100000);
    expect($invoice->fund_amount)->toEqual(4000);
    expect($invoice->total_vat)->toEqual(22880);
    expect($invoice->total_gross)->toEqual(126880);
});

test('fund does not affect withholding tax base', function () {
    $contact = Contact::create(['name' => 'Test Client']);

    // Invoice with both fund AND withholding tax
    $invoice = Invoice::create([
        'number' => '001',
        'date' => now(),
        'contact_id' => $contact->id,
        'fund_enabled' => true,
        'fund_type' => 'TC22',
        'fund_percent' => 4,
        'fund_vat_rate' => VatRate::R22->value,
        'withholding_tax_enabled' => true,
        'withholding_tax_percent' => 20,
    ]);

    $invoice->lines()->create([
        'description' => 'Consulenza',
        'quantity' => 1,
        'unit_price' => 100000,
        'vat_rate' => VatRate::R22->value,
        'total' => 100000,
    ]);

    $invoice->refresh();

    // Withholding tax must be on ORIGINAL net (100000), NOT on net + fund (104000)
    // Withholding: 100000 * 20% = 20000 cents (200.00 EUR)
    expect($invoice->withholding_tax_amount)->toEqual(20000);
    expect($invoice->fund_amount)->toEqual(4000);
});

test('invoice without fund has unchanged totals', function () {
    $contact = Contact::create(['name' => 'Test Client']);

    $invoice = Invoice::create([
        'number' => '001',
        'date' => now(),
        'contact_id' => $contact->id,
        'fund_enabled' => false,
    ]);

    $invoice->lines()->create([
        'description' => 'Consulenza',
        'quantity' => 2,
        'unit_price' => 10000,
        'vat_rate' => VatRate::R22->value,
        'total' => 20000,
    ]);

    $invoice->refresh();

    // Same as standard calculation without fund
    expect($invoice->total_net)->toEqual(20000);
    expect($invoice->total_vat)->toEqual(4400);
    expect($invoice->total_gross)->toEqual(24400);
    expect($invoice->fund_amount)->toEqual(0);
});

test('net_due equals total_gross when no withholding or split payment', function () {
    $contact = Contact::create(['name' => 'Test Client']);

    $invoice = Invoice::create([
        'number' => '001',
        'date' => now(),
        'contact_id' => $contact->id,
    ]);

    $invoice->lines()->create([
        'description' => 'Consulenza',
        'quantity' => 1,
        'unit_price' => 100000,
        'vat_rate' => VatRate::R22->value,
        'total' => 100000,
    ]);

    $invoice->refresh();

    // No withholding, no stamp duty, no split payment: net_due === total_gross
    expect($invoice->net_due)->toEqual($invoice->total_gross);
    expect($invoice->net_due)->toEqual(122000); // 100000 + 22000 VAT
});

test('net_due subtracts withholding tax', function () {
    $contact = Contact::create(['name' => 'Test Client']);

    $invoice = Invoice::create([
        'number' => '001',
        'date' => now(),
        'contact_id' => $contact->id,
        'withholding_tax_enabled' => true,
        'withholding_tax_percent' => 20,
    ]);

    $invoice->lines()->create([
        'description' => 'Consulenza',
        'quantity' => 1,
        'unit_price' => 100000, // 1000.00 EUR
        'vat_rate' => VatRate::R22->value,
        'total' => 100000,
    ]);

    $invoice->refresh();

    // Net: 100000, VAT: 22000, Gross: 122000, Withholding: 20000
    // net_due = 122000 - 20000 = 102000 (1020.00 EUR)
    expect($invoice->total_gross)->toEqual(122000);
    expect($invoice->withholding_tax_amount)->toEqual(20000);
    expect($invoice->net_due)->toEqual(102000);
});

test('net_due includes stamp duty and subtracts withholding tax', function () {
    $contact = Contact::create(['name' => 'Test Client']);

    $invoice = Invoice::create([
        'number' => '001',
        'date' => now(),
        'contact_id' => $contact->id,
        'withholding_tax_enabled' => true,
        'withholding_tax_percent' => 20,
        'stamp_duty_applied' => true,
        'stamp_duty_amount' => 200, // 2.00 EUR
    ]);

    $invoice->lines()->create([
        'description' => 'Consulenza',
        'quantity' => 1,
        'unit_price' => 100000,
        'vat_rate' => VatRate::R22->value,
        'total' => 100000,
    ]);

    $invoice->refresh();

    // Gross: 122000, Stamp duty: 200, Withholding: 20000
    // net_due = 122000 + 200 - 20000 = 102200 (1022.00 EUR)
    expect($invoice->total_gross)->toEqual(122000);
    expect($invoice->net_due)->toEqual(102200);
});

test('net_due subtracts vat when split payment is active', function () {
    $contact = Contact::create(['name' => 'Test Client']);

    $invoice = Invoice::create([
        'number' => '001',
        'date' => now(),
        'contact_id' => $contact->id,
        'split_payment' => true,
    ]);

    $invoice->lines()->create([
        'description' => 'Consulenza',
        'quantity' => 1,
        'unit_price' => 100000,
        'vat_rate' => VatRate::R22->value,
        'total' => 100000,
    ]);

    $invoice->refresh();

    // Gross: 122000, VAT: 22000
    // net_due = 122000 - 22000 = 100000 (net only, VAT goes to tax authority)
    expect($invoice->total_gross)->toEqual(122000);
    expect($invoice->total_vat)->toEqual(22000);
    expect($invoice->net_due)->toEqual(100000);
});

test('net_due with split payment and fund keeps fund vat', function () {
    $contact = Contact::create(['name' => 'Test Client']);

    $invoice = Invoice::create([
        'number' => '001',
        'date' => now(),
        'contact_id' => $contact->id,
        'split_payment' => true,
        'fund_enabled' => true,
        'fund_type' => 'TC22',
        'fund_percent' => 4,
        'fund_vat_rate' => VatRate::R22->value,
    ]);

    $invoice->lines()->create([
        'description' => 'Consulenza',
        'quantity' => 1,
        'unit_price' => 100000,
        'vat_rate' => VatRate::R22->value,
        'total' => 100000,
    ]);

    $invoice->refresh();

    // Net: 100000, Fund: 4000, Line VAT: 22000, Fund VAT: 880, Total VAT: 22880, Gross: 126880
    // Split payment subtracts line VAT only (22000), keeps fund VAT (880)
    // net_due = 126880 - (22880 - 880) = 126880 - 22000 = 104880
    expect($invoice->total_gross)->toEqual(126880);
    expect($invoice->total_vat)->toEqual(22880);
    expect($invoice->fund_amount)->toEqual(4000);
    expect($invoice->net_due)->toEqual(104880);
});

test('net_due with withholding, stamp duty and split payment combined', function () {
    $contact = Contact::create(['name' => 'Test Client']);

    $invoice = Invoice::create([
        'number' => '001',
        'date' => now(),
        'contact_id' => $contact->id,
        'withholding_tax_enabled' => true,
        'withholding_tax_percent' => 20,
        'stamp_duty_applied' => true,
        'stamp_duty_amount' => 200,
        'split_payment' => true,
    ]);

    $invoice->lines()->create([
        'description' => 'Consulenza',
        'quantity' => 1,
        'unit_price' => 100000,
        'vat_rate' => VatRate::R22->value,
        'total' => 100000,
    ]);

    $invoice->refresh();

    // Net: 100000, VAT: 22000, Gross: 122000, Stamp: 200, Withholding: 20000
    // net_due = 122000 + 200 - 20000 - 22000 = 80200 (802.00 EUR)
    expect($invoice->total_gross)->toEqual(122000);
    expect($invoice->net_due)->toEqual(80200);
});

test('invoice does not apply stamp duty when total is below threshold', function () {
    // Create necessary data
    $contact = Contact::create(['name' => 'Test Client']);
    $product = Product::create([
        'name' => 'Cheap Product',
        'price' => 5000, // 50.00 EUR in cents
        'vat_rate' => VatRate::R22->value,
    ]);

    // Create Invoice (total will be 61.00 EUR, which is below 77.47 EUR threshold)
    $invoice = Invoice::create([
        'number' => '001',
        'date' => now(),
        'contact_id' => $contact->id,
    ]);

    // Add Invoice Line
    $invoice->lines()->create([
        'product_id' => $product->id,
        'description' => $product->name,
        'quantity' => 1,
        'unit_price' => 5000, // 50.00 EUR in cents
        'vat_rate' => VatRate::R22->value,
        'total' => 5000,
    ]);

    // Refresh invoice to get updated totals
    $invoice->refresh();

    // Assertions
    // Net: 5000 cents (50.00 EUR)
    // VAT: 1100 cents (11.00 EUR)
    // Gross: 6100 cents (61.00 EUR) - below 77.47 threshold
    // Stamp duty: should not be applied
    expect($invoice->total_gross)->toEqual(6100);
    expect($invoice->stamp_duty_applied)->toBeFalse();
    expect($invoice->stamp_duty_amount)->toEqual(0);
});
