<?php

use App\Actions\SavePurchaseInvoice;
use App\Enums\SdiStatus;
use App\Models\Contact;
use App\Models\PurchaseInvoice;
use App\Models\Sequence;
use Illuminate\Validation\ValidationException;

function purchaseInvoicePayload(Contact $contact): array
{
    return ['contact_id' => $contact->id, 'number' => 'FOR-UPDATED-001', 'date' => now()->toDateString(), 'due_date' => now()->addDays(30)->toDateString(), 'lines' => [['description' => 'Acquisto aggiornato', 'quantity' => 1, 'unit_of_measure' => null, 'unit_price' => 100, 'vat_rate' => 'R22']]];
}

it('updates a purchase invoice without changing its assigned sequence', function () {
    $contact = Contact::factory()->create();
    $sequence = Sequence::factory()->create(['type' => 'purchase']);
    $invoice = PurchaseInvoice::factory()->create(['contact_id' => $contact->id, 'sequence_id' => $sequence->id, 'date' => now()->toDateString()]);
    app(SavePurchaseInvoice::class)->update($invoice, purchaseInvoicePayload($contact));

    expect($invoice->fresh()->sequence_id)->toBe($sequence->id)
        ->and($invoice->fresh()->number)->toBe('FOR-UPDATED-001')
        ->and($invoice->fresh()->lines()->sole()->description)->toBe('Acquisto aggiornato');
});

it('rejects updates to SDI-locked purchase invoices', function () {
    $contact = Contact::factory()->create();
    $invoice = PurchaseInvoice::factory()->create(['contact_id' => $contact->id, 'sdi_status' => SdiStatus::Delivered, 'date' => now()->toDateString()]);
    expect(fn () => app(SavePurchaseInvoice::class)->update($invoice, purchaseInvoicePayload($contact)))->toThrow(ValidationException::class);
});

it('persists amount-only discount lines with zero quantity and negative unit price', function () {
    $contact = Contact::factory()->create();
    $sequence = Sequence::factory()->create(['type' => 'purchase']);
    $invoice = PurchaseInvoice::factory()->create(['contact_id' => $contact->id, 'sequence_id' => $sequence->id, 'date' => now()->toDateString()]);

    app(SavePurchaseInvoice::class)->update($invoice, [
        ...purchaseInvoicePayload($contact),
        'lines' => [
            ['description' => 'Apple iPhone', 'quantity' => 1, 'unit_of_measure' => null, 'unit_price' => 14.46, 'vat_rate' => 'R22'],
            ['description' => 'Sconto Loyalty Discount', 'quantity' => 0, 'unit_of_measure' => null, 'unit_price' => -7.74, 'vat_rate' => 'R22'],
        ],
    ]);

    $lines = $invoice->fresh()->lines()->orderBy('id')->get();

    expect($lines)->toHaveCount(2)
        ->and((float) $lines[1]->quantity)->toEqual(0.0)
        ->and($lines[1]->unit_price)->toBe(-774)
        ->and($lines[1]->total)->toBe(-774)
        ->and($invoice->fresh()->total_net)->toBe(672);
});
