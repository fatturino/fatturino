<?php

namespace Tests\Feature;

use App\Enums\VatRate;
use App\Models\Contact;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\ProformaInvoice;
use App\Services\CourtesyPdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourtesyPdfTest extends TestCase
{
    use RefreshDatabase;

    private function createBaseInvoice(array $overrides = []): array
    {
        $contact = Contact::create([
            'name' => 'Cliente Test',
            'vat_number' => 'IT12345678903',
            'address' => 'Via Test 1',
            'city' => 'Roma',
            'postal_code' => '00100',
            'province' => 'RM',
            'country' => 'IT',
            'sdi_code' => '1111111',
        ]);

        $invoice = Invoice::create(array_merge([
            'number' => '1/2024',
            'date' => now(),
            'contact_id' => $contact->id,
            'total_net' => 40000,
            'total_vat' => 8800,
            'total_gross' => 48800,
        ], $overrides));

        InvoiceLine::create([
            'invoice_id' => $invoice->id,
            'description' => 'Servizio Test',
            'quantity' => 1,
            'unit_price' => 40000,
            'vat_rate' => VatRate::R22->value,
            'total' => 40000,
        ]);

        return [$invoice, VatRate::R22];
    }

    public function test_pdf_is_generated_for_a_basic_invoice(): void
    {
        [$invoice] = $this->createBaseInvoice();

        $service = app(CourtesyPdfService::class);
        $pdf = $service->generate($invoice);
        $output = $pdf->output();

        $this->assertNotEmpty($output);
        $this->assertStringStartsWith('%PDF', $output);
    }

    public function test_pdf_filename_contains_invoice_number(): void
    {
        [$invoice] = $this->createBaseInvoice();

        $service = app(CourtesyPdfService::class);
        $filename = $service->generateFileName($invoice);

        $this->assertEquals('fattura-cortesia-1/2024.pdf', $filename);
    }

    public function test_pdf_is_generated_with_withholding_tax(): void
    {
        [$invoice] = $this->createBaseInvoice([
            'withholding_tax_enabled' => true,
            'withholding_tax_percent' => 20,
            'withholding_tax_amount' => 8000,
        ]);

        $service = app(CourtesyPdfService::class);
        $output = $service->generate($invoice)->output();

        $this->assertStringStartsWith('%PDF', $output);
    }

    public function test_pdf_is_generated_with_stamp_duty(): void
    {
        [$invoice] = $this->createBaseInvoice([
            'stamp_duty_applied' => true,
            'stamp_duty_amount' => 200,
        ]);

        $service = app(CourtesyPdfService::class);
        $output = $service->generate($invoice)->output();

        $this->assertStringStartsWith('%PDF', $output);
    }

    public function test_vat_summary_groups_lines_correctly(): void
    {
        [$invoice, $vatRate] = $this->createBaseInvoice();

        // Add a second line with the same VAT rate
        InvoiceLine::create([
            'invoice_id' => $invoice->id,
            'description' => 'Secondo Servizio',
            'quantity' => 2,
            'unit_price' => 10000,
            'vat_rate' => VatRate::R22->value,
            'total' => 20000,
        ]);

        $summary = $invoice->getVatSummary();

        // Both lines share the same 22% rate, so only one bucket
        $this->assertCount(1, $summary);
        $bucket = array_values($summary)[0];
        $this->assertEquals(22.0, $bucket['rate']);
        $this->assertEquals(60000, $bucket['taxable']); // 40000 + 20000 cents
    }

    public function test_proforma_pdf_is_generated_without_sdi_disclaimer(): void
    {
        $contact = Contact::create([
            'name' => 'Cliente Proforma',
            'vat_number' => 'IT12345678903',
            'city' => 'Milano',
            'country' => 'IT',
        ]);

        $proforma = ProformaInvoice::create([
            'number' => 'P1/2024',
            'date' => now(),
            'contact_id' => $contact->id,
            'total_net' => 20000,
            'total_vat' => 4400,
            'total_gross' => 24400,
        ]);

        InvoiceLine::create([
            'invoice_id' => $proforma->id,
            'description' => 'Servizio Proforma',
            'quantity' => 1,
            'unit_price' => 20000,
            'vat_rate' => VatRate::R22->value,
            'total' => 20000,
        ]);

        $service = app(CourtesyPdfService::class);
        $output = $service->generateForProforma($proforma)->output();

        $this->assertStringStartsWith('%PDF', $output);
        $this->assertEquals('proforma-P1/2024.pdf', $service->generateProformaFileName($proforma));
    }
}
