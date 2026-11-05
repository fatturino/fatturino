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

it('renders the purchase invoice with the shared line editor', function () {
    $user = User::factory()->create();
    $invoice = PurchaseInvoice::factory()->create(['date' => now()->toDateString()]);
    $invoice->lines()->create(['description' => 'Acquisto', 'quantity' => 1, 'unit_price' => 10000, 'vat_rate' => 'R22', 'total' => 10000]);

    $this->actingAs($user);

    Livewire::test('pages::documents.purchase.form', ['purchaseInvoice' => $invoice])
        ->assertSee('Dati fattura di acquisto')
        ->assertSee('La sequenza d\'importazione non è modificabile.', escape: false)
        ->call('toggleLineDetails', 0)
        ->assertSee('Unità di misura');
});

it('renders SDI-locked purchase invoices as read-only', function () {
    $user = User::factory()->create();
    $invoice = PurchaseInvoice::factory()->create(['sdi_status' => SdiStatus::Delivered, 'date' => now()->toDateString()]);
    $this->actingAs($user);
    Livewire::test('pages::documents.purchase.form', ['purchaseInvoice' => $invoice])
        ->assertSee('Questa fattura non è più modificabile.')
        ->assertDontSee('Aggiorna fattura');
});

it('includes negative discount lines in purchase invoice totals', function () {
    $user = User::factory()->create();
    $invoice = PurchaseInvoice::factory()->create(['date' => now()->toDateString()]);
    $invoice->lines()->create(['description' => 'Servizio', 'quantity' => 1, 'unit_price' => 10000, 'vat_rate' => 'R22', 'total' => 10000]);
    $invoice->lines()->create(['description' => 'Sconto', 'quantity' => 1, 'unit_price' => -5000, 'vat_rate' => 'R22', 'total' => -5000]);

    $this->actingAs($user);

    $component = Livewire::test('pages::documents.purchase.form', ['purchaseInvoice' => $invoice])
        ->assertSet('lines.1.unit_price', '-50.00')
        ->assertSee('-50,00');

    expect($component->get('netTotal'))->toEqual(50.0)
        ->and($component->get('vatTotal'))->toEqual(11.0);
});

it('includes SDI amount-only discount lines with zero quantity in purchase totals', function () {
    $user = User::factory()->create();
    $invoice = PurchaseInvoice::factory()->create(['date' => now()->toDateString()]);
    $invoice->lines()->create(['description' => 'Apple iPhone', 'quantity' => 1, 'unit_price' => 1446, 'vat_rate' => 'R22', 'total' => 1446]);
    $invoice->lines()->create(['description' => 'Sconto Loyalty Discount', 'quantity' => 0, 'unit_price' => -774, 'vat_rate' => 'R22', 'total' => -774]);

    $this->actingAs($user);

    $component = Livewire::test('pages::documents.purchase.form', ['purchaseInvoice' => $invoice])
        ->assertSet('lines.1.unit_price', '-7.74')
        ->assertSee('-7,74');

    expect((float) $component->get('lines.1.quantity'))->toEqual(0.0)
        ->and($component->get('netTotal'))->toEqual(6.72)
        ->and($component->get('vatTotal'))->toEqual(1.48);
});

it('persists a purchase invoice line with a negative unit price', function () {
    $user = User::factory()->create();
    $contact = Contact::factory()->create();
    $sequence = Sequence::factory()->create(['type' => 'purchase']);
    $invoice = PurchaseInvoice::factory()->create([
        'contact_id' => $contact->id,
        'sequence_id' => $sequence->id,
        'date' => now()->toDateString(),
    ]);
    $invoice->lines()->create(['description' => 'Acquisto', 'quantity' => 1, 'unit_price' => 10000, 'vat_rate' => 'R22', 'total' => 10000]);

    $this->actingAs($user);

    Livewire::test('pages::documents.purchase.form', ['purchaseInvoice' => $invoice])
        ->set('lines', [
            validPurchaseInvoiceLine('Servizio'),
            [...validPurchaseInvoiceLine('Sconto'), 'unit_price' => '-25.00'],
        ])
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('purchase-invoices.index'));

    $lines = $invoice->fresh()->lines()->orderBy('id')->get();

    expect($lines)->toHaveCount(2)
        ->and($lines[1]->unit_price)->toBe(-2500)
        ->and($lines[1]->total)->toBe(-2500)
        ->and($invoice->fresh()->total_net)->toBe(7500);
});

it('persists an amount-only discount line with zero quantity', function () {
    $user = User::factory()->create();
    $contact = Contact::factory()->create();
    $sequence = Sequence::factory()->create(['type' => 'purchase']);
    $invoice = PurchaseInvoice::factory()->create([
        'contact_id' => $contact->id,
        'sequence_id' => $sequence->id,
        'date' => now()->toDateString(),
    ]);
    $invoice->lines()->create(['description' => 'Acquisto', 'quantity' => 1, 'unit_price' => 10000, 'vat_rate' => 'R22', 'total' => 10000]);

    $this->actingAs($user);

    Livewire::test('pages::documents.purchase.form', ['purchaseInvoice' => $invoice])
        ->set('lines', [
            validPurchaseInvoiceLine('Servizio'),
            [...validPurchaseInvoiceLine('Sconto Loyalty Discount'), 'quantity' => '0', 'unit_price' => '-7.74'],
        ])
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('purchase-invoices.index'));

    $discount = $invoice->fresh()->lines()->orderBy('id')->get()->last();

    expect($discount->quantity)->toEqual(0)
        ->and($discount->unit_price)->toBe(-774)
        ->and($discount->total)->toBe(-774)
        ->and($invoice->fresh()->total_net)->toBe(9226);
});
