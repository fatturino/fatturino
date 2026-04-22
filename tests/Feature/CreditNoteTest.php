<?php

use App\Enums\SdiStatus;
use App\Models\Contact;
use App\Models\CreditNote;
use App\Enums\VatRate;
use App\Models\InvoiceLine;

test('creating a CreditNote auto-sets type to credit_note', function () {
    $contact = Contact::factory()->create();
    $creditNote = CreditNote::create([
        'number'     => 'NC-001',
        'date'       => now(),
        'contact_id' => $contact->id,
    ]);

    expect($creditNote->type)->toBe('credit_note');
});

test('creating a CreditNote auto-sets document_type to TD04', function () {
    $contact = Contact::factory()->create();
    $creditNote = CreditNote::create([
        'number'     => 'NC-001',
        'date'       => now(),
        'contact_id' => $contact->id,
    ]);

    expect($creditNote->document_type)->toBe('TD04');
});

test('global scope filters only credit_note type', function () {
    $contact = Contact::factory()->create();

    CreditNote::create(['number' => 'NC-001', 'date' => now(), 'contact_id' => $contact->id]);
    CreditNote::create(['number' => 'NC-002', 'date' => now(), 'contact_id' => $contact->id]);

    expect(CreditNote::count())->toBe(2);
    expect(CreditNote::all()->pluck('type')->unique()->toArray())->toBe(['credit_note']);
});

test('fiscal_year is auto-set from date on creation', function () {
    $contact = Contact::factory()->create();
    $creditNote = CreditNote::create([
        'number'     => 'NC-001',
        'date'       => '2023-06-15',
        'contact_id' => $contact->id,
    ]);

    expect($creditNote->fiscal_year)->toBe(2023);
});

test('isSdiEditable returns true when sdi_status is null', function () {
    $creditNote = CreditNote::factory()->create(['sdi_status' => null]);

    expect($creditNote->isSdiEditable())->toBeTrue();
});

test('isSdiEditable returns false when sdi_status is Delivered', function () {
    $creditNote = CreditNote::factory()->create(['sdi_status' => SdiStatus::Delivered]);

    expect($creditNote->isSdiEditable())->toBeFalse();
});

test('calculateTotals sums lines correctly', function () {
    $creditNote = CreditNote::factory()->create();

    InvoiceLine::create([
        'invoice_id'  => $creditNote->id,
        'description' => 'Reso merce',
        'quantity'    => 1,
        'unit_price'  => 10000, // 100.00 EUR
        'total'       => 10000,
        'vat_rate'    => VatRate::R22->value,
    ]);

    $creditNote->refresh();

    expect($creditNote->total_net)->toBe(10000);
    expect($creditNote->total_vat)->toBe(2200); // 22% of 100.00
    expect($creditNote->total_gross)->toBe(12200);
});

test('getVatSummary groups lines by VAT rate', function () {
    $creditNote = CreditNote::factory()->create();

    InvoiceLine::create([
        'invoice_id'  => $creditNote->id,
        'description' => 'Prodotto A',
        'quantity'    => 1,
        'unit_price'  => 10000,
        'total'       => 10000,
        'vat_rate'    => VatRate::R22->value,
    ]);

    InvoiceLine::create([
        'invoice_id'  => $creditNote->id,
        'description' => 'Prodotto B',
        'quantity'    => 1,
        'unit_price'  => 5000,
        'total'       => 5000,
        'vat_rate'    => VatRate::R10->value,
    ]);

    $creditNote->refresh();
    $vatSummary = $creditNote->getVatSummary();

    expect($vatSummary)->toHaveCount(2);
});

test('related_invoice fields are stored correctly', function () {
    $contact = Contact::factory()->create();
    $creditNote = CreditNote::create([
        'number'                 => 'NC-001',
        'date'                   => now(),
        'contact_id'             => $contact->id,
        'related_invoice_number' => 'FT-2026-001',
        'related_invoice_date'   => '2026-01-15',
    ]);

    expect($creditNote->related_invoice_number)->toBe('FT-2026-001');
    expect($creditNote->related_invoice_date->format('Y-m-d'))->toBe('2026-01-15');
});
