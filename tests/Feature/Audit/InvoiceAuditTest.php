<?php

use App\Enums\VatRate;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('creating an invoice records a created audit', function () {
    $invoice = Invoice::factory()->create();

    expect($invoice->audits)->toHaveCount(1);
    expect($invoice->audits->first()->event)->toBe('created');
    expect($invoice->audits->first()->user_id)->toBe($this->user->id);
});

test('updating a tracked field records an updated audit', function () {
    $invoice = Invoice::factory()->create(['number' => 'FT-001']);
    $invoice->refresh();

    $invoice->update(['number' => 'FT-002']);

    $updateAudits = $invoice->audits()->where('event', 'updated')->get();
    expect($updateAudits)->toHaveCount(1);
    expect($updateAudits->first()->old_values['number'])->toBe('FT-001');
    expect($updateAudits->first()->new_values['number'])->toBe('FT-002');
});

test('updating only recalculated totals does not create an audit', function () {
    $invoice = Invoice::factory()->create();
    $invoice->refresh();

    $baseline = $invoice->audits()->count();

    $invoice->update([
        'total_net' => 10000,
        'total_vat' => 2200,
        'total_gross' => 12200,
    ]);

    expect($invoice->audits()->count())->toBe($baseline);
});

test('modifying a line audits the line but not the parent invoice totals', function () {
    $invoice = Invoice::factory()->create();
    $invoice->refresh();
    $invoiceAuditsBaseline = $invoice->audits()->count();

    $line = InvoiceLine::create([
        'invoice_id' => $invoice->id,
        'description' => 'Service',
        'quantity' => 1,
        'unit_price' => 10000,
        'discount_percent' => 0,
        'discount_amount' => 0,
        'total' => 10000,
        'vat_rate' => VatRate::R22,
    ]);

    expect($line->audits()->count())->toBe(1);
    expect($line->audits->first()->event)->toBe('created');

    // Parent Invoice has no additional audits from the totals recalculation
    expect($invoice->fresh()->audits()->count())->toBe($invoiceAuditsBaseline);
});
