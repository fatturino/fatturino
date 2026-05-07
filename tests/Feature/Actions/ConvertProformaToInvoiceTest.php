<?php

use App\Actions\ConvertProformaToInvoice;
use App\Enums\InvoiceStatus;
use App\Enums\ProformaStatus;
use App\Enums\VatRate;
use App\Models\Contact;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\ProformaInvoice;
use App\Models\Sequence;

test('converts a Draft proforma to a sales invoice', function () {
    $sequence = Sequence::factory()->create(['type' => 'electronic_invoice', 'is_system' => true]);
    $contact = Contact::factory()->create();
    $proforma = ProformaInvoice::factory()->create([
        'contact_id' => $contact->id,
        'status' => ProformaStatus::Draft,
    ]);

    $action = app(ConvertProformaToInvoice::class);
    $invoice = $action->execute($proforma);

    expect($invoice)->toBeInstanceOf(Invoice::class);
    expect($invoice->contact_id)->toBe($contact->id);
    expect($invoice->status)->toBe(InvoiceStatus::Draft);
});

test('converts a Sent proforma to a sales invoice', function () {
    Sequence::factory()->create(['type' => 'electronic_invoice', 'is_system' => true]);
    $contact = Contact::factory()->create();
    $proforma = ProformaInvoice::factory()->sent()->create(['contact_id' => $contact->id]);

    $action = app(ConvertProformaToInvoice::class);
    $invoice = $action->execute($proforma);

    expect($invoice)->toBeInstanceOf(Invoice::class);
});

test('returns null for a Converted proforma', function () {
    Sequence::factory()->create(['type' => 'electronic_invoice', 'is_system' => true]);
    $proforma = ProformaInvoice::factory()->converted()->create();

    $action = app(ConvertProformaToInvoice::class);
    $result = $action->execute($proforma);

    expect($result)->toBeNull();
});

test('returns null for a Cancelled proforma', function () {
    Sequence::factory()->create(['type' => 'electronic_invoice', 'is_system' => true]);
    $proforma = ProformaInvoice::factory()->cancelled()->create();

    $action = app(ConvertProformaToInvoice::class);
    $result = $action->execute($proforma);

    expect($result)->toBeNull();
});

test('returns null when no electronic_invoice sequence exists', function () {
    $proforma = ProformaInvoice::factory()->create(['status' => ProformaStatus::Draft]);

    $action = app(ConvertProformaToInvoice::class);
    $result = $action->execute($proforma);

    expect($result)->toBeNull();
});

test('copies all lines from proforma to new invoice', function () {
    Sequence::factory()->create(['type' => 'electronic_invoice', 'is_system' => true]);
    $proforma = ProformaInvoice::factory()->create(['status' => ProformaStatus::Draft]);

    InvoiceLine::create([
        'invoice_id' => $proforma->id,
        'description' => 'Line 1',
        'quantity' => 2,
        'unit_price' => 5000,
        'total' => 10000,
        'vat_rate' => VatRate::R22->value,
    ]);

    InvoiceLine::create([
        'invoice_id' => $proforma->id,
        'description' => 'Line 2',
        'quantity' => 1,
        'unit_price' => 3000,
        'total' => 3000,
        'vat_rate' => VatRate::R22->value,
    ]);

    $action = app(ConvertProformaToInvoice::class);
    $invoice = $action->execute($proforma);

    expect($invoice->lines)->toHaveCount(2);
    expect($invoice->lines->pluck('description')->toArray())->toBe(['Line 1', 'Line 2']);
});

test('copies tax options from proforma to invoice', function () {
    Sequence::factory()->create(['type' => 'electronic_invoice', 'is_system' => true]);
    $proforma = ProformaInvoice::factory()->create([
        'status' => ProformaStatus::Draft,
        'withholding_tax_enabled' => true,
        'withholding_tax_percent' => '20.00',
        'stamp_duty_applied' => true,
        'stamp_duty_amount' => 200,
    ]);

    $action = app(ConvertProformaToInvoice::class);
    $invoice = $action->execute($proforma);

    expect($invoice->withholding_tax_enabled)->toBeTrue();
    expect($invoice->withholding_tax_percent)->toBe('20.00');
    expect($invoice->stamp_duty_applied)->toBeTrue();
    expect($invoice->stamp_duty_amount)->toBe(200);
});

test('sets proforma status to Converted after successful conversion', function () {
    Sequence::factory()->create(['type' => 'electronic_invoice', 'is_system' => true]);
    $proforma = ProformaInvoice::factory()->create(['status' => ProformaStatus::Draft]);

    $action = app(ConvertProformaToInvoice::class);
    $action->execute($proforma);

    expect($proforma->fresh()->status)->toBe(ProformaStatus::Converted);
});

test('new invoice gets a sequence number via reserveNextNumber', function () {
    $sequence = Sequence::factory()->create(['type' => 'electronic_invoice', 'is_system' => true, 'pattern' => '{SEQ}']);
    $proforma = ProformaInvoice::factory()->create(['status' => ProformaStatus::Draft]);

    $action = app(ConvertProformaToInvoice::class);
    $invoice = $action->execute($proforma);

    expect($invoice->sequential_number)->toBe(1);
    expect($invoice->number)->toBe('1');
});

test('proforma_id is set on the new invoice', function () {
    Sequence::factory()->create(['type' => 'electronic_invoice', 'is_system' => true]);
    $proforma = ProformaInvoice::factory()->create(['status' => ProformaStatus::Draft]);

    $action = app(ConvertProformaToInvoice::class);
    $invoice = $action->execute($proforma);

    expect($invoice->proforma_id)->toBe($proforma->id);
});
