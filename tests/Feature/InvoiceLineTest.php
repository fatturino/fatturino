<?php

use App\Enums\VatRate;
use App\Models\FiscalDocument;
use App\Models\FiscalDocumentLine;
use App\Models\PurchaseInvoice;

test('saving a line triggers calculateTotals on the parent invoice', function () {
    $invoice = FiscalDocument::factory()->create();

    expect($invoice->total_net)->toBe(0);

    FiscalDocumentLine::create([
        'fiscal_document_id' => $invoice->id,
        'description' => 'Test service',
        'quantity' => 1,
        'unit_price' => 20000,
        'total' => 20000,
        'vat_rate' => VatRate::R22->value,
    ]);

    $invoice->refresh();
    expect($invoice->total_net)->toBe(20000);
    expect($invoice->total_gross)->toBe(24400);
});

test('deleting a line triggers calculateTotals on the parent invoice', function () {
    $invoice = FiscalDocument::factory()->create();

    $line = FiscalDocumentLine::create([
        'fiscal_document_id' => $invoice->id,
        'description' => 'Test service',
        'quantity' => 1,
        'unit_price' => 10000,
        'total' => 10000,
        'vat_rate' => VatRate::R22->value,
    ]);

    $invoice->refresh();
    expect($invoice->total_net)->toBe(10000);

    // Use a fresh instance to avoid Eloquent relationship caching issues
    FiscalDocumentLine::find($line->id)->delete();

    $invoice->refresh();
    expect($invoice->total_net)->toBe(0);
    expect($invoice->total_gross)->toBe(0);
});

test('fiscalDocument relationship works without global scopes for purchase invoices', function () {
    $purchaseInvoice = PurchaseInvoice::factory()->create();

    $line = FiscalDocumentLine::create([
        'fiscal_document_id' => $purchaseInvoice->id,
        'description' => 'Acquisto',
        'quantity' => 1,
        'unit_price' => 5000,
        'total' => 5000,
        'vat_rate' => VatRate::R22->value,
    ]);

    expect($line->fiscalDocument)->not->toBeNull();
    expect($line->fiscalDocument->id)->toBe($purchaseInvoice->id);
});

test('discount fields are nullable and cast correctly', function () {
    $invoice = FiscalDocument::factory()->create();

    $line = FiscalDocumentLine::create([
        'fiscal_document_id' => $invoice->id,
        'description' => 'Test',
        'quantity' => 1,
        'unit_price' => 10000,
        'discount_percent' => '10.00',
        'discount_amount' => 1000,
        'total' => 9000,
        'vat_rate' => VatRate::R22->value,
    ]);

    expect($line->fresh()->discount_percent)->toBe('10.00');
    expect($line->fresh()->discount_amount)->toBe(1000);
});

test('line belongs to vatRate', function () {
    $invoice = FiscalDocument::factory()->create();

    $line = FiscalDocumentLine::create([
        'fiscal_document_id' => $invoice->id,
        'description' => 'Test',
        'quantity' => 1,
        'unit_price' => 5000,
        'total' => 5000,
        'vat_rate' => VatRate::R22->value,
    ]);

    // The vat_rate column is cast to the VatRate enum on the model
    expect($line->vat_rate)->toBeInstanceOf(VatRate::class);
    expect($line->vat_rate)->toBe(VatRate::R22);
});
