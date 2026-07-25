<?php

use App\Actions\SaveCreditNote;
use App\Enums\SdiStatus;
use App\Models\Contact;
use App\Models\CreditNote;
use App\Models\Sequence;
use App\Settings\CompanySettings;
use Illuminate\Validation\ValidationException;

function creditNotePayload(Contact $contact, Sequence $sequence): array
{
    return ['contact_id' => $contact->id, 'sequence_id' => $sequence->id, 'date' => now()->toDateString(), 'related_invoice_number' => 'FT-001', 'related_invoice_date' => now()->subDay()->toDateString(), 'notes' => 'Storno parziale', 'lines' => [['description' => 'Storno', 'quantity' => 1, 'unit_of_measure' => null, 'unit_price' => 100, 'vat_rate' => 'R22']]];
}

it('creates a credit note and preserves its credit document semantics', function () {
    $contact = Contact::factory()->create();
    $sequence = Sequence::factory()->create(['type' => 'credit_note']);

    $note = app(SaveCreditNote::class)->create(creditNotePayload($contact, $sequence));

    expect($note->document_type)->toBe('TD04')
        ->and($note->total_gross)->toBe(12200)
        ->and($note->sequence_id)->toBe($sequence->id);
});

it('normalizes RF19 credit note lines and legal notice', function () {
    app(CompanySettings::class)->fill(['company_fiscal_regime' => 'RF19'])->save();
    $contact = Contact::factory()->create();
    $sequence = Sequence::factory()->create(['type' => 'credit_note']);

    $note = app(SaveCreditNote::class)->create(creditNotePayload($contact, $sequence));

    expect($note->lines->sole()->vat_rate->value)->toBe('N2.2')
        ->and($note->notes)->toContain('Operazione in franchigia da IVA');
});

it('preserves the assigned sequence and rejects SDI-locked credit notes on update', function () {
    $contact = Contact::factory()->create();
    $sequence = Sequence::factory()->create(['type' => 'credit_note']);
    $otherSequence = Sequence::factory()->create(['type' => 'credit_note']);
    $note = CreditNote::factory()->create(['contact_id' => $contact->id, 'sequence_id' => $sequence->id, 'date' => now()->toDateString()]);

    app(SaveCreditNote::class)->update($note, creditNotePayload($contact, $otherSequence));
    expect($note->fresh()->sequence_id)->toBe($sequence->id);

    $note->update(['sdi_status' => SdiStatus::Delivered]);
    expect(fn () => app(SaveCreditNote::class)->update($note, creditNotePayload($contact, $sequence)))
        ->toThrow(ValidationException::class);
});
