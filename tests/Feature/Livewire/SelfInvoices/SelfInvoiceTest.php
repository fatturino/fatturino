<?php

namespace Tests\Feature\Livewire\SelfInvoices;

use App\Livewire\SelfInvoices\Create;
use App\Livewire\SelfInvoices\Index;
use App\Models\Contact;
use App\Models\Sequence;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SelfInvoiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_page_renders_for_authenticated_user()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/self-invoices')
            ->assertSeeLivewire(Index::class)
            ->assertStatus(200);
    }

    public function test_create_component_renders()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/self-invoices/create')
            ->assertSeeLivewire(Create::class)
            ->assertStatus(200);
    }

    public function test_create_validates_document_type_is_required()
    {
        $user = User::factory()->create();
        $contact = Contact::factory()->create();
        $sequence = Sequence::factory()->create(['type' => 'self_invoice']);

        Livewire::actingAs($user)
            ->test(Create::class)
            ->set('document_type', '')
            ->set('date', now()->format('Y-m-d'))
            ->set('contact_id', $contact->id)
            ->set('sequence_id', $sequence->id)
            ->set('related_invoice_number', 'INV/001')
            ->set('related_invoice_date', now()->subMonth()->format('Y-m-d'))
            ->call('save')
            ->assertHasErrors(['document_type']);
    }

    public function test_create_validates_document_type_is_valid_td_code()
    {
        $user = User::factory()->create();
        $contact = Contact::factory()->create();
        $sequence = Sequence::factory()->create(['type' => 'self_invoice']);

        Livewire::actingAs($user)
            ->test(Create::class)
            ->set('number', 'AF-001')
            ->set('document_type', 'TD01') // Invalid for self-invoices
            ->set('date', now()->format('Y-m-d'))
            ->set('contact_id', $contact->id)
            ->set('sequence_id', $sequence->id)
            ->set('related_invoice_number', 'INV/001')
            ->set('related_invoice_date', now()->subMonth()->format('Y-m-d'))
            ->call('save')
            ->assertHasErrors(['document_type']);
    }

    public function test_create_validates_related_invoice_number_required()
    {
        $user = User::factory()->create();
        $contact = Contact::factory()->create();
        $sequence = Sequence::factory()->create(['type' => 'self_invoice']);

        Livewire::actingAs($user)
            ->test(Create::class)
            ->set('number', 'AF-001')
            ->set('document_type', 'TD17')
            ->set('date', now()->format('Y-m-d'))
            ->set('contact_id', $contact->id)
            ->set('sequence_id', $sequence->id)
            ->set('related_invoice_number', '')
            ->set('related_invoice_date', now()->subMonth()->format('Y-m-d'))
            ->call('save')
            ->assertHasErrors(['related_invoice_number']);
    }

    public function test_create_redirects_to_index_when_past_fiscal_year_is_selected()
    {
        $user = User::factory()->create();
        session(['fiscal_year' => now()->year - 1]);

        Livewire::actingAs($user)
            ->test(Create::class)
            ->assertRedirect(route('self-invoices.index'));
    }
}
