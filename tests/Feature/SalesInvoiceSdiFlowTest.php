<?php

use App\Contracts\SdiProvider;
use App\Enums\InvoiceStatus;
use App\Enums\VatRate;
use App\Models\Contact;
use App\Models\FiscalDocument;
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
    $settings->save();
});

test('validate xml endpoint sets invoice status to xml_validated', function () {
    $user = User::factory()->create();
    $contact = Contact::factory()->create(['country' => 'IT', 'sdi_code' => '1234567']);

    $invoice = FiscalDocument::factory()->create([
        'contact_id' => $contact->id,
        'status' => InvoiceStatus::Draft,
    ]);

    $invoice->lines()->create([
        'description' => 'Servizio',
        'quantity' => 1,
        'unit_price' => 10000,
        'vat_rate' => VatRate::R22->value,
        'total' => 10000,
    ]);
    $invoice->calculateTotals();

    $provider = Mockery::mock(SdiProvider::class);
    $provider->shouldReceive('validateXml')
        ->once()
        ->andReturn(['valid' => true, 'message' => 'ok']);
    app()->instance(SdiProvider::class, $provider);

    $response = $this->actingAs($user)->postJson("/sell-invoices/{$invoice->id}/validate-xml");

    $response->assertOk()->assertJson(['success' => true]);
    expect($invoice->fresh()->status)->toBe(InvoiceStatus::XmlValidated);
});

test('send to sdi endpoint sends only xml validated invoices', function () {
    $user = User::factory()->create();
    $contact = Contact::factory()->create(['country' => 'IT', 'sdi_code' => '1234567']);

    $invoice = FiscalDocument::factory()->create([
        'contact_id' => $contact->id,
        'status' => InvoiceStatus::Draft,
    ]);

    $invoice->lines()->create([
        'description' => 'Servizio',
        'quantity' => 1,
        'unit_price' => 10000,
        'vat_rate' => VatRate::R22->value,
        'total' => 10000,
    ]);
    $invoice->calculateTotals();

    $provider = Mockery::mock(SdiProvider::class);
    $provider->shouldReceive('sendInvoice')->never();
    app()->instance(SdiProvider::class, $provider);

    $response = $this->actingAs($user)->postJson("/sell-invoices/{$invoice->id}/send-sdi");

    $response->assertStatus(422)->assertJson(['success' => false]);
});

afterEach(function () {
    Mockery::close();
});
