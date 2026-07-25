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
