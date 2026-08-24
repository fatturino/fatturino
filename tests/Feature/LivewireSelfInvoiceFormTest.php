<?php

use App\Enums\SdiStatus;
use App\Models\Contact;
use App\Models\SelfInvoice;
use App\Models\Sequence;
use App\Models\User;
use Livewire\Livewire;

function validSelfInvoiceLine(string $description = 'Servizio estero'): array
{
    return ['key' => (string) str()->uuid(), 'description' => $description, 'quantity' => '1', 'unit_of_measure' => '', 'unit_price' => '100.00', 'vat_rate' => 'R22', 'details_enabled' => false];
}

it('renders and creates a self invoice through the Livewire form', function () {
    $user = User::factory()->create();
    $contact = Contact::factory()->create();
    $sequence = Sequence::factory()->create(['type' => 'self_invoice']);

    $this->actingAs($user)->get(route('self-invoices.create'))
        ->assertOk()
        ->assertSeeLivewire('pages::documents.self-invoice.form');

    Livewire::test('pages::documents.self-invoice.form')
        ->set('contact_id', $contact->id)
        ->set('lines', [validSelfInvoiceLine()])
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('self-invoices.index'));

    $this->assertDatabaseHas('fiscal_documents', ['type' => 'self_invoice', 'sequence_id' => $sequence->id, 'number' => '1', 'total_gross' => 12200]);
});

it('renders the self invoice as a document editor with linked invoice references', function () {
    $user = User::factory()->create();
    Sequence::factory()->create(['type' => 'self_invoice']);

    $this->actingAs($user);

    Livewire::test('pages::documents.self-invoice.form')
        ->assertSee('Dati autofattura')
        ->assertSee('Tipo documento *')
        ->assertSee('Numero fattura collegata')
        ->assertSee('Data fattura collegata')
        ->assertSee('Descrizione')
        ->assertSee('Quantità')
        ->assertSee('Prezzo')
        ->assertSee('Note')
        ->call('toggleLineDetails', 0)
        ->assertSee('Unità di misura');
});

it('updates an editable self invoice without changing its sequence', function () {
    $user = User::factory()->create();
    $contact = Contact::factory()->create();
    $sequence = Sequence::factory()->create(['type' => 'self_invoice']);
    $invoice = SelfInvoice::factory()->create(['contact_id' => $contact->id, 'sequence_id' => $sequence->id, 'date' => now()->toDateString()]);

    $this->actingAs($user);
    Livewire::test('pages::documents.self-invoice.form', ['selfInvoice' => $invoice])
        ->set('lines', [validSelfInvoiceLine('Versione aggiornata')])
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('self-invoices.index'));

    expect($invoice->fresh()->sequence_id)->toBe($sequence->id)
        ->and($invoice->fresh()->lines()->sole()->description)->toBe('Versione aggiornata');
});

it('renders SDI-locked self invoices as read-only', function () {
    $user = User::factory()->create();
    $invoice = SelfInvoice::factory()->create(['sdi_status' => SdiStatus::Delivered, 'date' => now()->toDateString()]);

    $this->actingAs($user);
    Livewire::test('pages::documents.self-invoice.form', ['selfInvoice' => $invoice])
        ->assertSee('Questa autofattura non è più modificabile.')
        ->assertDontSee('Aggiorna autofattura');
});
