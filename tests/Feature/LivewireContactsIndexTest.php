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
        ->assertSee('1 contatto')
        ->assertSee(route('contacts.edit', $contact), escape: false)
        ->assertSee('aria-sort="ascending"', escape: false)
        ->assertDontSee('>Azioni<', escape: false)
        ->assertDontSee('>Apri<', escape: false);
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
