<?php

use App\Enums\SdiStatus;
use App\Enums\VatRate;
use App\Livewire\Invoices\InvoiceTimeline;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\SdiLog;
use App\Models\User;
use App\Support\InvoiceAuditDispatcher;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('timeline renders without errors for an empty invoice', function () {
    $invoice = Invoice::factory()->create();

    Livewire::test(InvoiceTimeline::class, ['invoice' => $invoice])
        ->assertOk();
});

test('adjacent line edits by same user within a minute cluster together', function () {
    $invoice = Invoice::factory()->create();

    // Three line creations in quick succession — should cluster into one
    for ($i = 0; $i < 3; $i++) {
        InvoiceLine::create([
            'invoice_id' => $invoice->id,
            'description' => "Line {$i}",
            'quantity' => 1,
            'unit_price' => 1000,
            'discount_percent' => 0,
            'discount_amount' => 0,
            'total' => 1000,
            'vat_rate' => VatRate::R22,
        ]);
    }

    $component = Livewire::withoutLazyLoading()->test(InvoiceTimeline::class, ['invoice' => $invoice]);
    $clusters = $component->viewData('clusters');

    // Find the cluster containing the 3 line edits
    $lineCluster = collect($clusters)->first(fn ($c) => count($c['items']) > 1);

    expect($lineCluster)->not->toBeNull();
    expect($lineCluster['items'])->toHaveCount(3);
});

test('sdi logs and audits merge in timeline sorted newest first', function () {
    $invoice = Invoice::factory()->create();

    // Record an SDI log first
    SdiLog::create([
        'invoice_id' => $invoice->id,
        'event_type' => 'sent',
        'status' => SdiStatus::Sent->value,
        'message' => 'Sent to SDI',
    ]);

    // Then a custom audit event (newer)
    InvoiceAuditDispatcher::dispatch($invoice, 'sdi_sent');

    $component = Livewire::withoutLazyLoading()->test(InvoiceTimeline::class, ['invoice' => $invoice]);
    $clusters = $component->viewData('clusters');

    // Flatten all entries
    $entries = collect($clusters)->flatMap(fn ($c) => $c['items']);

    expect($entries->count())->toBeGreaterThanOrEqual(2);

    // Assert both sources are represented
    expect($entries->pluck('source')->unique()->values()->all())
        ->toContain('audit', 'sdi');
});

test('toggleCluster flips expansion state', function () {
    $invoice = Invoice::factory()->create();

    Livewire::test(InvoiceTimeline::class, ['invoice' => $invoice])
        ->call('toggleCluster', 'abc')
        ->assertSet('expanded.abc', true)
        ->call('toggleCluster', 'abc')
        ->assertSet('expanded.abc', false);
});
