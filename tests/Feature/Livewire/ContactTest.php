<?php

namespace Tests\Feature\Livewire;

use App\Livewire\Contacts\Create;
use App\Livewire\Contacts\Edit;
use App\Livewire\Contacts\Index;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ContactTest extends TestCase
{
    use RefreshDatabase;

    public function test_contacts_can_be_listed()
    {
        $user = User::factory()->create();
        Contact::factory()->count(5)->create();

        $this->actingAs($user)
            ->get('/contacts')
            ->assertSeeLivewire(Index::class)
            ->assertStatus(200);
    }

    public function test_contact_can_be_created()
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(Create::class)
            ->set('name', 'Test Contact')
            ->set('email', 'test@example.com')
            ->set('country', 'IT')
            ->call('save')
            ->assertRedirect('/contacts');

        $this->assertDatabaseHas('contacts', [
            'name' => 'Test Contact',
            'email' => 'test@example.com',
        ]);
    }

    public function test_contact_can_be_edited()
    {
        $user = User::factory()->create();
        $contact = Contact::create(['name' => 'Old Name', 'country' => 'IT']);

        Livewire::actingAs($user)
            ->test(Edit::class, ['contact' => $contact])
            ->set('name', 'New Name')
            ->call('save')
            ->assertRedirect('/contacts');

        $this->assertDatabaseHas('contacts', [
            'id' => $contact->id,
            'name' => 'New Name',
        ]);
    }

    public function test_contact_can_be_deleted()
    {
        $user = User::factory()->create();
        $contact = Contact::create(['name' => 'To Delete', 'country' => 'IT']);

        Livewire::actingAs($user)
            ->test(Index::class)
            ->call('delete', $contact->id);

        $this->assertDatabaseMissing('contacts', [
            'id' => $contact->id,
        ]);
    }
}
