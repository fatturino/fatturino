<?php

use App\Models\Contact;
use App\Models\FiscalDocument;
use App\Models\Sequence;
use App\Models\User;
use App\Settings\CompanySettings;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $settings = app(CompanySettings::class);
    $settings->company_name = 'Test Company SRL';
    $settings->company_vat_number = 'IT12345678903';
    $settings->company_tax_code = '12345678903';
    $settings->company_address = 'Via Test 1';
    $settings->company_city = 'Milano';
    $settings->company_postal_code = '20100';
    $settings->company_province = 'MI';
    $settings->company_country = 'IT';
    $settings->company_fiscal_regime = 'RF19';
    $settings->rf19_self_invoices_enabled = false;
    $settings->save();
});

test('rf19 normalizes withholding split and vat rate on sales invoices', function () {
    $user = User::factory()->create();
    $contact = Contact::factory()->create();
    $sequence = Sequence::factory()->create(['type' => 'electronic_invoice']);

    $response = $this->actingAs($user)->post('/sell-invoices', [
        'contact_id' => $contact->id,
        'sequence_id' => $sequence->id,
        'date' => '2026-05-26',
        'document_type' => 'TD01',
        'withholding_tax_enabled' => true,
        'withholding_tax_percent' => '20.00',
        'split_payment' => true,
        'vat_payability' => 'S',
        'lines' => [
            [
                'description' => 'Servizio',
                'quantity' => 1,
                'unit_of_measure' => '',
                'unit_price' => 100,
                'discount_percent' => null,
                'vat_rate' => 'R22',
            ],
        ],
    ]);

    $response->assertRedirect(route('sell-invoices.index'));

    $invoice = FiscalDocument::query()->latest('id')->firstOrFail();
    expect((bool) $invoice->withholding_tax_enabled)->toBeFalse();
    expect((bool) $invoice->split_payment)->toBeFalse();
    expect($invoice->vat_payability)->toBe('I');
    expect($invoice->notes)->toContain('Operazione in franchigia da IVA');
    expect($invoice->notes)->toContain("Compenso non soggetto a ritenuta d'acconto");
    expect($invoice->lines()->firstOrFail()->vat_rate->value)->toBe('N2.2');
});

test('rf19 blocks self invoices routes when override is disabled', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/self-invoices')->assertForbidden();
    $this->actingAs($user)->get('/self-invoices/create')->assertForbidden();
});

test('rf19 hides self invoice import option', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/imports')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('selfInvoiceImportEnabled', false)
        );
});
