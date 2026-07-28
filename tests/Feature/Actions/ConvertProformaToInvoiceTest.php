<?php

use App\Actions\ConvertProformaToInvoice;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\ProformaStatus;
use App\Enums\VatRate;
use App\Models\Contact;
use App\Models\FiscalDocument;
use App\Models\FiscalDocumentLine;
use App\Models\Payment;
use App\Models\ProformaInvoice;
use App\Models\SalesInvoice;
use App\Models\Sequence;
use App\Services\InvoiceXmlService;
use App\Settings\CompanySettings;
use App\Support\FiscalRegimePolicy;

test('converts a Draft proforma to a sales invoice', function () {
    Sequence::factory()->create(['type' => 'sales', 'is_system' => true]);
    $contact = Contact::factory()->create();
    $proforma = ProformaInvoice::factory()->create([
        'contact_id' => $contact->id,
        'status' => ProformaStatus::Draft,
    ]);

    $action = app(ConvertProformaToInvoice::class);
    $invoice = $action->execute($proforma);

    expect($invoice)->toBeInstanceOf(SalesInvoice::class);
    expect($invoice->contact_id)->toBe($contact->id);
    expect($invoice->status)->toBe(InvoiceStatus::Draft);
});

test('converts a Sent proforma to a sales invoice', function () {
    Sequence::factory()->create(['type' => 'sales', 'is_system' => true]);
    $contact = Contact::factory()->create();
    $proforma = ProformaInvoice::factory()->sent()->create(['contact_id' => $contact->id]);

    $action = app(ConvertProformaToInvoice::class);
    $invoice = $action->execute($proforma);

    expect($invoice)->toBeInstanceOf(FiscalDocument::class);
});

test('returns null for a Converted proforma', function () {
    Sequence::factory()->create(['type' => 'sales', 'is_system' => true]);
    $proforma = ProformaInvoice::factory()->converted()->create();

    $action = app(ConvertProformaToInvoice::class);
    $result = $action->execute($proforma);

    expect($result)->toBeNull();
});

test('returns null for a Cancelled proforma', function () {
    Sequence::factory()->create(['type' => 'sales', 'is_system' => true]);
    $proforma = ProformaInvoice::factory()->cancelled()->create();

    $action = app(ConvertProformaToInvoice::class);
    $result = $action->execute($proforma);

    expect($result)->toBeNull();
});

test('returns null when no sales sequence exists', function () {
    $proforma = ProformaInvoice::factory()->create(['status' => ProformaStatus::Draft]);

    $action = app(ConvertProformaToInvoice::class);
    $result = $action->execute($proforma);

    expect($result)->toBeNull();
});

