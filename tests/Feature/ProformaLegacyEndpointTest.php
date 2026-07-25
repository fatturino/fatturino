<?php

use App\Enums\ProformaStatus;
use App\Models\Contact;
use App\Models\ProformaInvoice;
use App\Models\Sequence;
use App\Models\User;

function legacyProformaPayload(Contact $contact, Sequence $sequence): array
{
    return [
        'contact_id' => $contact->id,
        'sequence_id' => $sequence->id,
        'date' => now()->toDateString(),
        'due_date' => now()->addDays(30)->toDateString(),
        'notes' => 'Nota legacy',
        'withholding_tax_enabled' => false,
        'fund_enabled' => false,
        'stamp_duty_applied' => false,
        'lines' => [[
            'description' => 'Servizio legacy',
            'quantity' => 1,
            'unit_of_measure' => null,
            'unit_price' => 100,
            'discount_percent' => null,
            'vat_rate' => 'R22',
        ]],
    ];
}

it('creates a proforma through the legacy POST endpoint using the shared action', function () {
    $user = User::factory()->create();
    $contact = Contact::factory()->create();
    $sequence = Sequence::factory()->create(['type' => 'proforma']);

    $this->actingAs($user)
        ->post(route('proforma.store'), legacyProformaPayload($contact, $sequence))
        ->assertRedirect(route('proforma.index'));

    $invoice = ProformaInvoice::query()->sole();
    expect($invoice->sequence_id)->toBe($sequence->id)
        ->and($invoice->total_gross)->toBe(12200)
        ->and($invoice->events()->count())->toBe(1);
});

it('requires a sequence payload only through the legacy POST endpoint', function () {
    $user = User::factory()->create();
    $contact = Contact::factory()->create();
    $sequence = Sequence::factory()->create(['type' => 'proforma']);
    $payload = legacyProformaPayload($contact, $sequence);
    unset($payload['sequence_id']);

    $this->actingAs($user)
        ->from(route('proforma.create'))
        ->post(route('proforma.store'), $payload)
        ->assertRedirect(route('proforma.create'))
        ->assertSessionHasErrors('sequence_id');
});

it('updates through the legacy PUT endpoint without a sequence payload', function () {
    $user = User::factory()->create();
    $contact = Contact::factory()->create();
    $originalSequence = Sequence::factory()->create(['type' => 'proforma']);
    $invoice = ProformaInvoice::factory()->create([
        'contact_id' => $contact->id,
        'sequence_id' => $originalSequence->id,
        'status' => ProformaStatus::Draft,
        'date' => now()->toDateString(),
    ]);
    $invoice->lines()->create([
        'description' => 'Prima versione',
        'quantity' => 1,
        'unit_price' => 10000,
        'vat_rate' => 'R22',
        'total' => 10000,
    ]);
    $payload = legacyProformaPayload($contact, $originalSequence);
    unset($payload['sequence_id']);

    $this->actingAs($user)
        ->put(route('proforma.update', $invoice), $payload)
        ->assertRedirect(route('proforma.index'));

    expect($invoice->fresh()->sequence_id)->toBe($originalSequence->id)
        ->and($invoice->fresh()->lines()->sole()->description)->toBe('Servizio legacy');
});

it('rejects historical proformas through the legacy PUT endpoint', function () {
    $user = User::factory()->create();
    $contact = Contact::factory()->create();
    $sequence = Sequence::factory()->create(['type' => 'proforma']);
    $invoice = ProformaInvoice::factory()->create([
        'contact_id' => $contact->id,
        'sequence_id' => $sequence->id,
        'date' => now()->subYear()->toDateString(),
    ]);

    $this->actingAs($user)
        ->from(route('proforma.index'))
        ->put(route('proforma.update', $invoice), legacyProformaPayload($contact, $sequence))
        ->assertRedirect(route('proforma.index'))
        ->assertSessionHasErrors('invoice');
});
