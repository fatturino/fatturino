<?php

namespace Tests\Feature;

use App\Enums\VatRate;
use App\Models\Contact;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Services\InvoiceXmlService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ForeignInvoiceXmlTest extends TestCase
{
    use RefreshDatabase;

    public function test_xml_generation_for_eu_customer()
    {
        // Setup EU customer (Germany)
        $contact = Contact::create([
            'name' => 'Deutsche Firma GmbH',
            'vat_number' => 'DE123456789',
            'address' => 'Hauptstraße 1',
            'city' => 'Berlin',
            'postal_code' => '10115',
            'country' => 'DE',
        ]);

        $invoice = Invoice::create([
            'number' => '1/2023',
            'date' => now(),
            'contact_id' => $contact->id,
            'total_net' => 100,
            'total_vat' => 22,
            'total_gross' => 122,
        ]);

        InvoiceLine::create([
            'invoice_id' => $invoice->id,
            'description' => 'International Service',
            'quantity' => 1,
            'unit_price' => 100,
            'vat_rate' => VatRate::R22->value,
            'total' => 100,
        ]);

        // Generate XML
        $service = app(InvoiceXmlService::class);
        $xml = $service->generate($invoice);

        // Assertions for EU customer
        $this->assertNotEmpty($xml);
        $this->assertStringContainsString('FatturaElettronica', $xml);

        // Should contain XXXXXXX for foreign customer
        $this->assertStringContainsString('XXXXXXX', $xml);

        // Should contain customer name
        $this->assertStringContainsString('Deutsche Firma GmbH', $xml);

        // Should contain cleaned VAT number (without DE prefix)
        $this->assertStringContainsString('123456789', $xml);

        // Should contain country code
        $this->assertStringContainsString('<Nazione>DE</Nazione>', $xml);

        // Should contain 00000 for postal code
        $this->assertStringContainsString('<CAP>00000</CAP>', $xml);

        // Province tag is omitted for foreign addresses by the library
        // This is correct behavior for SDI XML

        // Should NOT contain PEC (not applicable for foreign customers)
        $this->assertStringNotContainsString('<PECDestinatario>', $xml);
    }

    public function test_xml_generation_for_non_eu_customer()
    {
        // Setup non-EU customer (USA)
        $contact = Contact::create([
            'name' => 'American Company LLC',
            'vat_number' => 'US123456789',
            'address' => 'Main Street 1',
            'city' => 'New York',
            'postal_code' => '10001',
            'country' => 'US',
        ]);

        $invoice = Invoice::create([
            'number' => '2/2023',
            'date' => now(),
            'contact_id' => $contact->id,
            'total_net' => 500,
            'total_vat' => 0,
            'total_gross' => 500,
        ]);

        InvoiceLine::create([
            'invoice_id' => $invoice->id,
            'description' => 'Export Service',
            'quantity' => 1,
            'unit_price' => 500,
            'vat_rate' => VatRate::N3_1->value, // Non imponibile - esportazioni
            'total' => 500,
        ]);

        // Generate XML
        $service = app(InvoiceXmlService::class);
        $xml = $service->generate($invoice);

        // Assertions for non-EU customer
        $this->assertNotEmpty($xml);

        // Should contain XXXXXXX for foreign customer
        $this->assertStringContainsString('XXXXXXX', $xml);

        // Should contain 00000000000 as VAT number for non-EU
        $this->assertStringContainsString('00000000000', $xml);

        // Should contain country code
        $this->assertStringContainsString('<Nazione>US</Nazione>', $xml);

        // Should contain 00000 for postal code
        $this->assertStringContainsString('<CAP>00000</CAP>', $xml);

        // Province tag is omitted for foreign addresses by the library

        // Should contain natura IVA for exempt transaction
        $this->assertStringContainsString('N3.1', $xml);
    }

    public function test_xml_generation_for_italian_customer_with_pec()
    {
        // Setup Italian customer with PEC
        $contact = Contact::create([
            'name' => 'Cliente Italiano SRL',
            'vat_number' => 'IT12345678903',
            'tax_code' => '12345678903',
            'address' => 'Via Roma 1',
            'city' => 'Milano',
            'postal_code' => '20121',
            'province' => 'MI',
            'country' => 'IT',
            'pec' => 'cliente@pec.it',
        ]);

        $invoice = Invoice::create([
            'number' => '3/2023',
            'date' => now(),
            'contact_id' => $contact->id,
            'total_net' => 200,
            'total_vat' => 44,
            'total_gross' => 244,
        ]);

        InvoiceLine::create([
            'invoice_id' => $invoice->id,
            'description' => 'Servizio Nazionale',
            'quantity' => 1,
            'unit_price' => 200,
            'vat_rate' => VatRate::R22->value,
            'total' => 200,
        ]);

        // Generate XML
        $service = app(InvoiceXmlService::class);
        $xml = $service->generate($invoice);

        // Assertions for Italian customer with PEC
        $this->assertNotEmpty($xml);

        // Should contain 0000000 as SDI code (when using PEC)
        $this->assertStringContainsString('0000000', $xml);

        // Should contain PEC address
        $this->assertStringContainsString('cliente@pec.it', $xml);

        // Should contain actual postal code (not 00000)
        $this->assertStringContainsString('<CAP>20121</CAP>', $xml);

        // Should contain actual province
        $this->assertStringContainsString('<Provincia>MI</Provincia>', $xml);

        // Should contain tax code
        $this->assertStringContainsString('12345678903', $xml);
    }

    public function test_xml_generation_for_italian_customer_with_sdi_code()
    {
        // Setup Italian customer with SDI code
        $contact = Contact::create([
            'name' => 'Cliente PA',
            'vat_number' => 'IT98765432103',
            'tax_code' => '98765432103',
            'address' => 'Via Governo 1',
            'city' => 'Roma',
            'postal_code' => '00100',
            'province' => 'RM',
            'country' => 'IT',
            'sdi_code' => 'ABCDEFG',
        ]);

        $invoice = Invoice::create([
            'number' => '4/2023',
            'date' => now(),
            'contact_id' => $contact->id,
            'total_net' => 300,
            'total_vat' => 66,
            'total_gross' => 366,
        ]);

        InvoiceLine::create([
            'invoice_id' => $invoice->id,
            'description' => 'Servizio PA',
            'quantity' => 1,
            'unit_price' => 300,
            'vat_rate' => VatRate::R22->value,
            'total' => 300,
        ]);

        // Generate XML
        $service = app(InvoiceXmlService::class);
        $xml = $service->generate($invoice);

        // Assertions for Italian customer with SDI code
        $this->assertNotEmpty($xml);

        // Should contain the specific SDI code
        $this->assertStringContainsString('ABCDEFG', $xml);

        // Should NOT contain PEC
        $this->assertStringNotContainsString('<PECDestinatario>', $xml);

        // Should contain actual postal code
        $this->assertStringContainsString('<CAP>00100</CAP>', $xml);
    }
}
