<?php

use App\Enums\InvoiceStatus;
use App\Enums\ProformaStatus;
use App\Models\ProformaInvoice;
use App\Models\SalesInvoice;
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

test('authenticated user can link a proforma to an existing invoice of the same customer', function () {
    $user = User::factory()->create();
    $proforma = ProformaInvoice::factory()->create(['status' => ProformaStatus::Sent]);
    $invoice = SalesInvoice::factory()->create([
        'contact_id' => $proforma->contact_id,
        'status' => InvoiceStatus::Sent,
        'proforma_id' => null,
    ]);

    $response = $this->actingAs($user)->post(route('proforma.convert', $proforma), [
        'mode' => 'link',
        'invoice_id' => $invoice->id,
    ]);

    expect($proforma->fresh()->status)->toBe(ProformaStatus::Converted)
        ->and($invoice->fresh()->proforma_id)->toBe($proforma->id)
        ->and($invoice->fresh()->status)->toBe(InvoiceStatus::Sent);
    $response->assertRedirect(route('sell-invoices.edit', $invoice));
});

test('linking rejects an invoice for a different customer', function () {
    $user = User::factory()->create();
    $proforma = ProformaInvoice::factory()->create();
    $invoice = SalesInvoice::factory()->create();

    $response = $this->actingAs($user)->from(route('proforma.index'))->post(route('proforma.convert', $proforma), [
        'mode' => 'link',
        'invoice_id' => $invoice->id,
    ]);

    $response->assertRedirect(route('proforma.index'));
    $response->assertSessionHasErrors('invoice');
    expect($proforma->fresh()->status)->toBe(ProformaStatus::Draft)
        ->and($invoice->fresh()->proforma_id)->toBeNull();
});

test('linking rejects an invoice already linked to another proforma', function () {
    $user = User::factory()->create();
    $proforma = ProformaInvoice::factory()->create();
    $linkedProforma = ProformaInvoice::factory()->create(['contact_id' => $proforma->contact_id]);
    $invoice = SalesInvoice::factory()->create([
        'contact_id' => $proforma->contact_id,
        'proforma_id' => $linkedProforma->id,
    ]);

    $response = $this->actingAs($user)->from(route('proforma.index'))->post(route('proforma.convert', $proforma), [
        'mode' => 'link',
        'invoice_id' => $invoice->id,
    ]);

    $response->assertRedirect(route('proforma.index'));
    $response->assertSessionHasErrors('invoice');
    expect($proforma->fresh()->status)->toBe(ProformaStatus::Draft)
        ->and($invoice->fresh()->proforma_id)->toBe($linkedProforma->id);
});
