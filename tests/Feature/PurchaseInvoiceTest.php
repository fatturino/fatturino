<?php

use App\Enums\PaymentStatus;
use App\Enums\SdiStatus;
use App\Enums\VatRate;
use App\Models\Contact;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\PurchaseInvoice;

test('creating a PurchaseInvoice auto-sets type to purchase', function () {
    $contact = Contact::factory()->create();
    $invoice = PurchaseInvoice::create([
        'number' => 'ACQ-001',
        'date' => now(),
        'contact_id' => $contact->id,
    ]);

    expect($invoice->type)->toBe('purchase');
});

test('global scope filters only purchase invoices', function () {
    $contact = Contact::factory()->create();

    // Create one of each type
    Invoice::create(['number' => 'FT-001', 'date' => now(), 'contact_id' => $contact->id]);
    PurchaseInvoice::create(['number' => 'ACQ-001', 'date' => now(), 'contact_id' => $contact->id]);

    // PurchaseInvoice query should only return purchase type
    expect(PurchaseInvoice::count())->toBe(1);
    expect(PurchaseInvoice::first()->number)->toBe('ACQ-001');
});

test('fiscal_year is auto-set from date on creation', function () {
    $contact = Contact::factory()->create();
    $invoice = PurchaseInvoice::create([
        'number' => 'ACQ-001',
        'date' => '2024-06-15',
        'contact_id' => $contact->id,
    ]);

    expect($invoice->fiscal_year)->toBe(2024);
});

test('isSdiEditable returns true when sdi_status is null', function () {
    $invoice = PurchaseInvoice::factory()->create(['sdi_status' => null]);

    expect($invoice->isSdiEditable())->toBeTrue();
});

test('isSdiEditable returns true when sdi_status is Rejected', function () {
    $invoice = PurchaseInvoice::factory()->create(['sdi_status' => SdiStatus::Rejected]);

    expect($invoice->isSdiEditable())->toBeTrue();
});

test('isSdiEditable returns true when sdi_status is Error', function () {
    $invoice = PurchaseInvoice::factory()->create(['sdi_status' => SdiStatus::Error]);

    expect($invoice->isSdiEditable())->toBeTrue();
});

test('isSdiEditable returns false when sdi_status is Delivered', function () {
    $invoice = PurchaseInvoice::factory()->create(['sdi_status' => SdiStatus::Delivered]);

    expect($invoice->isSdiEditable())->toBeFalse();
});

test('isSdiEditable returns false when sdi_status is Sent', function () {
    $invoice = PurchaseInvoice::factory()->create(['sdi_status' => SdiStatus::Sent]);

    expect($invoice->isSdiEditable())->toBeFalse();
});

test('isOverdue returns true when unpaid with past due_date', function () {
    $invoice = PurchaseInvoice::factory()->create([
        'payment_status' => PaymentStatus::Unpaid,
        'due_date' => now()->subDay(),
    ]);

    expect($invoice->isOverdue())->toBeTrue();
});

test('isOverdue returns false when paid', function () {
    $invoice = PurchaseInvoice::factory()->create([
        'payment_status' => PaymentStatus::Paid,
        'due_date' => now()->subDay(),
    ]);

    expect($invoice->isOverdue())->toBeFalse();
});

test('isOverdue returns false when no due_date', function () {
    $invoice = PurchaseInvoice::factory()->create([
        'payment_status' => PaymentStatus::Unpaid,
        'due_date' => null,
    ]);

    expect($invoice->isOverdue())->toBeFalse();
});

test('calculateTotals sums lines correctly', function () {
    $invoice = PurchaseInvoice::factory()->create();

    InvoiceLine::create([
        'invoice_id' => $invoice->id,
        'description' => 'Service',
        'quantity' => 1,
        'unit_price' => 10000, // 100.00 EUR
        'total' => 10000,
        'vat_rate' => VatRate::R22->value,
    ]);

    $invoice->refresh();

    expect($invoice->total_net)->toBe(10000);
    expect($invoice->total_vat)->toBe(2200); // 22% of 100.00
    expect($invoice->total_gross)->toBe(12200);
});
