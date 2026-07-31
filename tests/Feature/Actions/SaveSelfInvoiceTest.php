<?php

use App\Actions\SaveSelfInvoice;
use App\Enums\SdiStatus;
use App\Models\Contact;
use App\Models\SelfInvoice;
use App\Models\Sequence;
use App\Settings\InvoiceSettings;
use Illuminate\Validation\ValidationException;

function selfInvoicePayload(Contact $contact, Sequence $sequence): array
{
    return [
        'contact_id' => $contact->id,
        'date' => now()->toDateString(),
        'due_date' => null,
        'document_type' => 'TD17',
        'related_invoice_number' => 'FOR-001',
        'related_invoice_date' => now()->subDay()->toDateString(),
        'notes' => 'Autofattura',
        'lines' => [[
            'description' => 'Servizio estero',
            'quantity' => 1,
            'unit_of_measure' => null,
            'unit_price' => 100,
            'vat_rate' => 'R22',
        ]],
    ];
}

it('creates a self invoice with the configured default sequence and issue-date payment', function () {
    $contact = Contact::factory()->create();
    $sequence = Sequence::factory()->create(['type' => 'self_invoice']);
    $settings = app(InvoiceSettings::class);
    $settings->default_sequence_self_invoice = $sequence->id;
    $settings->save();

    $invoice = app(SaveSelfInvoice::class)->create(selfInvoicePayload($contact, $sequence));

    expect($invoice->sequence_id)->toBe($sequence->id)
        ->and($invoice->number)->toBe('1')
        ->and($invoice->total_gross)->toBe(12200)
        ->and($invoice->payment_status->value)->toBe('paid')
        ->and($invoice->payments)->toHaveCount(1)
        ->and($invoice->payments->sole()->paid_at->toDateString())->toBe(now()->toDateString());
});

it('preserves the assigned sequence when updating a self invoice', function () {
    $contact = Contact::factory()->create();
    $originalSequence = Sequence::factory()->create(['type' => 'self_invoice']);
    $requestedSequence = Sequence::factory()->create(['type' => 'self_invoice']);
    $invoice = SelfInvoice::factory()->create(['contact_id' => $contact->id, 'sequence_id' => $originalSequence->id, 'date' => now()->toDateString()]);

    $payload = selfInvoicePayload($contact, $requestedSequence);
    app(SaveSelfInvoice::class)->update($invoice, $payload);

    expect($invoice->fresh()->sequence_id)->toBe($originalSequence->id)
        ->and($invoice->fresh()->lines()->sole()->description)->toBe('Servizio estero');
});

it('rejects updates to self invoices locked by SDI', function () {
    $contact = Contact::factory()->create();
    $sequence = Sequence::factory()->create(['type' => 'self_invoice']);
    $invoice = SelfInvoice::factory()->create(['contact_id' => $contact->id, 'sequence_id' => $sequence->id, 'sdi_status' => SdiStatus::Delivered, 'date' => now()->toDateString()]);

    expect(fn () => app(SaveSelfInvoice::class)->update($invoice, selfInvoicePayload($contact, $sequence)))
        ->toThrow(ValidationException::class);
});
