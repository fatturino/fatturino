<?php

namespace Tests\Feature\Livewire;

use App\Livewire\FiscalYearSelector;
use App\Models\Contact;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FiscalYearSelectorTest extends TestCase
{
    use RefreshDatabase;

    public function test_mounts_with_current_year_when_no_session()
    {
        $user = User::factory()->create();
        session()->forget('fiscal_year');

        Livewire::actingAs($user)
            ->test(FiscalYearSelector::class)
            ->assertSet('selectedYear', now()->year);
    }

    public function test_mounts_with_year_from_session()
    {
        $user = User::factory()->create();
        session(['fiscal_year' => 2022]);

        Livewire::actingAs($user)
            ->test(FiscalYearSelector::class)
            ->assertSet('selectedYear', 2022);
    }

    public function test_updating_selected_year_saves_to_session()
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(FiscalYearSelector::class)
            ->set('selectedYear', 2023);

        $this->assertEquals(2023, session('fiscal_year'));
    }

    public function test_available_years_always_includes_current_year()
    {
        $user = User::factory()->create();

        $component = Livewire::actingAs($user)
            ->test(FiscalYearSelector::class);

        $years = $component->instance()->availableYears();
        $this->assertContains(now()->year, $years);
    }

    public function test_available_years_includes_years_from_existing_invoices()
    {
        $user = User::factory()->create();
        $contact = Contact::factory()->create();

        // Create an invoice from 3 years ago
        Invoice::create([
            'number' => 'FT-001',
            'date' => now()->subYears(3)->format('Y-01-01'),
            'contact_id' => $contact->id,
        ]);

        $component = Livewire::actingAs($user)
            ->test(FiscalYearSelector::class);

        $years = $component->instance()->availableYears();
        $this->assertContains(now()->year - 3, $years);
    }
}
