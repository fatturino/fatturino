<?php

use App\Enums\VatRate;
use App\Models\Contact;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\PurchaseInvoice;

test('saving a line triggers calculateTotals on the parent invoice', function () {
    $invoice = Invoice::factory()->create();

    expect($invoice->total_net)->toBe(0);

    InvoiceLine::create([
        'invoice_id' => $invoice->id,
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
    $invoice = Invoice::factory()->create();

    $line = InvoiceLine::create([
        'invoice_id' => $invoice->id,
        'description' => 'Test service',
        'quantity' => 1,
        'unit_price' => 10000,
        'total' => 10000,
        'vat_rate' => VatRate::R22->value,
    ]);

    $invoice->refresh();
    expect($invoice->total_net)->toBe(10000);

    // Use a fresh instance to avoid Eloquent relationship caching issues
    InvoiceLine::find($line->id)->delete();

    $invoice->refresh();
    expect($invoice->total_net)->toBe(0);
    expect($invoice->total_gross)->toBe(0);
});

test('parentInvoice relationship works without global scopes for purchase invoices', function () {
    $purchaseInvoice = PurchaseInvoice::factory()->create();

    $line = InvoiceLine::create([
        'invoice_id' => $purchaseInvoice->id,
        'description' => 'Acquisto',
        'quantity' => 1,
        'unit_price' => 5000,
        'total' => 5000,
        'vat_rate' => VatRate::R22->value,
    ]);

    // parentInvoice uses withoutGlobalScopes, so it can reach purchase invoices
    expect($line->parentInvoice)->not->toBeNull();
    expect($line->parentInvoice->id)->toBe($purchaseInvoice->id);
});

test('discount fields are nullable and cast correctly', function () {
    $invoice = Invoice::factory()->create();

    $line = InvoiceLine::create([
        'invoice_id' => $invoice->id,
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
    $invoice = Invoice::factory()->create();

    $line = InvoiceLine::create([
        'invoice_id' => $invoice->id,
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
