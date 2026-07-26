<?php

use App\Enums\PaymentMethod;
use App\Enums\VatRate;
use App\Models\Contact;
use App\Models\FiscalDocument;
use App\Models\FiscalDocumentLine;
use App\Models\Sequence;
use App\Models\User;
use App\Services\InvoiceXmlService;
use App\Settings\CompanySettings;
use App\Support\FiscalRegimePolicy;

beforeEach(function () {
    $settings = app(CompanySettings::class);
    $settings->company_name = 'Test Company SRL';
    $settings->company_vat_number = '12345678903';
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
    $sequence = Sequence::factory()->create(['type' => 'sales']);

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
        ->assertSeeLivewire('pages::imports.index')
        ->assertDontSee('Autofatture XML');
});

test('rf19 xml charges stamp duty as n2 2 taxable line', function () {
    $contact = Contact::factory()->create([
        'country' => 'IT',
        'sdi_code' => '0000000',
        'pec' => 'cliente@example.test',
    ]);

    $invoice = FiscalDocument::create([
        'number' => '1',
        'date' => '2026-06-19',
        'contact_id' => $contact->id,
        'total_net' => 27000,
        'total_vat' => 0,
        'total_gross' => 27000,
        'stamp_duty_applied' => true,
        'stamp_duty_charged_to_customer' => true,
        'stamp_duty_amount' => 200,
        'payment_method' => PaymentMethod::MP05,
        'notes' => FiscalRegimePolicy::FORFETTARIO_VAT_NOTICE,
    ]);

    FiscalDocumentLine::create([
        'fiscal_document_id' => $invoice->id,
        'description' => 'Servizio',
        'quantity' => 1,
        'unit_price' => 27000,
        'vat_rate' => VatRate::N2_2->value,
        'total' => 27000,
    ]);

    $xml = app(InvoiceXmlService::class)->generate($invoice);

    expect($xml)->toContain('<ImportoTotaleDocumento>272.00</ImportoTotaleDocumento>');
    expect($xml)->toContain('<Descrizione>'.FiscalRegimePolicy::STAMP_DUTY_DESCRIPTION.'</Descrizione>');
    expect($xml)->toContain('<PrezzoTotale>2.00000000</PrezzoTotale>');
    expect($xml)->toContain('<Natura>'.FiscalRegimePolicy::FORFETTARIO_VAT_RATE.'</Natura>');
    expect($xml)->toContain('<ImponibileImporto>272.00</ImponibileImporto>');
    expect($xml)->toContain('<RiferimentoNormativo>'.FiscalRegimePolicy::FORFETTARIO_VAT_NOTICE.'</RiferimentoNormativo>');
    expect($xml)->not->toContain('<Natura>'.FiscalRegimePolicy::STAMP_DUTY_VAT_RATE.'</Natura>');
    expect($xml)->not->toContain('<RiferimentoNormativo>'.FiscalRegimePolicy::STAMP_DUTY_REFERENCE.'</RiferimentoNormativo>');
});

test('rf19 requires stamp duty above threshold but lets the issuer bear its cost', function () {
    $payload = FiscalRegimePolicy::normalizeStampDutyPayload([
        'stamp_duty_applied' => false,
        'stamp_duty_charged_to_customer' => false,
    ], [[
        'quantity' => 1,
        'unit_price' => 77.48,
        'discount_percent' => null,
    ]], 'RF19');

    expect($payload['stamp_duty_applied'])->toBeTrue();
    expect($payload['stamp_duty_charged_to_customer'])->toBeFalse();
});

test('rf19 issuer-paid stamp duty is declared in dati bollo without increasing xml total', function () {
    $contact = Contact::factory()->create(['country' => 'IT', 'sdi_code' => '0000000']);
    $invoice = FiscalDocument::create([
        'number' => '2',
        'date' => '2026-06-19',
        'contact_id' => $contact->id,
        'total_net' => 10000,
        'total_vat' => 0,
        'total_gross' => 10000,
        'stamp_duty_applied' => true,
        'stamp_duty_charged_to_customer' => false,
        'stamp_duty_amount' => 200,
        'payment_method' => PaymentMethod::MP05,
        'notes' => FiscalRegimePolicy::FORFETTARIO_VAT_NOTICE,
    ]);
    FiscalDocumentLine::create([
        'fiscal_document_id' => $invoice->id,
        'description' => 'Servizio',
        'quantity' => 1,
        'unit_price' => 10000,
        'vat_rate' => VatRate::N2_2->value,
        'total' => 10000,
    ]);

    $xml = app(InvoiceXmlService::class)->generate($invoice);

    expect($invoice->net_due)->toBe(10000);
    expect($xml)->toContain('<BolloVirtuale>SI</BolloVirtuale>');
    expect($xml)->toContain('<ImportoBollo>2.00</ImportoBollo>');
    expect($xml)->toContain('<ImportoTotaleDocumento>100.00</ImportoTotaleDocumento>');
    expect($xml)->toContain('<ImportoPagamento>100.00</ImportoPagamento>');
    expect($xml)->not->toContain('<Descrizione>'.FiscalRegimePolicy::STAMP_DUTY_DESCRIPTION.'</Descrizione>');
});
