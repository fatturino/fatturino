<?php

namespace Tests\Feature\Livewire\CreditNotes;

use App\Enums\VatRate;
use App\Livewire\CreditNotes\Create;
use App\Livewire\CreditNotes\Edit;
use App\Livewire\CreditNotes\Index;
use App\Models\Contact;
use App\Models\CreditNote;
use App\Models\Sequence;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CreditNoteTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_page_renders_for_authenticated_user()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/credit-notes')
            ->assertSeeLivewire(Index::class)
            ->assertStatus(200);
    }

    public function test_create_page_renders_for_authenticated_user()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/credit-notes/create')
            ->assertSeeLivewire(Create::class)
            ->assertStatus(200);
    }

    public function test_edit_page_renders_for_authenticated_user()
    {
        $user = User::factory()->create();
        $creditNote = CreditNote::factory()->create();

        $this->actingAs($user)
            ->get("/credit-notes/{$creditNote->id}/edit")
            ->assertSeeLivewire(Edit::class)
            ->assertStatus(200);
    }

    public function test_create_validates_required_fields()
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(Create::class)
            ->set('number', '')
            ->set('date', '')
            ->set('contact_id', null)
            ->set('sequence_id', null)
            ->call('save')
            ->assertHasErrors(['number', 'date', 'contact_id', 'sequence_id']);
    }

    public function test_create_saves_credit_note_with_correct_type()
    {
        $user = User::factory()->create();
        $contact = Contact::factory()->create();
        $sequence = Sequence::factory()->create(['type' => 'credit_note']);

        Livewire::actingAs($user)
            ->test(Create::class)
            ->set('number', 'NC-001')
            ->set('date', now()->format('Y-m-d'))
            ->set('contact_id', $contact->id)
            ->set('sequence_id', $sequence->id)
            ->set('lines', [[
                'description' => 'Reso prodotto',
                'quantity' => 1,
                'unit_of_measure' => 'pz',
                'unit_price' => 100,
                'vat_rate' => VatRate::R22->value,
                'total' => 100,
            ]])
            ->call('save');

        $this->assertDatabaseHas('invoices', [
            'type' => 'credit_note',
            'document_type' => 'TD04',
            'contact_id' => $contact->id,
        ]);
    }

    public function test_create_stores_related_invoice_reference()
    {
        $user = User::factory()->create();
        $contact = Contact::factory()->create();
        $sequence = Sequence::factory()->create(['type' => 'credit_note']);

        Livewire::actingAs($user)
            ->test(Create::class)
            ->set('number', 'NC-001')
            ->set('date', now()->format('Y-m-d'))
            ->set('contact_id', $contact->id)
            ->set('sequence_id', $sequence->id)
            ->set('related_invoice_number', 'FT-2026-001')
            ->set('related_invoice_date', '2026-01-15')
            ->set('lines', [[
                'description' => 'Reso prodotto',
                'quantity' => 1,
                'unit_of_measure' => 'pz',
                'unit_price' => 100,
                'vat_rate' => VatRate::R22->value,
                'total' => 100,
            ]])
            ->call('save');

        $this->assertDatabaseHas('invoices', [
            'type' => 'credit_note',
            'related_invoice_number' => 'FT-2026-001',
        ]);
    }

    public function test_delete_removes_credit_note()
    {
        $user = User::factory()->create();
        $creditNote = CreditNote::factory()->create();

        Livewire::actingAs($user)
            ->test(Index::class)
            ->call('delete', $creditNote->id);

        $this->assertDatabaseMissing('invoices', ['id' => $creditNote->id]);
    }

    public function test_index_is_readonly_in_past_fiscal_year()
    {
        $user = User::factory()->create();

        session(['fiscal_year' => now()->year - 1]);

        Livewire::actingAs($user)
            ->test(Index::class)
            ->assertSet('isReadOnly', true);
    }
}
