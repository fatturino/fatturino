<?php

use App\Models\Contact;
use App\Models\User;
use Livewire\Livewire;

it('renders the contacts index as a Livewire page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/contacts')
        ->assertOk()
        ->assertSeeLivewire('pages::contacts.index')
        ->assertSee('Contatti');
});

it('renders a compact, row-linked contacts list without a separate actions column', function () {
    $user = User::factory()->create();
    $contact = Contact::factory()->create(['name' => 'Atlantis Srl']);

    $this->actingAs($user);

    Livewire::test('pages::contacts.index')
        ->assertSee('Riepilogo contatti')
        ->assertSee('Contatti')
        ->assertSee(route('contacts.edit', $contact), escape: false)
        ->assertSee('aria-sort="ascending"', escape: false)
        ->assertDontSee('>Azioni<', escape: false)
        ->assertDontSee('>Apri<', escape: false);
});

it('shows global contact metrics and identifies incomplete contact records', function () {
    $user = User::factory()->create();
    Contact::factory()->create([
        'name' => 'Cliente Completo',
        'is_customer' => true,
        'vat_number' => 'IT12345678901',
        'sdi_code' => 'ABC1234',
    ]);
    Contact::factory()->create([
        'name' => 'Cliente e Fornitore',
        'is_customer' => true,
        'is_supplier' => true,
        'tax_code' => 'RSSMRA80A01H501Z',
        'pec' => 'amministrazione@example.test',
    ]);
    Contact::factory()->create([
        'name' => 'Senza Identificativi',
        'is_customer' => false,
        'is_supplier' => true,
        'vat_number' => null,
        'tax_code' => null,
    ]);
    Contact::factory()->create([
        'name' => 'Cliente Senza Recapito SDI',
        'is_customer' => true,
        'vat_number' => 'IT98765432109',
        'sdi_code' => null,
        'pec' => null,
    ]);

    $this->actingAs($user);

    Livewire::test('pages::contacts.index')
        ->assertSee('id="contact-summary-title"', escape: false)
        ->assertSee('Riepilogo contatti')
        ->assertSee('Clienti')
        ->assertSee('Fornitori')
        ->assertSee('Da completare')
        ->assertSee('id="contact-summary-total" class="mt-1 text-xl font-semibold tabular-nums text-content">4</dd>', escape: false)
        ->assertSee('id="contact-summary-customers" class="mt-1 text-xl font-semibold tabular-nums text-content">3</dd>', escape: false)
        ->assertSee('id="contact-summary-suppliers" class="mt-1 text-xl font-semibold tabular-nums text-content">2</dd>', escape: false)
        ->assertSee('id="contact-summary-incomplete" class="mt-1 text-xl font-semibold tabular-nums text-content">2</dd>', escape: false)
        ->assertSee('Dati fiscali o recapiti mancanti')
        ->set('search', 'Cliente Completo')
        ->assertSee('id="contact-summary-total" class="mt-1 text-xl font-semibold tabular-nums text-content">4</dd>', escape: false)
        ->assertSee('id="contact-summary-customers" class="mt-1 text-xl font-semibold tabular-nums text-content">3</dd>', escape: false)
        ->assertSee('id="contact-summary-suppliers" class="mt-1 text-xl font-semibold tabular-nums text-content">2</dd>', escape: false)
        ->assertSee('id="contact-summary-incomplete" class="mt-1 text-xl font-semibold tabular-nums text-content">2</dd>', escape: false);
});

it('filters contacts by name, VAT number and email', function () {
    $user = User::factory()->create();
    Contact::factory()->create(['name' => 'Cliente Cercato', 'vat_number' => 'IT12345678901', 'email' => 'cliente@example.test']);
    Contact::factory()->create(['name' => 'Altro Contatto', 'vat_number' => 'IT98765432109', 'email' => 'altro@example.test']);

    $this->actingAs($user);

    Livewire::test('pages::contacts.index')
        ->set('search', 'Cliente Cercato')
        ->assertSee('Cliente Cercato')
        ->assertDontSee('Altro Contatto')
        ->set('search', '98765432109')
        ->assertSee('Altro Contatto')
        ->set('search', 'cliente@example.test')
        ->assertSee('Cliente Cercato');
});

it('sorts contacts and resets the search filter', function () {
    $user = User::factory()->create();
    Contact::factory()->create(['name' => 'Alfa']);
    Contact::factory()->create(['name' => 'Zeta']);

    $this->actingAs($user);

    Livewire::test('pages::contacts.index')
        ->set('search', 'Alfa')
        ->call('resetFilters')
        ->assertSet('search', '')
        ->call('sortBy', 'name')
        ->assertSet('direction', 'desc')
        ->assertSeeInOrder(['Zeta', 'Alfa']);
});

it('updates the accessible sort direction and distinguishes an empty search result', function () {
    $user = User::factory()->create();
    Contact::factory()->create(['name' => 'Alfa']);

    $this->actingAs($user);

    Livewire::test('pages::contacts.index')
        ->call('sortBy', 'name')
        ->assertSee('aria-sort="descending"', escape: false)
        ->set('search', 'nessuna corrispondenza')
        ->assertSee('Nessun contatto corrisponde alla ricerca.')
        ->assertSee('Cancella ricerca');
});
