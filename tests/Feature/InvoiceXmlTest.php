<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Enums\VatRate;
use App\Models\Contact;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Services\InvoiceXmlService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceXmlTest extends TestCase
{
    use RefreshDatabase;

    public function test_xml_generation()
    {
        // Setup data
        $contact = Contact::create([
            'name' => 'Cliente Test',
            'vat_number' => 'IT12345678903',
            'address' => 'Via Test 1',
            'city' => 'Roma',
            'postal_code' => '00100',
            'province' => 'RM',
            'country' => 'IT',
            'sdi_code' => '1111111',
            'pec' => 'test@pec.it',
        ]);

        $invoice = Invoice::create([
            'number' => '1/2023',
            'date' => now(),
            'contact_id' => $contact->id,
            'total_net' => 10000, // 100.00 EUR in cents
            'total_vat' => 2200,  // 22.00 EUR in cents
            'total_gross' => 12200, // 122.00 EUR in cents
        ]);

        InvoiceLine::create([
            'invoice_id' => $invoice->id,
            'description' => 'Servizio Test',
            'quantity' => 1,
            'unit_price' => 10000, // 100.00 EUR in cents
            'vat_rate' => VatRate::R22->value,
            'total' => 10000, // 100.00 EUR in cents
        ]);

        // Call service
        $service = app(InvoiceXmlService::class);
        $xml = $service->generate($invoice);

        // Assert
        $this->assertNotEmpty($xml);
        $this->assertStringContainsString('FatturaElettronica', $xml);
        $this->assertStringContainsString('1/2023', $xml);
        $this->assertStringContainsString('122.00', $xml);
        $this->assertStringContainsString('1111111', $xml);
        $this->assertStringContainsString('Cliente Test', $xml);
    }

    public function test_xml_includes_stamp_duty_when_applied()
    {
        $contact = Contact::create([
            'name' => 'Cliente Bollo',
            'vat_number' => 'IT12345678903',
            'address' => 'Via Test 1',
            'city' => 'Roma',
            'postal_code' => '00100',
            'province' => 'RM',
            'country' => 'IT',
            'sdi_code' => '1111111',
        ]);

        // Invoice with stamp duty applied (€2.00 = 200 cents)
        $invoice = Invoice::create([
            'number' => '2/2023',
            'date' => now(),
            'contact_id' => $contact->id,
            'total_net' => 10000,
            'total_vat' => 2200,
            'total_gross' => 12200,
            'stamp_duty_applied' => true,
            'stamp_duty_amount' => 200,
        ]);

        InvoiceLine::create([
            'invoice_id' => $invoice->id,
            'description' => 'Servizio Test',
            'quantity' => 1,
            'unit_price' => 10000,
            'vat_rate' => VatRate::R22->value,
            'total' => 10000,
        ]);

        $service = app(InvoiceXmlService::class);
        $xml = $service->generate($invoice);

        // DatiBollo node with BolloVirtuale = SI
        $this->assertStringContainsString('BolloVirtuale', $xml);
        $this->assertStringContainsString('SI', $xml);
        $this->assertStringContainsString('ImportoBollo', $xml);
        // Document total includes stamp duty: 122.00 + 2.00 = 124.00
        $this->assertStringContainsString('124.00', $xml);
    }

    public function test_xml_excludes_stamp_duty_when_not_applied()
    {
        $contact = Contact::create([
            'name' => 'Cliente No Bollo',
            'vat_number' => 'IT12345678903',
            'address' => 'Via Test 1',
            'city' => 'Roma',
            'postal_code' => '00100',
            'province' => 'RM',
            'country' => 'IT',
            'sdi_code' => '1111111',
        ]);

        $invoice = Invoice::create([
            'number' => '3/2023',
            'date' => now(),
            'contact_id' => $contact->id,
            'total_net' => 5000,
            'total_vat' => 1100,
            'total_gross' => 6100,
            'stamp_duty_applied' => false,
            'stamp_duty_amount' => 0,
        ]);

        InvoiceLine::create([
            'invoice_id' => $invoice->id,
            'description' => 'Servizio Piccolo',
            'quantity' => 1,
            'unit_price' => 5000,
            'vat_rate' => VatRate::R22->value,
            'total' => 5000,
        ]);

        $service = app(InvoiceXmlService::class);
        $xml = $service->generate($invoice);

        // No DatiBollo node when stamp duty is not applied
        $this->assertStringNotContainsString('DatiBollo', $xml);
        $this->assertStringNotContainsString('BolloVirtuale', $xml);
        // Document total without stamp duty
        $this->assertStringContainsString('61.00', $xml);
    }

    public function test_xml_includes_dati_ritenuta_when_withholding_enabled()
    {
        $contact = Contact::create([
            'name' => 'Cliente Ritenuta',
            'vat_number' => 'IT12345678903',
            'address' => 'Via Test 1',
            'city' => 'Roma',
            'postal_code' => '00100',
            'province' => 'RM',
            'country' => 'IT',
            'sdi_code' => '1111111',
        ]);

        $invoice = Invoice::create([
            'number' => '4/2023',
            'date' => now(),
            'contact_id' => $contact->id,
            'total_net' => 100000,
            'total_vat' => 22000,
            'total_gross' => 122000,
            'withholding_tax_enabled' => true,
            'withholding_tax_percent' => 20,
            'withholding_tax_amount' => 20000, // 200.00 EUR
        ]);

        InvoiceLine::create([
            'invoice_id' => $invoice->id,
            'description' => 'Consulenza',
            'quantity' => 1,
            'unit_price' => 100000,
            'vat_rate' => VatRate::R22->value,
            'total' => 100000,
        ]);

        $service = app(InvoiceXmlService::class);
        $xml = $service->generate($invoice);

        // DatiRitenuta node present with correct values
        $this->assertStringContainsString('DatiRitenuta', $xml);
        $this->assertStringContainsString('TipoRitenuta', $xml);
        $this->assertStringContainsString('RT01', $xml);
        $this->assertStringContainsString('ImportoRitenuta', $xml);
        $this->assertStringContainsString('200.00', $xml); // Withholding amount
        $this->assertStringContainsString('AliquotaRitenuta', $xml);
        $this->assertStringContainsString('20.00', $xml); // Withholding rate
    }

    public function test_xml_includes_dati_cassa_previdenziale_when_fund_enabled()
    {
        $contact = Contact::create([
            'name' => 'Cliente Cassa',
            'vat_number' => 'IT12345678903',
            'address' => 'Via Test 1',
            'city' => 'Roma',
            'postal_code' => '00100',
            'province' => 'RM',
            'country' => 'IT',
            'sdi_code' => '1111111',
        ]);

        // Invoice with fund: net 1000, fund 4% = 40, fund VAT 22% = 8.80
        // total_gross = 1000 + 40 + 220 + 8.80 = 1268.80
        $invoice = Invoice::create([
            'number' => '5/2023',
            'date' => now(),
            'contact_id' => $contact->id,
            'total_net' => 100000,
            'total_vat' => 22880, // Line VAT 22000 + Fund VAT 880
            'total_gross' => 126880,
            'fund_enabled' => true,
            'fund_type' => 'TC22',
            'fund_percent' => 4,
            'fund_amount' => 4000, // 40.00 EUR
            'fund_vat_rate' => VatRate::R22->value,
            'fund_has_deduction' => false,
        ]);

        InvoiceLine::create([
            'invoice_id' => $invoice->id,
            'description' => 'Consulenza',
            'quantity' => 1,
            'unit_price' => 100000,
            'vat_rate' => VatRate::R22->value,
            'total' => 100000,
        ]);

        $service = app(InvoiceXmlService::class);
        $xml = $service->generate($invoice);

        // DatiCassaPrevidenziale node present
        $this->assertStringContainsString('DatiCassaPrevidenziale', $xml);
        $this->assertStringContainsString('TipoCassa', $xml);
        $this->assertStringContainsString('TC22', $xml);
        $this->assertStringContainsString('AlCassa', $xml);
        $this->assertStringContainsString('ImportoContributoCassa', $xml);
        $this->assertStringContainsString('40.00', $xml); // Fund amount
        $this->assertStringContainsString('ImponibileCassa', $xml);
        $this->assertStringContainsString('1000.00', $xml); // Original net as subtotal
        // Document total includes fund: 1268.80
        $this->assertStringContainsString('1268.80', $xml);
    }

    public function test_xml_excludes_fund_when_not_enabled()
    {
        $contact = Contact::create([
            'name' => 'Cliente No Cassa',
            'vat_number' => 'IT12345678903',
            'address' => 'Via Test 1',
            'city' => 'Roma',
            'postal_code' => '00100',
            'province' => 'RM',
            'country' => 'IT',
            'sdi_code' => '1111111',
        ]);

        $invoice = Invoice::create([
            'number' => '6/2023',
            'date' => now(),
            'contact_id' => $contact->id,
            'total_net' => 10000,
            'total_vat' => 2200,
            'total_gross' => 12200,
            'fund_enabled' => false,
        ]);

        InvoiceLine::create([
            'invoice_id' => $invoice->id,
            'description' => 'Servizio',
            'quantity' => 1,
            'unit_price' => 10000,
            'vat_rate' => VatRate::R22->value,
            'total' => 10000,
        ]);

        $service = app(InvoiceXmlService::class);
        $xml = $service->generate($invoice);

        // No DatiCassaPrevidenziale node
        $this->assertStringNotContainsString('DatiCassaPrevidenziale', $xml);
        $this->assertStringNotContainsString('TipoCassa', $xml);
    }

    public function test_payment_amount_subtracts_withholding_tax()
    {
        // net 1000.00, VAT 22% = 220.00, gross = 1220.00
        // withholding 20% on net = 200.00
        // ImportoPagamento must be 1220.00 - 200.00 = 1020.00
        $contact = Contact::create([
            'name' => 'Cliente Ritenuta Pagamento',
            'vat_number' => 'IT12345678903',
            'address' => 'Via Test 1',
            'city' => 'Roma',
            'postal_code' => '00100',
            'province' => 'RM',
            'country' => 'IT',
            'sdi_code' => '1111111',
        ]);

        $invoice = Invoice::create([
            'number' => '7/2023',
            'date' => now(),
            'contact_id' => $contact->id,
            'total_net' => 100000,
            'total_vat' => 22000,
            'total_gross' => 122000,
            'withholding_tax_enabled' => true,
            'withholding_tax_percent' => 20,
            'withholding_tax_amount' => 20000,
            'payment_method' => PaymentMethod::MP05,
        ]);

        InvoiceLine::create([
            'invoice_id' => $invoice->id,
            'description' => 'Consulenza',
            'quantity' => 1,
            'unit_price' => 100000,
            'vat_rate' => VatRate::R22->value,
            'total' => 100000,
        ]);

        $service = app(InvoiceXmlService::class);
        $xml = $service->generate($invoice);

        $this->assertStringContainsString('ImportoPagamento', $xml);
        // Must NOT be the gross total (1220.00)
        $this->assertStringNotContainsString('<ImportoPagamento>1220.00</ImportoPagamento>', $xml);
        // Must be gross minus withholding (1020.00)
        $this->assertStringContainsString('<ImportoPagamento>1020.00</ImportoPagamento>', $xml);
    }

    public function test_payment_amount_includes_stamp_duty()
    {
        // net 1000.00, VAT 22% = 220.00, gross = 1220.00, bollo = 2.00
        // ImportoPagamento must be 1220.00 + 2.00 = 1222.00
        $contact = Contact::create([
            'name' => 'Cliente Bollo Pagamento',
            'vat_number' => 'IT12345678903',
            'address' => 'Via Test 1',
            'city' => 'Roma',
            'postal_code' => '00100',
            'province' => 'RM',
            'country' => 'IT',
            'sdi_code' => '1111111',
        ]);

        $invoice = Invoice::create([
            'number' => '8/2023',
            'date' => now(),
            'contact_id' => $contact->id,
            'total_net' => 100000,
            'total_vat' => 22000,
            'total_gross' => 122000,
            'stamp_duty_applied' => true,
            'stamp_duty_amount' => 200,
            'payment_method' => PaymentMethod::MP05,
        ]);

        InvoiceLine::create([
            'invoice_id' => $invoice->id,
            'description' => 'Servizio Esente',
            'quantity' => 1,
            'unit_price' => 100000,
            'vat_rate' => VatRate::R22->value,
            'total' => 100000,
        ]);

        $service = app(InvoiceXmlService::class);
        $xml = $service->generate($invoice);

        $this->assertStringContainsString('ImportoPagamento', $xml);
        $this->assertStringContainsString('<ImportoPagamento>1222.00</ImportoPagamento>', $xml);
    }
}
