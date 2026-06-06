<?php

use App\Enums\PaymentStatus;
use App\Enums\SdiStatus;
use App\Enums\VatRate;
use App\Models\Contact;
use App\Models\FiscalDocument;
use App\Models\FiscalDocumentLine;
use App\Models\PurchaseInvoice;
use App\Models\SelfInvoice;

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
    FiscalDocument::create(['number' => 'FT-001', 'date' => now(), 'contact_id' => $contact->id]);
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

    FiscalDocumentLine::create([
        'fiscal_document_id' => $invoice->id,
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

test('purchase invoice date fields serialize as date-only strings', function () {
    $invoice = PurchaseInvoice::factory()->create([
        'date' => '2026-05-05',
        'due_date' => '2026-05-12',
    ]);

    $serialized = $invoice->fresh()->toArray();

    expect($serialized['date'])->toBe('2026-05-05');
    expect($serialized['due_date'])->toBe('2026-05-12');
});

test('createOrUpdateFromSdiData does not create purchase when matching self-invoice is already delivered', function () {
    $contact = Contact::factory()->create();

    $selfInvoice = SelfInvoice::factory()->create([
        'number' => 'AF-LOCK-001',
        'document_type' => 'TD17',
        'sdi_status' => SdiStatus::Delivered,
        'payment_status' => PaymentStatus::Paid,
        'sdi_uuid' => null,
    ]);

    $result = PurchaseInvoice::createOrUpdateFromSdiData([
        'uuid' => '11111111-2222-3333-4444-555555555555',
        'file_id' => 987654,
        'filename' => 'IT_TEST_AF_001.xml',
        'payload' => [
            'fattura_elettronica_body' => [[
                'dati_generali' => [
                    'dati_generali_documento' => [
                        'tipo_documento' => 'TD17',
                        'numero' => 'AF-LOCK-001',
                        'data' => '2024-01-10',
                        'importo_totale_documento' => '122.00',
                    ],
                ],
                'dati_beni_servizi' => [
                    'dati_riepilogo' => [[
                        'imponibile_importo' => '100.00',
                        'imposta' => '22.00',
                    ]],
                ],
            ]],
        ],
    ], $contact);

    expect($result)->toBeNull();
    expect(PurchaseInvoice::withoutGlobalScopes()->where('type', 'purchase')->count())->toBe(0);

    $selfInvoice->refresh();
    expect($selfInvoice->sdi_uuid)->toBe('11111111-2222-3333-4444-555555555555');
});

test('createOrUpdateFromSdiData skips inbound self-invoice when no local self-invoice matches', function () {
    $contact = Contact::factory()->create();

    $result = PurchaseInvoice::createOrUpdateFromSdiData([
        'uuid' => '99999999-2222-3333-4444-555555555555',
        'file_id' => 123456,
        'filename' => 'IT_TEST_AF_999.xml',
        'payload' => [
            'fattura_elettronica_body' => [[
                'dati_generali' => [
                    'dati_generali_documento' => [
                        'tipo_documento' => 'TD17',
                        'numero' => 'AF-MISSING-001',
                        'data' => '2024-01-10',
                        'importo_totale_documento' => '122.00',
                    ],
                ],
                'dati_beni_servizi' => [
                    'dati_riepilogo' => [[
                        'imponibile_importo' => '100.00',
                        'imposta' => '22.00',
                    ]],
                ],
            ]],
        ],
    ], $contact);

    expect($result)->toBeNull()
        ->and(PurchaseInvoice::withoutGlobalScopes()->where('type', 'purchase')->count())->toBe(0)
        ->and(SelfInvoice::withoutGlobalScopes()->count())->toBe(0);
});
