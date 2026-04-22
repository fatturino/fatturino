<?php

namespace Tests\Feature;

use App\Enums\VatRate;
use App\Models\Contact;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Services\InvoiceXmlService;
use App\Settings\CompanySettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceXmlEnhancedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure company settings are properly set up for XML generation
        $settings = app(CompanySettings::class);
        $settings->company_name = 'Test Company SRL';
        $settings->company_vat_number = 'IT12345678903';
        $settings->company_tax_code = '12345678903';
        $settings->company_address = 'Via Test 1';
        $settings->company_city = 'Milano';
        $settings->company_postal_code = '20100';
        $settings->company_province = 'MI';
        $settings->company_country = 'IT';
        $settings->company_pec = 'test@pec.it';
        $settings->company_sdi_code = '1234567';
        $settings->company_fiscal_regime = 'RF01';
        $settings->save();
    }

    public function test_xml_generation_for_italian_customer()
    {
        $contact = Contact::create([
            'name' => 'Cliente Italiano SRL',
            'vat_number' => 'IT98765432103',
            'tax_code' => '98765432103',
            'address' => 'Via Cliente 10',
            'city' => 'Roma',
            'postal_code' => '00100',
            'province' => 'RM',
            'country' => 'IT',
            'sdi_code' => '7777777',
            'pec' => 'cliente@pec.it',
        ]);

        $invoice = Invoice::create([
            'number' => 1,
            'date' => now(),
            'contact_id' => $contact->id,
            'total_net' => 10000, // 100.00 EUR in cents
            'total_vat' => 2200, // 22.00 EUR in cents
            'total_gross' => 12200, // 122.00 EUR in cents
        ]);

        InvoiceLine::create([
            'invoice_id' => $invoice->id,
            'description' => 'Servizio di consulenza',
            'quantity' => 1,
            'unit_price' => 10000, // 100.00 EUR in cents
            'vat_rate' => VatRate::R22->value,
            'total' => 10000, // 100.00 EUR in cents
        ]);

        $service = app(InvoiceXmlService::class);
        $xml = $service->generate($invoice);

        // Basic structure checks
        $this->assertNotEmpty($xml);
        $this->assertStringContainsString('FatturaElettronica', $xml);

        // Customer data
        $this->assertStringContainsString('Cliente Italiano SRL', $xml);
        $this->assertStringContainsString('7777777', $xml); // SDI code
        $this->assertStringContainsString('RM', $xml); // Province

        // Invoice data
        $this->assertStringContainsString('122.00', $xml); // Total gross
        $this->assertStringContainsString('Servizio di consulenza', $xml);
    }

    public function test_xml_generation_for_foreign_eu_customer()
    {
        $contact = Contact::create([
            'name' => 'German Company GmbH',
            'vat_number' => 'DE123456789',
            'address' => 'Strasse 1',
            'city' => 'Berlin',
            'postal_code' => '10115',
            'province' => 'Berlin',
            'country' => 'DE',
        ]);

        $invoice = Invoice::create([
            'number' => 2,
            'date' => now(),
            'contact_id' => $contact->id,
            'total_net' => 20000, // 200.00 EUR in cents
            'total_vat' => 0, // 0.00 EUR in cents
            'total_gross' => 20000, // 200.00 EUR in cents
        ]);

        InvoiceLine::create([
            'invoice_id' => $invoice->id,
            'description' => 'International Service',
            'quantity' => 1,
            'unit_price' => 20000, // 200.00 EUR in cents
            'vat_rate' => VatRate::N3_1->value, // Non imponibili - esportazioni
            'total' => 20000, // 200.00 EUR in cents
        ]);

        $service = app(InvoiceXmlService::class);
        $xml = $service->generate($invoice);

        // Foreign customer checks
        $this->assertStringContainsString('German Company GmbH', $xml);
        $this->assertStringContainsString('XXXXXXX', $xml); // Foreign SDI code
        // Province (Provincia) is optional for foreign customers and may not appear in XML
        $this->assertStringContainsString('00000', $xml); // Foreign postal code
        $this->assertStringContainsString('N3.1', $xml); // Natura code
    }

    public function test_xml_generation_for_foreign_non_eu_customer()
    {
        $contact = Contact::create([
            'name' => 'US Company Inc',
            'vat_number' => null, // Non-EU may not have EU VAT
            'address' => '123 Main Street',
            'city' => 'New York',
            'postal_code' => '10001',
            'province' => 'NY',
            'country' => 'US',
        ]);

        $invoice = Invoice::create([
            'number' => 3,
            'date' => now(),
            'contact_id' => $contact->id,
            'total_net' => 50000, // 500.00 EUR in cents
            'total_vat' => 0, // 0.00 EUR in cents
            'total_gross' => 50000, // 500.00 EUR in cents
        ]);

        InvoiceLine::create([
            'invoice_id' => $invoice->id,
            'description' => 'Extra-EU Service',
            'quantity' => 1,
            'unit_price' => 50000, // 500.00 EUR in cents
            'vat_rate' => VatRate::N3_3->value, // Non imponibili - prestazioni extra-UE
            'total' => 50000, // 500.00 EUR in cents
        ]);

        $service = app(InvoiceXmlService::class);
        $xml = $service->generate($invoice);

        $this->assertStringContainsString('US Company Inc', $xml);
        $this->assertStringContainsString('XXXXXXX', $xml); // Foreign SDI code
        $this->assertStringContainsString('N3.3', $xml); // Natura for extra-EU
    }

    public function test_xml_generation_with_multiple_vat_rates()
    {
        $contact = Contact::create([
            'name' => 'Cliente Test',
            'vat_number' => 'IT11111111111',
            'address' => 'Via Test 1',
            'city' => 'Milano',
            'postal_code' => '20100',
            'province' => 'MI',
            'country' => 'IT',
            'sdi_code' => '1234567',
        ]);

        $invoice = Invoice::create([
            'number' => 4,
            'date' => now(),
            'contact_id' => $contact->id,
        ]);

        // Line with 22%
        InvoiceLine::create([
            'invoice_id' => $invoice->id,
            'description' => 'Prodotto 22%',
            'quantity' => 1,
            'unit_price' => 10000, // 100.00 EUR in cents
            'vat_rate' => VatRate::R22->value,
            'total' => 10000, // 100.00 EUR in cents
        ]);

        // Line with 10%
        InvoiceLine::create([
            'invoice_id' => $invoice->id,
            'description' => 'Prodotto 10%',
            'quantity' => 1,
            'unit_price' => 10000, // 100.00 EUR in cents
            'vat_rate' => VatRate::R10->value,
            'total' => 10000, // 100.00 EUR in cents
        ]);

        // Line with 0% (exempt N4)
        InvoiceLine::create([
            'invoice_id' => $invoice->id,
            'description' => 'Prodotto Esente',
            'quantity' => 1,
            'unit_price' => 10000, // 100.00 EUR in cents
            'vat_rate' => VatRate::N4->value,
            'total' => 10000, // 100.00 EUR in cents
        ]);

        $invoice->calculateTotals();

        $service = app(InvoiceXmlService::class);
        $xml = $service->generate($invoice);

        // Check all three VAT rates are present
        $this->assertStringContainsString('22', $xml); // 22% rate
        $this->assertStringContainsString('10', $xml); // 10% rate
        $this->assertStringContainsString('N4', $xml); // Natura for exempt

        // Check line descriptions
        $this->assertStringContainsString('Prodotto 22%', $xml);
        $this->assertStringContainsString('Prodotto 10%', $xml);
        $this->assertStringContainsString('Prodotto Esente', $xml);
    }

    public function test_xml_generation_with_decimal_quantities()
    {
        $contact = Contact::create([
            'name' => 'Cliente Test',
            'vat_number' => 'IT11111111111',
            'address' => 'Via Test 1',
            'city' => 'Milano',
            'postal_code' => '20100',
            'province' => 'MI',
            'country' => 'IT',
            'sdi_code' => '1234567',
        ]);

        $invoice = Invoice::create([
            'number' => 5,
            'date' => now(),
            'contact_id' => $contact->id,
        ]);

        InvoiceLine::create([
            'invoice_id' => $invoice->id,
            'description' => 'Servizio orario',
            'quantity' => 2.5,
            'unit_price' => 5050, // 50.50 EUR in cents
            'vat_rate' => VatRate::R22->value,
            'total' => 12625, // 126.25 EUR in cents // 2.5 * 50.50
        ]);

        $invoice->calculateTotals();

        $service = app(InvoiceXmlService::class);
        $xml = $service->generate($invoice);

        // Check decimal handling
        $this->assertStringContainsString('2.5', $xml); // Quantity
        $this->assertStringContainsString('50.5', $xml); // Unit price
    }

    public function test_xml_contains_required_sdi_elements()
    {
        $contact = Contact::create([
            'name' => 'Cliente Test',
            'vat_number' => 'IT11111111111',
            'address' => 'Via Test 1',
            'city' => 'Milano',
            'postal_code' => '20100',
            'province' => 'MI',
            'country' => 'IT',
            'sdi_code' => '1234567',
        ]);

        $invoice = Invoice::create([
            'number' => 6,
            'date' => now(),
            'contact_id' => $contact->id,
        ]);

        InvoiceLine::create([
            'invoice_id' => $invoice->id,
            'description' => 'Test',
            'quantity' => 1,
            'unit_price' => 10000, // 100.00 EUR in cents
            'vat_rate' => VatRate::R22->value,
            'total' => 10000, // 100.00 EUR in cents
        ]);

        $invoice->calculateTotals();

        $service = app(InvoiceXmlService::class);
        $xml = $service->generate($invoice);

        // Check for required SDI elements
        $this->assertStringContainsString('FatturaElettronicaHeader', $xml);
        $this->assertStringContainsString('FatturaElettronicaBody', $xml);
        $this->assertStringContainsString('DatiTrasmissione', $xml);
        $this->assertStringContainsString('CedentePrestatore', $xml);
        $this->assertStringContainsString('CessionarioCommittente', $xml);
        $this->assertStringContainsString('DatiGenerali', $xml);
        $this->assertStringContainsString('DatiBeniServizi', $xml);

        // Check TD01 document type
        $this->assertStringContainsString('TD01', $xml);

        // Check fiscal regime
        $this->assertStringContainsString('RF01', $xml);
    }

    public function test_xml_generation_with_large_invoice()
    {
        $contact = Contact::create([
            'name' => 'Grande Cliente SRL',
            'vat_number' => 'IT11111111111',
            'address' => 'Via Test 1',
            'city' => 'Milano',
            'postal_code' => '20100',
            'province' => 'MI',
            'country' => 'IT',
            'sdi_code' => '1234567',
        ]);

        $invoice = Invoice::create([
            'number' => 7,
            'date' => now(),
            'contact_id' => $contact->id,
        ]);

        // Create 20 invoice lines
        for ($i = 1; $i <= 20; $i++) {
            InvoiceLine::create([
                'invoice_id' => $invoice->id,
                'description' => "Prodotto/Servizio {$i}",
                'quantity' => 1,
                'unit_price' => 10000, // 100.00 EUR in cents
                'vat_rate' => VatRate::R22->value,
                'total' => 10000, // 100.00 EUR in cents
            ]);
        }

        $invoice->calculateTotals();

        $service = app(InvoiceXmlService::class);
        $xml = $service->generate($invoice);

        // Should contain all 20 lines
        for ($i = 1; $i <= 20; $i++) {
            $this->assertStringContainsString("Prodotto/Servizio {$i}", $xml);
        }

        // Total should be 20 x 100 = 2000 net + 440 VAT = 2440 gross
        $this->assertStringContainsString('2440', $xml);
    }

    public function test_xml_is_valid_structure()
    {
        $contact = Contact::create([
            'name' => 'Cliente Test',
            'vat_number' => 'IT11111111111',
            'address' => 'Via Test 1',
            'city' => 'Milano',
            'postal_code' => '20100',
            'province' => 'MI',
            'country' => 'IT',
            'sdi_code' => '1234567',
        ]);

        $invoice = Invoice::create([
            'number' => 8,
            'date' => now(),
            'contact_id' => $contact->id,
        ]);

        InvoiceLine::create([
            'invoice_id' => $invoice->id,
            'description' => 'Test',
            'quantity' => 1,
            'unit_price' => 10000, // 100.00 EUR in cents
            'vat_rate' => VatRate::R22->value,
            'total' => 10000, // 100.00 EUR in cents
        ]);

        $invoice->calculateTotals();

        $service = app(InvoiceXmlService::class);
        $xml = $service->generate($invoice);

        // Verify XML is valid and can be parsed
        $xmlObject = simplexml_load_string($xml);
        $this->assertNotFalse($xmlObject, 'Generated XML should be valid');
    }
}
