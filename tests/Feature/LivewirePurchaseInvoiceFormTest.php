<?php

use App\Enums\SdiStatus;
use App\Models\Contact;
use App\Models\PurchaseInvoice;
use App\Models\Sequence;
use App\Models\User;
use Livewire\Livewire;

function validPurchaseInvoiceLine(string $description = 'Acquisto'): array
{
    return ['key' => (string) str()->uuid(), 'description' => $description, 'quantity' => '1', 'unit_of_measure' => 'pz', 'unit_price' => '100.00', 'vat_rate' => 'R22'];
}

it('renders and updates a purchase invoice through the Livewire form', function () {
    $user = User::factory()->create();
    $contact = Contact::factory()->create();
    $sequence = Sequence::factory()->create(['type' => 'purchase']);
    $invoice = PurchaseInvoice::factory()->create(['contact_id' => $contact->id, 'sequence_id' => $sequence->id, 'date' => now()->toDateString()]);
    $this->actingAs($user)->get(route('purchase-invoices.edit', $invoice))->assertOk()->assertSeeLivewire('pages::documents.purchase.form');

    Livewire::test('pages::documents.purchase.form', ['purchaseInvoice' => $invoice])
        ->set('lines', [validPurchaseInvoiceLine('Acquisto aggiornato')])
        ->call('save')->assertHasNoErrors()->assertRedirect(route('purchase-invoices.index'));

    expect($invoice->fresh()->sequence_id)->toBe($sequence->id)
        ->and($invoice->fresh()->lines()->sole()->unit_of_measure)->toBe('pz');
});

it('renders SDI-locked purchase invoices as read-only', function () {
    $user = User::factory()->create();
    $invoice = PurchaseInvoice::factory()->create(['sdi_status' => SdiStatus::Delivered, 'date' => now()->toDateString()]);
    $this->actingAs($user);
    Livewire::test('pages::documents.purchase.form', ['purchaseInvoice' => $invoice])
        ->assertSee('Questa fattura non è più modificabile.')
        ->assertDontSee('Aggiorna fattura');
});
