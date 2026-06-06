<?php

use App\Contracts\SdiProvider;
use App\Enums\InvoiceStatus;
use App\Enums\VatRate;
use App\Models\Contact;
use App\Models\CreditNote;
use App\Models\SelfInvoice;
use App\Models\User;
use App\Settings\CompanySettings;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

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
    $settings->company_fiscal_regime = 'RF01';
    $settings->company_sdi_code = '1234567';
    $settings->save();
});

test('self invoice validate xml sets status to xml_validated', function () {
    $user = User::factory()->create();
    $contact = Contact::factory()->create(['country' => 'DE', 'vat_number' => 'DE123456789']);

    $invoice = SelfInvoice::factory()->create([
        'contact_id' => $contact->id,
        'status' => InvoiceStatus::Draft,
        'document_type' => 'TD17',
    ]);

    $invoice->lines()->create([
        'description' => 'Servizio estero',
        'quantity' => 1,
        'unit_price' => 10000,
        'vat_rate' => VatRate::R22->value,
        'total' => 10000,
    ]);
    $invoice->calculateTotals();

    $provider = Mockery::mock(SdiProvider::class);
    $provider->shouldReceive('validateXml')->once()->andReturn(['valid' => true]);
    app()->instance(SdiProvider::class, $provider);

    $response = $this->actingAs($user)->postJson("/self-invoices/{$invoice->id}/validate-xml");

    $response->assertOk()->assertJson([
        'success' => true,
        'document' => [
            'status' => InvoiceStatus::XmlValidated->value,
            'sdi_status' => null,
            'is_sdi_editable' => true,
        ],
    ]);
    expect($invoice->fresh()->status)->toBe(InvoiceStatus::XmlValidated);
});

test('credit note validate xml sets status to xml_validated', function () {
    $user = User::factory()->create();
    $contact = Contact::factory()->create(['country' => 'IT', 'sdi_code' => '1234567']);

    $creditNote = CreditNote::factory()->create([
        'contact_id' => $contact->id,
        'status' => InvoiceStatus::Draft,
    ]);

    $creditNote->lines()->create([
        'description' => 'Rettifica',
        'quantity' => 1,
        'unit_price' => 10000,
        'vat_rate' => VatRate::R22->value,
        'total' => 10000,
    ]);
    $creditNote->calculateTotals();

    $provider = Mockery::mock(SdiProvider::class);
    $provider->shouldReceive('validateXml')->once()->andReturn(['valid' => true]);
    app()->instance(SdiProvider::class, $provider);

    $response = $this->actingAs($user)->postJson("/credit-notes/{$creditNote->id}/validate-xml");

    $response->assertOk()->assertJson([
        'success' => true,
        'document' => [
            'status' => InvoiceStatus::XmlValidated->value,
            'sdi_status' => null,
            'is_sdi_editable' => true,
        ],
    ]);
    expect($creditNote->fresh()->status)->toBe(InvoiceStatus::XmlValidated);
});

afterEach(function () {
    Mockery::close();
});