test('copies all lines from proforma to new invoice', function () {
    Sequence::factory()->create(['type' => 'sales', 'is_system' => true]);
    $proforma = ProformaInvoice::factory()->create(['status' => ProformaStatus::Draft]);

    FiscalDocumentLine::create([
        'fiscal_document_id' => $proforma->id,
        'description' => 'Line 1',
        'quantity' => 2,
        'unit_price' => 5000,
        'total' => 10000,
        'vat_rate' => VatRate::R22->value,
    ]);

    FiscalDocumentLine::create([
        'fiscal_document_id' => $proforma->id,
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

test('copies document details, line discounts, and payments to the new invoice', function () {
    Sequence::factory()->create(['type' => 'sales', 'is_system' => true]);
    $proforma = ProformaInvoice::factory()->create([
        'status' => ProformaStatus::Draft,
        'due_date' => '2026-09-30',
        'payment_method' => 'MP05',
        'payment_terms' => 'TP02',
        'bank_name' => 'Banca Test',
        'bank_iban' => 'IT60X0542811101000000123456',
        'vat_payability' => 'S',
        'split_payment' => true,
    ]);
    $proforma->lines()->create([
        'description' => 'Servizio scontato',
        'quantity' => 2,
        'unit_price' => 10000,
        'discount_percent' => '10.00',
        'discount_amount' => 2000,
        'vat_rate' => VatRate::R22->value,
        'total' => 18000,
    ]);
    Payment::create([
        'fiscal_document_id' => $proforma->id,
        'amount' => 5000,
        'paid_at' => '2026-08-01',
        'payment_method' => 'MP05',
        'reference' => 'TRN-123',
        'bank_name' => 'Banca Test',
        'notes' => 'Acconto',
    ]);

    $invoice = app(ConvertProformaToInvoice::class)->execute($proforma);

    expect($invoice->document_type)->toBe('TD01')
        ->and($invoice->due_date?->toDateString())->toBe('2026-09-30')
        ->and($invoice->payment_method)->toBe('MP05')
        ->and($invoice->payment_terms)->toBe('TP02')
        ->and($invoice->bank_name)->toBe('Banca Test')
        ->and($invoice->bank_iban)->toBe('IT60X0542811101000000123456')
        ->and($invoice->vat_payability)->toBe('S')
        ->and((bool) $invoice->split_payment)->toBeTrue()
        ->and($invoice->lines->sole()->discount_percent)->toBe('10.00')
        ->and($invoice->lines->sole()->discount_amount)->toBe(2000)
        ->and($invoice->payments)->toHaveCount(1)
        ->and($invoice->payments->sole()->only(['amount', 'reference', 'bank_name', 'notes']))->toBe([
            'amount' => 5000,
            'reference' => 'TRN-123',
            'bank_name' => 'Banca Test',
            'notes' => 'Acconto',
        ])
        ->and($invoice->total_paid)->toBe(5000);
});

test('copies tax options from proforma to invoice', function () {
    Sequence::factory()->create(['type' => 'sales', 'is_system' => true]);
    $proforma = ProformaInvoice::factory()->create([
        'status' => ProformaStatus::Draft,
        'withholding_tax_enabled' => true,
        'withholding_tax_percent' => '20.00',
        'stamp_duty_applied' => true,
        'stamp_duty_amount' => 200,
    ]);

    $action = app(ConvertProformaToInvoice::class);
    $invoice = $action->execute($proforma);

    expect((bool) $invoice->withholding_tax_enabled)->toBeTrue();
    expect((float) $invoice->withholding_tax_percent)->toBe(20.0);
    expect((bool) $invoice->stamp_duty_applied)->toBeTrue();
    expect($invoice->stamp_duty_amount)->toBe(200);
});

test('keeps issuer-paid RF19 stamp duty out of the converted invoice XML total', function () {
    $settings = app(CompanySettings::class);
    $settings->company_fiscal_regime = 'RF19';
    $settings->save();

    Sequence::factory()->create(['type' => 'sales', 'is_system' => true]);
    $contact = Contact::factory()->create(['country' => 'IT', 'sdi_code' => '0000000']);
    $proforma = ProformaInvoice::factory()->create([
        'contact_id' => $contact->id,
        'status' => ProformaStatus::Draft,
        'total_net' => 60000,
        'total_vat' => 0,
        'total_gross' => 60000,
        'stamp_duty_applied' => true,
        'stamp_duty_charged_to_customer' => false,
        'stamp_duty_amount' => 200,
        'payment_method' => PaymentMethod::MP05,
        'notes' => FiscalRegimePolicy::FORFETTARIO_VAT_NOTICE,
    ]);
    $proforma->lines()->create([
        'description' => 'Consulenza',
        'quantity' => 1,
        'unit_price' => 60000,
        'vat_rate' => VatRate::N2_2->value,
        'total' => 60000,
    ]);

    $invoice = app(ConvertProformaToInvoice::class)->execute($proforma);
    $xml = app(InvoiceXmlService::class)->generate($invoice->load(['contact', 'lines']));

    expect($invoice)->toBeInstanceOf(SalesInvoice::class)
        ->and($invoice->stamp_duty_charged_to_customer)->toBeFalse()
        ->and($invoice->chargesStampDutyToCustomer())->toBeFalse()
        ->and($invoice->net_due)->toBe(60000)
        ->and($xml)->toContain('<ImportoBollo>2.00</ImportoBollo>')
        ->and($xml)->toContain('<ImportoTotaleDocumento>600.00</ImportoTotaleDocumento>')
        ->and($xml)->toContain('<ImportoPagamento>600.00</ImportoPagamento>')
        ->and($xml)->not->toContain('<Descrizione>'.FiscalRegimePolicy::STAMP_DUTY_DESCRIPTION.'</Descrizione>')
        ->and($xml)->not->toContain('<ImponibileImporto>602.00</ImponibileImporto>');
});

test('sets proforma status to Converted after successful conversion', function () {
    Sequence::factory()->create(['type' => 'sales', 'is_system' => true]);
    $proforma = ProformaInvoice::factory()->create(['status' => ProformaStatus::Draft]);

    $action = app(ConvertProformaToInvoice::class);
    $action->execute($proforma);

    expect($proforma->fresh()->status)->toBe(ProformaStatus::Converted);
});

test('new invoice gets a sequence number via reserveNextNumber', function () {
    $sequence = Sequence::factory()->create(['type' => 'sales', 'is_system' => true, 'pattern' => '{SEQ}']);
    $proforma = ProformaInvoice::factory()->create(['status' => ProformaStatus::Draft]);

    $action = app(ConvertProformaToInvoice::class);
    $invoice = $action->execute($proforma);

    expect($invoice->sequential_number)->toBe(1);
    expect($invoice->number)->toBe('1');
});

test('proforma_id is set on the new invoice', function () {
    Sequence::factory()->create(['type' => 'sales', 'is_system' => true]);
    $proforma = ProformaInvoice::factory()->create(['status' => ProformaStatus::Draft]);

    $action = app(ConvertProformaToInvoice::class);
    $invoice = $action->execute($proforma);

    expect($invoice->proforma_id)->toBe($proforma->id);
});
