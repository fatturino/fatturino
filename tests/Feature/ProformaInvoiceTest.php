<?php

use App\Enums\PaymentStatus;
use App\Enums\ProformaStatus;
use App\Enums\VatRate;
use App\Models\Contact;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\ProformaInvoice;

test('creating a ProformaInvoice auto-sets type to proforma', function () {
    $contact = Contact::factory()->create();
    $invoice = ProformaInvoice::create([
        'number' => 'PRO-001',
        'date' => now(),
        'contact_id' => $contact->id,
    ]);

    expect($invoice->type)->toBe('proforma');
});

test('global scope filters only proforma type', function () {
    $contact = Contact::factory()->create();

    ProformaInvoice::create(['number' => 'PRO-001', 'date' => now(), 'contact_id' => $contact->id]);
    ProformaInvoice::create(['number' => 'PRO-002', 'date' => now(), 'contact_id' => $contact->id]);
    Invoice::create(['number' => 'FT-001', 'date' => now(), 'contact_id' => $contact->id]);

    expect(ProformaInvoice::count())->toBe(2);
});

test('fiscal_year is auto-set from date', function () {
    $contact = Contact::factory()->create();
    $invoice = ProformaInvoice::create([
        'number' => 'PRO-001',
        'date' => '2025-11-20',
        'contact_id' => $contact->id,
    ]);

    expect($invoice->fiscal_year)->toBe(2025);
});

test('isConvertible returns true for Draft status without converted invoice', function () {
    $proforma = ProformaInvoice::factory()->create(['status' => ProformaStatus::Draft]);

    expect($proforma->isConvertible())->toBeTrue();
});

test('isConvertible returns true for Sent status without converted invoice', function () {
    $proforma = ProformaInvoice::factory()->create(['status' => ProformaStatus::Sent]);

    expect($proforma->isConvertible())->toBeTrue();
});

test('isConvertible returns false for Converted status', function () {
    $proforma = ProformaInvoice::factory()->create(['status' => ProformaStatus::Converted]);

    expect($proforma->isConvertible())->toBeFalse();
});

test('isConvertible returns false for Cancelled status', function () {
    $proforma = ProformaInvoice::factory()->create(['status' => ProformaStatus::Cancelled]);

    expect($proforma->isConvertible())->toBeFalse();
});

test('isConvertible returns false when a converted invoice already exists', function () {
    $proforma = ProformaInvoice::factory()->create(['status' => ProformaStatus::Draft]);

    // Create the converted invoice pointing back to this proforma
    Invoice::create([
        'number' => 'FT-001',
        'date' => now(),
        'contact_id' => $proforma->contact_id,
        'proforma_id' => $proforma->id,
    ]);

    expect($proforma->isConvertible())->toBeFalse();
});

test('isOverdue returns true when unpaid with past due_date', function () {
    $proforma = ProformaInvoice::factory()->create([
        'payment_status' => PaymentStatus::Unpaid,
        'due_date' => now()->subDays(3),
    ]);

    expect($proforma->isOverdue())->toBeTrue();
});

test('isOverdue returns false when paid', function () {
    $proforma = ProformaInvoice::factory()->create([
        'payment_status' => PaymentStatus::Paid,
        'due_date' => now()->subDay(),
    ]);

    expect($proforma->isOverdue())->toBeFalse();
});

test('calculateTotals includes fund contribution', function () {
    $proforma = ProformaInvoice::factory()->create([
        'fund_enabled' => true,
        'fund_percent' => '4.00',
        'fund_vat_rate' => VatRate::R22->value,
    ]);

    InvoiceLine::create([
        'invoice_id' => $proforma->id,
        'description' => 'Consulenza',
        'quantity' => 1,
        'unit_price' => 100000, // 1000.00 EUR
        'total' => 100000,
        'vat_rate' => VatRate::R22->value,
    ]);

    $proforma->refresh();

    // Fund: 4% of 1000 = 40.00 EUR = 4000 cents
    // Fund VAT: 22% of 40 = 8.80 EUR = 880 cents
    // Net VAT: 22% of 1000 = 220.00 EUR = 22000 cents
    expect($proforma->total_net)->toBe(100000);
    expect($proforma->fund_amount)->toBe(4000);
    expect($proforma->total_vat)->toBe(22000 + 880); // line VAT + fund VAT
    expect($proforma->total_gross)->toBe(100000 + 4000 + 22000 + 880);
});
