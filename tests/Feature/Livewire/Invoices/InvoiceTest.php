<?php

namespace Tests\Feature\Livewire\Invoices;

use App\Enums\VatRate;
use App\Livewire\Invoices\Create;
use App\Livewire\Invoices\Edit;
use App\Livewire\Invoices\Index;
use App\Models\Contact;
use App\Models\Invoice;
use App\Models\Sequence;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class InvoiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_invoices_can_be_listed()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/sell-invoices')
            ->assertSeeLivewire(Index::class)
            ->assertStatus(200);
    }

    public function test_invoice_can_be_created_with_lines_and_totals()
    {
        $user = User::factory()->create();
        $contact = Contact::factory()->create();
        $sequence = Sequence::create(['name' => 'Sales', 'pattern' => 'INV-{SEQ}', 'is_system' => true, 'type' => 'electronic_invoice']);

        Livewire::actingAs($user)
            ->test(Create::class)
            ->set('contact_id', $contact->id)
            ->set('sequence_id', $sequence->id)
            ->set('number', 'INV-0001')
            ->set('lines', [
                [
                    'description' => 'Item 1',
                    'quantity' => 2,
                    'unit_of_measure' => '',
                    'unit_price' => 100, // €100.00
                    'vat_rate' => VatRate::R22->value,
                    'total' => 0
                ]
            ])
            ->call('save')
            ->assertRedirect('/sell-invoices');

        $this->assertDatabaseHas('invoices', [
            'number' => 'INV-0001',
            'contact_id' => $contact->id,
            'total_net' => 20000, // 2 * 100 * 100 cents
            'total_vat' => 4400, // 200 * 0.22 * 100 cents
            'total_gross' => 24400,
        ]);

        $this->assertDatabaseHas('invoice_lines', [
            'description' => 'Item 1',
            'quantity' => 2,
            'unit_price' => 10000, // cents
        ]);
    }

    public function test_invoice_can_be_edited_and_totals_recalculated()
    {
        $user = User::factory()->create();
        $contact = Contact::factory()->create();
        $sequence = Sequence::create(['name' => 'Sales', 'pattern' => 'INV-{SEQ}', 'is_system' => true, 'type' => 'electronic_invoice']);

        $invoice = Invoice::create([
            'number' => 'INV-OLD',
            'date' => now(),
            'contact_id' => $contact->id,
            'sequence_id' => $sequence->id,
            'type' => 'sales',
            'withholding_tax_enabled' => false,
        ]);

        Livewire::actingAs($user)
            ->test(Edit::class, ['invoice' => $invoice])
            ->set('number', 'INV-NEW')
            ->set('lines', [
                [
                    'description' => 'New Item',
                    'quantity' => 1,
                    'unit_of_measure' => '',
                    'unit_price' => 50, // €50.00
                    'vat_rate' => VatRate::R22->value,
                    'total' => 0
                ]
            ])
            ->call('save')
            ->assertRedirect('/sell-invoices');

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'number' => 'INV-NEW',
            'total_net' => 5000, // 50 * 100
            'total_gross' => 6100, // 50 + 11 (22%)
        ]);
    }
}
