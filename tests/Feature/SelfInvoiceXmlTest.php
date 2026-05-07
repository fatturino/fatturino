<?php

namespace Tests\Feature;

use App\Enums\VatRate;
use App\Models\Contact;
use App\Models\InvoiceLine;
use App\Models\SelfInvoice;
use App\Services\SelfInvoiceXmlService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SelfInvoiceXmlTest extends TestCase
{
    use RefreshDatabase;

    private function makeInvoiceWithLine(string $documentType = 'TD17', float $vatPercent = 22.0): SelfInvoice
    {
        $contact = Contact::create([
            'name' => 'Foreign Supplier GmbH',
            'vat_number' => 'DE987654321',
            'address' => 'Hauptstrasse 5',
            'city' => 'Berlin',
            'postal_code' => '10115',
            'country' => 'DE',
        ]);

        // Map the percent value to the matching enum case
        $vatRateEnum = match ($vatPercent) {
            22.0 => VatRate::R22,
            10.0 => VatRate::R10,
            5.0 => VatRate::R5,
            4.0 => VatRate::R4,
            default => VatRate::R22,
        };

        $invoice = SelfInvoice::create([
            'number' => 'AF-001',
            'date' => now(),
            'contact_id' => $contact->id,
            'document_type' => $documentType,
            'related_invoice_number' => 'INV/2024/001',
            'related_invoice_date' => now()->subMonth(),
            'total_net' => 10000,
            'total_vat' => (int) round(10000 * $vatPercent / 100),
            'total_gross' => 10000 + (int) round(10000 * $vatPercent / 100),
        ]);

        InvoiceLine::create([
            'invoice_id' => $invoice->id,
            'description' => 'Foreign services',
            'quantity' => 1,
            'unit_price' => 10000,
            'total' => 10000,
            'vat_rate' => $vatRateEnum->value,
        ]);

        return $invoice;
    }

    public function test_generates_valid_xml_for_td17()
    {
        $invoice = $this->makeInvoiceWithLine('TD17');
        $service = app(SelfInvoiceXmlService::class);

        $xml = $service->generate($invoice);

        $this->assertNotEmpty($xml);
        $this->assertStringContainsString('TD17', $xml);
        $this->assertStringContainsString('<?xml', $xml);
    }

    public function test_generates_valid_xml_for_td18()
    {
        $invoice = $this->makeInvoiceWithLine('TD18');
        $service = app(SelfInvoiceXmlService::class);

        $xml = $service->generate($invoice);

        $this->assertStringContainsString('TD18', $xml);
    }

    public function test_generates_valid_xml_for_td19()
    {
        $invoice = $this->makeInvoiceWithLine('TD19');
        $service = app(SelfInvoiceXmlService::class);

        $xml = $service->generate($invoice);

        $this->assertStringContainsString('TD19', $xml);
    }

    public function test_generates_valid_xml_for_td28()
    {
        $invoice = $this->makeInvoiceWithLine('TD28');
        $service = app(SelfInvoiceXmlService::class);

        $xml = $service->generate($invoice);

        $this->assertStringContainsString('TD28', $xml);
    }

    public function test_xml_contains_foreign_supplier_data()
    {
        $invoice = $this->makeInvoiceWithLine('TD17');
        $service = app(SelfInvoiceXmlService::class);

        $xml = $service->generate($invoice);

        $this->assertStringContainsString('Foreign Supplier GmbH', $xml);
    }

    public function test_xml_contains_company_as_customer()
    {
        $invoice = $this->makeInvoiceWithLine('TD17');
        $service = app(SelfInvoiceXmlService::class);

        $xml = $service->generate($invoice);

        // Company VAT (from company settings seeded in TestCase::setUp) should appear
        $this->assertNotEmpty($xml);
        // The company data appears in CessionarioCommittente
        $this->assertStringContainsString('CessionarioCommittente', $xml);
    }

    public function test_xml_contains_related_invoice_data()
    {
        $invoice = $this->makeInvoiceWithLine('TD17');
        $service = app(SelfInvoiceXmlService::class);

        $xml = $service->generate($invoice);

        // DatiFattureCollegate should include the related invoice number
        $this->assertStringContainsString('INV/2024/001', $xml);
    }

    public function test_generate_filename_follows_sdi_format()
    {
        $invoice = $this->makeInvoiceWithLine('TD17');
        $service = app(SelfInvoiceXmlService::class);

        $filename = $service->generateFileName($invoice);

        // Format: <CountryCode><VatNumber>_<XXXXX>.xml
        $this->assertStringEndsWith('.xml', $filename);
        $this->assertStringContainsString('_', $filename);
    }
}
