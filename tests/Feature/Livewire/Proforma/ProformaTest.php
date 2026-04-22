<?php

namespace Tests\Feature\Livewire\Proforma;

use App\Livewire\Proforma\Create;
use App\Livewire\Proforma\Index;
use App\Models\Contact;
use App\Models\ProformaInvoice;
use App\Models\Sequence;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProformaTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_page_renders_for_authenticated_user()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/proforma')
            ->assertSeeLivewire(Index::class)
            ->assertStatus(200);
    }

    public function test_create_component_renders()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/proforma/create')
            ->assertSeeLivewire(Create::class)
            ->assertStatus(200);
    }

    public function test_create_validates_required_fields()
    {
        $user = User::factory()->create();

        // date is pre-filled by mount(), so only number/contact_id/sequence_id are required
        Livewire::actingAs($user)
            ->test(Create::class)
            ->call('save')
            ->assertHasErrors(['number', 'contact_id', 'sequence_id']);
    }

    public function test_create_saves_proforma_with_withholding_tax()
    {
        $user = User::factory()->create();
        $contact = Contact::factory()->create();
        $sequence = Sequence::factory()->create(['type' => 'proforma']);

        Livewire::actingAs($user)
            ->test(Create::class)
            ->set('date', now()->format('Y-m-d'))
            ->set('contact_id', $contact->id)
            ->set('sequence_id', $sequence->id)
            ->set('withholding_tax_enabled', true)
            ->set('withholding_tax_percent', '20.00')
            ->call('save')
            ->assertHasNoErrors();

        // save() uses reserveNextNumber() from the sequence, so number is sequence-generated
        $this->assertDatabaseHas('invoices', [
            'type' => 'proforma',
            'withholding_tax_enabled' => true,
        ]);
    }

    public function test_create_redirects_to_index_when_past_fiscal_year_is_selected()
    {
        $user = User::factory()->create();
        session(['fiscal_year' => now()->year - 1]);

        Livewire::actingAs($user)
            ->test(Create::class)
            ->assertRedirect(route('proforma.index'));
    }

    public function test_listing_shows_proforma_invoices_for_current_fiscal_year()
    {
        $user = User::factory()->create();
        $contact = Contact::factory()->create();

        ProformaInvoice::factory()->count(2)->create([
            'contact_id' => $contact->id,
            'fiscal_year' => now()->year,
        ]);

        Livewire::actingAs($user)
            ->test(Index::class)
            ->assertViewHas('proformas', fn ($proformas) => $proformas->total() === 2);
    }
}
