<?php

use App\Enums\InvoiceStatus;
use App\Enums\ProformaStatus;
use App\Models\ProformaInvoice;
use App\Models\Sequence;
use App\Models\User;

test('authenticated user can convert a proforma and is redirected to the draft invoice', function () {
    $user = User::factory()->create();
    Sequence::factory()->create(['type' => 'sales', 'is_system' => true]);
    $proforma = ProformaInvoice::factory()->create(['status' => ProformaStatus::Draft]);

    $response = $this->actingAs($user)->post(route('proforma.convert', $proforma));

    $invoice = $proforma->fresh()->convertedInvoice;

    expect($invoice)->not->toBeNull()
        ->and($invoice->status)->toBe(InvoiceStatus::Draft);
    $response->assertRedirect(route('sell-invoices.edit', $invoice));
});

test('conversion endpoint rejects an already converted proforma', function () {
    $user = User::factory()->create();
    $proforma = ProformaInvoice::factory()->converted()->create();

    $response = $this->actingAs($user)->from(route('proforma.index'))
        ->post(route('proforma.convert', $proforma));

    $response->assertRedirect(route('proforma.index'));
    $response->assertSessionHasErrors('invoice');
});

test('conversion endpoint requires authentication', function () {
    $proforma = ProformaInvoice::factory()->create();

    $this->post(route('proforma.convert', $proforma))
        ->assertRedirect(route('login'));
});
