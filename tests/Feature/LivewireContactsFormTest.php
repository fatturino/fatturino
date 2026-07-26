<?php

use App\Models\Contact;
use App\Models\User;
use Livewire\Livewire;

it('renders contact create and edit pages as Livewire components', function () {
    $user = User::factory()->create();
    $contact = Contact::factory()->create();

    $this->actingAs($user)->get(route('contacts.create'))->assertOk()->assertSeeLivewire('pages::contacts.create');
    $this->actingAs($user)->get(route('contacts.edit', $contact))->assertOk()->assertSeeLivewire('pages::contacts.edit');
});

it('creates a contact from the Livewire form', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test('pages::contacts.create')
        ->set('name', 'Cliente Nuovo SRL')
        ->set('country', 'IT')
        ->set('vat_number', 'IT12345678903')
        ->set('email', 'cliente@example.test')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('contacts.index'));

    $this->assertDatabaseHas('contacts', ['name' => 'Cliente Nuovo SRL', 'email' => 'cliente@example.test']);
});

it('updates a contact from the Livewire form and validates Italian VAT numbers', function () {
    $this->actingAs(User::factory()->create());
    $contact = Contact::factory()->create(['name' => 'Nome precedente', 'country' => 'IT', 'tax_code' => null]);

    Livewire::test('pages::contacts.edit', ['contact' => $contact])
        ->set('name', 'Nome aggiornato')
        ->set('vat_number', 'IT12345678903')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('contacts.index'));

    expect($contact->refresh()->name)->toBe('Nome aggiornato');

    Livewire::test('pages::contacts.create')
        ->set('name', 'Cliente non valido')
        ->set('country', 'IT')
        ->set('vat_number', 'IT123')
        ->call('save')
        ->assertHasErrors(['vat_number']);
});
