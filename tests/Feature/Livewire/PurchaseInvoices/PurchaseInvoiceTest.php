<?php

namespace Tests\Feature\Livewire\PurchaseInvoices;

use App\Livewire\PurchaseInvoices\Index;
use App\Models\Contact;
use App\Models\PurchaseInvoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PurchaseInvoiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_page_renders_for_authenticated_user()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/purchase-invoices')
            ->assertSeeLivewire(Index::class)
            ->assertStatus(200);
    }

    public function test_listing_shows_purchase_invoices_for_current_fiscal_year()
    {
        $user = User::factory()->create();
        $contact = Contact::factory()->create();

        PurchaseInvoice::factory()->count(3)->create([
            'contact_id' => $contact->id,
            'date' => now(),
            'fiscal_year' => now()->year,
        ]);

        // Different fiscal year — should not appear in default view (date is also in past year)
        PurchaseInvoice::factory()->create([
            'contact_id' => $contact->id,
            'date' => now()->subYear(),
            'fiscal_year' => now()->year - 1,
        ]);

        Livewire::actingAs($user)
            ->test(Index::class)
            ->assertViewHas('invoices', fn ($invoices) => $invoices->total() === 3);
    }

    public function test_search_filters_by_invoice_number()
    {
        $user = User::factory()->create();
        $contact = Contact::factory()->create();

        PurchaseInvoice::factory()->create(['number' => 'ACQ-MATCH', 'contact_id' => $contact->id]);
        PurchaseInvoice::factory()->create(['number' => 'ACQ-OTHER', 'contact_id' => $contact->id]);

        Livewire::actingAs($user)
            ->test(Index::class)
            ->set('search', 'MATCH')
            ->assertViewHas('invoices', fn ($invoices) => $invoices->total() === 1);
    }

    public function test_is_readonly_when_past_fiscal_year_selected()
    {
        $user = User::factory()->create();
        session(['fiscal_year' => now()->year - 1]);

        Livewire::actingAs($user)
            ->test(Index::class)
            ->assertSet('isReadOnly', true);
    }

    public function test_is_not_readonly_for_current_fiscal_year()
    {
        $user = User::factory()->create();
        session(['fiscal_year' => now()->year]);

        Livewire::actingAs($user)
            ->test(Index::class)
            ->assertSet('isReadOnly', false);
    }
}
