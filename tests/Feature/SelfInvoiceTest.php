<?php

use App\Enums\SdiStatus;
use App\Models\Contact;
use App\Enums\VatRate;
use App\Models\InvoiceLine;
use App\Models\SelfInvoice;

test('creating a SelfInvoice auto-sets type to self_invoice', function () {
    $contact = Contact::factory()->create();
    $invoice = SelfInvoice::create([
        'number' => 'AF-001',
        'date' => now(),
        'contact_id' => $contact->id,
        'document_type' => 'TD17',
    ]);

    expect($invoice->type)->toBe('self_invoice');
});

test('global scope filters only self_invoice type', function () {
    $contact = Contact::factory()->create();

    SelfInvoice::create(['number' => 'AF-001', 'date' => now(), 'contact_id' => $contact->id, 'document_type' => 'TD17']);
    SelfInvoice::create(['number' => 'AF-002', 'date' => now(), 'contact_id' => $contact->id, 'document_type' => 'TD18']);

    expect(SelfInvoice::count())->toBe(2);
    expect(SelfInvoice::all()->pluck('type')->unique()->toArray())->toBe(['self_invoice']);
});

test('fiscal_year is auto-set from date on creation', function () {
    $contact = Contact::factory()->create();
    $invoice = SelfInvoice::create([
        'number' => 'AF-001',
        'date' => '2023-03-10',
        'contact_id' => $contact->id,
        'document_type' => 'TD17',
    ]);

    expect($invoice->fiscal_year)->toBe(2023);
});

test('all self-invoice document types are accepted', function () {
    $contact = Contact::factory()->create();

    foreach (['TD17', 'TD18', 'TD19', 'TD28'] as $i => $type) {
        $invoice = SelfInvoice::create([
            'number' => "AF-{$i}",
            'date' => now(),
            'contact_id' => $contact->id,
            'document_type' => $type,
        ]);
        expect($invoice->document_type)->toBe($type);
    }
});

test('isSdiEditable returns true when sdi_status is null', function () {
    $invoice = SelfInvoice::factory()->create(['sdi_status' => null]);

    expect($invoice->isSdiEditable())->toBeTrue();
});

test('isSdiEditable returns false when sdi_status is Delivered', function () {
    $invoice = SelfInvoice::factory()->create(['sdi_status' => SdiStatus::Delivered]);

    expect($invoice->isSdiEditable())->toBeFalse();
});

test('calculateTotals sums lines correctly', function () {
    $invoice = SelfInvoice::factory()->create();

    InvoiceLine::create([
        'invoice_id' => $invoice->id,
        'description' => 'Service',
        'quantity' => 2,
        'unit_price' => 5000, // 50.00 EUR each
        'total' => 10000, // 100.00 EUR
        'vat_rate' => VatRate::R10->value,
    ]);

    $invoice->refresh();

    expect($invoice->total_net)->toBe(10000);
    expect($invoice->total_vat)->toBe(1000); // 10% of 100.00
    expect($invoice->total_gross)->toBe(11000);
});
