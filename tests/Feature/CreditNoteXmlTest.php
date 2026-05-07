<?php

namespace Tests\Feature;

use App\Enums\VatRate;
use App\Models\Contact;
use App\Models\CreditNote;
use App\Models\InvoiceLine;
use App\Services\CreditNoteXmlService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreditNoteXmlTest extends TestCase
{
    use RefreshDatabase;

    private function makeCreditNoteWithLine(float $vatPercent = 22.0): CreditNote
    {
        $contact = Contact::create([
            'name' => 'Cliente SpA',
            'vat_number' => 'IT01234567890',
            'address' => 'Via Roma 1',
            'city' => 'Milano',
            'postal_code' => '20100',
            'province' => 'MI',
            'country' => 'IT',
            'sdi_code' => 'ABCDEFG',
        ]);

        // Map the percent value to the matching enum case
        $vatRateEnum = match ($vatPercent) {
            22.0 => VatRate::R22,
            10.0 => VatRate::R10,
            5.0 => VatRate::R5,
            4.0 => VatRate::R4,
            default => VatRate::R22,
        };

        $creditNote = CreditNote::create([
            'number' => 'NC-001',
            'date' => now(),
            'contact_id' => $contact->id,
            'related_invoice_number' => 'FT-2026-001',
            'related_invoice_date' => now()->subMonth(),
            'total_net' => 10000,
            'total_vat' => (int) round(10000 * $vatPercent / 100),
            'total_gross' => 10000 + (int) round(10000 * $vatPercent / 100),
        ]);

        InvoiceLine::create([
            'invoice_id' => $creditNote->id,
            'description' => 'Reso merce',
            'quantity' => 1,
            'unit_price' => 10000,
            'total' => 10000,
            'vat_rate' => $vatRateEnum->value,
        ]);

        return $creditNote;
    }

    public function test_generates_valid_xml_with_td04()
    {
        $creditNote = $this->makeCreditNoteWithLine();
        $service = app(CreditNoteXmlService::class);

        $xml = $service->generate($creditNote);

        $this->assertNotEmpty($xml);
        $this->assertStringContainsString('<?xml', $xml);
        $this->assertStringContainsString('TD04', $xml);
    }

    public function test_xml_includes_dati_fatture_collegate_when_related_invoice_set()
    {
        $creditNote = $this->makeCreditNoteWithLine();
        $service = app(CreditNoteXmlService::class);

        $xml = $service->generate($creditNote);

        // DatiFattureCollegate section with related invoice number
        $this->assertStringContainsString('FT-2026-001', $xml);
    }

    public function test_xml_does_not_include_dati_fatture_collegate_when_not_set()
    {
        $contact = Contact::create([
            'name' => 'Cliente SpA',
            'vat_number' => 'IT01234567890',
            'address' => 'Via Roma 1',
            'city' => 'Milano',
            'postal_code' => '20100',
            'province' => 'MI',
            'country' => 'IT',
            'sdi_code' => 'ABCDEFG',
        ]);

        $creditNote = CreditNote::create([
            'number' => 'NC-002',
            'date' => now(),
            'contact_id' => $contact->id,
            'total_net' => 5000,
            'total_vat' => 1100,
            'total_gross' => 6100,
        ]);

        InvoiceLine::create([
            'invoice_id' => $creditNote->id,
            'description' => 'Nota senza riferimento',
            'quantity' => 1,
            'unit_price' => 5000,
            'total' => 5000,
            'vat_rate' => VatRate::R22->value,
        ]);

        $service = app(CreditNoteXmlService::class);
        $xml = $service->generate($creditNote);

        // DatiFattureCollegate should not be present
        $this->assertStringNotContainsString('DatiFattureCollegate', $xml);
    }

    public function test_generate_file_name_follows_sdi_convention()
    {
        $creditNote = $this->makeCreditNoteWithLine();
        $service = app(CreditNoteXmlService::class);

        $fileName = $service->generateFileName($creditNote);

        // SDI convention: CCIdCodice_ProgressivoInvio.xml
        $this->assertStringEndsWith('.xml', $fileName);
        $this->assertStringContainsString('_', $fileName);
    }
}
