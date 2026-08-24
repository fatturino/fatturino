<?php

use App\Enums\SdiStatus;
use App\Models\Contact;
use App\Models\CreditNote;
use App\Models\Sequence;
use App\Models\User;
use Livewire\Livewire;

function validCreditNoteLine(string $description = 'Storno'): array
{
    return ['key' => (string) str()->uuid(), 'description' => $description, 'quantity' => '1', 'unit_of_measure' => '', 'unit_price' => '100.00', 'vat_rate' => 'R22', 'details_enabled' => false];
}

it('renders and creates a credit note through the Livewire form', function () {
    $user = User::factory()->create();
    $contact = Contact::factory()->create();
    $sequence = Sequence::factory()->create(['type' => 'credit_note']);
    $this->actingAs($user)->get(route('credit-notes.create'))->assertOk()->assertSeeLivewire('pages::documents.credit-note.form');

    Livewire::test('pages::documents.credit-note.form')
        ->set('contact_id', $contact->id)->set('lines', [validCreditNoteLine()])
        ->call('save')->assertHasNoErrors()->assertRedirect(route('credit-notes.index'));

    $this->assertDatabaseHas('fiscal_documents', ['type' => 'credit_note', 'sequence_id' => $sequence->id, 'document_type' => 'TD04', 'total_gross' => 12200]);
});

it('renders the credit note as a document editor with the original invoice references', function () {
    $user = User::factory()->create();
    Sequence::factory()->create(['type' => 'credit_note']);

    $this->actingAs($user);

    Livewire::test('pages::documents.credit-note.form')
        ->assertSee('Dati nota di credito')
        ->assertSee('Numero fattura originaria')
        ->assertSee('Data fattura originaria')
        ->assertSee('Descrizione')
        ->assertSee('Quantità')
        ->assertSee('Prezzo')
        ->assertSee('Note')
        ->call('toggleLineDetails', 0)
        ->assertSee('Unità di misura');
});

it('updates an editable credit note without changing its sequence', function () {
    $user = User::factory()->create();
    $contact = Contact::factory()->create();
    $sequence = Sequence::factory()->create(['type' => 'credit_note']);
    $note = CreditNote::factory()->create(['contact_id' => $contact->id, 'sequence_id' => $sequence->id, 'date' => now()->toDateString()]);
    $this->actingAs($user);

    Livewire::test('pages::documents.credit-note.form', ['creditNote' => $note])
        ->set('lines', [validCreditNoteLine('Storno aggiornato')])
        ->call('save')->assertHasNoErrors()->assertRedirect(route('credit-notes.index'));

    expect($note->fresh()->sequence_id)->toBe($sequence->id)
        ->and($note->fresh()->lines()->sole()->description)->toBe('Storno aggiornato');
});

it('renders SDI-locked credit notes as read-only', function () {
    $user = User::factory()->create();
    $note = CreditNote::factory()->create(['sdi_status' => SdiStatus::Delivered, 'date' => now()->toDateString()]);
    $this->actingAs($user);

    Livewire::test('pages::documents.credit-note.form', ['creditNote' => $note])
        ->assertSee('Questa nota di credito non è più modificabile.')
        ->assertDontSee('Aggiorna nota di credito');
});
