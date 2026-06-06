<?php

use App\Enums\PaymentStatus;
use App\Enums\SdiStatus;
use App\Enums\VatRate;
use App\Models\Contact;
use App\Models\FiscalDocumentLine;
use App\Models\Payment;
use App\Models\SelfInvoice;
use App\Models\Sequence;
use App\Models\User;

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

    FiscalDocumentLine::create([
        'fiscal_document_id' => $invoice->id,
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

test('self invoice date fields serialize as date-only strings for the frontend', function () {
    $invoice = SelfInvoice::factory()->create([
        'date' => '2026-05-05',
        'due_date' => '2026-05-12',
        'related_invoice_date' => '2026-05-05',
    ]);

    $payment = Payment::create([
        'fiscal_document_id' => $invoice->id,
        'amount' => 183,
        'paid_at' => '2026-05-05',
    ]);

    $serializedInvoice = $invoice->fresh()->toArray();
    $serializedPayment = $payment->fresh()->toArray();

    expect($serializedInvoice['date'])->toBe('2026-05-05');
    expect($serializedInvoice['due_date'])->toBe('2026-05-12');
    expect($serializedInvoice['related_invoice_date'])->toBe('2026-05-05');
    expect($serializedPayment['paid_at'])->toBe('2026-05-05');
});

test('manual self invoice creation records full payment on invoice date', function () {
    $user = User::factory()->create();
    $contact = Contact::factory()->create();
    $sequence = Sequence::factory()->selfInvoice()->create();

    $response = $this->actingAs($user)->post(route('self-invoices.store'), [
        'contact_id' => $contact->id,
        'sequence_id' => $sequence->id,
        'date' => '2026-06-06',
        'due_date' => null,
        'document_type' => 'TD17',
        'related_invoice_number' => 'SUP-001',
        'related_invoice_date' => '2026-06-05',
        'notes' => 'Autofattura test',
        'lines' => [[
            'description' => 'Servizio estero',
            'quantity' => 1,
            'unit_of_measure' => null,
            'unit_price' => 100,
            'vat_rate' => VatRate::R22->value,
        ]],
    ]);

    $response->assertRedirect(route('self-invoices.index'));

    $invoice = SelfInvoice::query()->latest('id')->first();

    expect($invoice)->not->toBeNull()
        ->and($invoice->payment_status)->toBe(PaymentStatus::Paid)
        ->and($invoice->total_paid)->toBe($invoice->total_gross)
        ->and($invoice->payments()->count())->toBe(1)
        ->and($invoice->payments()->first()?->paid_at?->toDateString())->toBe('2026-06-06');
});
