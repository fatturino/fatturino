<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\VatRate;
use App\Livewire\Imports\Index;
use App\Models\Contact;
use App\Models\Invoice;
use App\Models\PurchaseInvoice;
use App\Models\Sequence;
use App\Services\InvoiceXmlImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

class InvoiceXmlImportTest extends TestCase
{
    use RefreshDatabase;

    private InvoiceXmlImportService $importer;

    private Sequence $sequence;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importer = app(InvoiceXmlImportService::class);
        $this->sequence = Sequence::create([
            'name' => 'Fatture Vendita',
            'pattern' => 'FV-{SEQ}',
            'type' => 'electronic_invoice',
        ]);
    }

    public function test_imports_invoice_from_xml()
    {
        $xml = $this->buildSampleXml();
        $this->importer->importXml($xml, $this->sequence->id);

        $stats = $this->importer->getStats();
        $this->assertEquals(1, $stats['invoices_imported']);
        $this->assertEquals(0, $stats['errors']);

        // Verify invoice was created
        $invoice = Invoice::first();
        $this->assertNotNull($invoice);
        $this->assertEquals('4-2026-FE', $invoice->number);
        $this->assertEquals('2026-02-02', $invoice->date->format('Y-m-d'));
        $this->assertEquals(InvoiceStatus::Sent, $invoice->status);
        $this->assertEquals($this->sequence->id, $invoice->sequence_id);
    }

    public function test_creates_contact_from_xml()
    {
        $xml = $this->buildSampleXml();
        $this->importer->importXml($xml, $this->sequence->id);

        $contact = Contact::first();
        $this->assertNotNull($contact);
        $this->assertEquals('TRIANGLE CORPORATION SRL', $contact->name);
        $this->assertEquals('04037090984', $contact->vat_number);
        $this->assertEquals('PIAZZA CAVOUR N 21', $contact->address);
        $this->assertEquals('25038', $contact->postal_code);
        $this->assertEquals('ROVATO', $contact->city);
        $this->assertEquals('BS', $contact->province);
        $this->assertEquals(1, $contact->is_customer);
    }

    public function test_reuses_existing_contact_by_vat_number()
    {
        // Pre-create the contact
        $existing = Contact::create([
            'name' => 'Triangle Corp',
            'vat_number' => '04037090984',
            'is_customer' => true,
        ]);

        $xml = $this->buildSampleXml();
        $this->importer->importXml($xml, $this->sequence->id);

        // Should not have created a new contact
        $this->assertEquals(1, Contact::count());
        $this->assertEquals($existing->id, Invoice::first()->contact_id);

        $stats = $this->importer->getStats();
        $this->assertEquals(0, $stats['contacts_created']);
    }

    public function test_creates_invoice_lines_with_correct_amounts()
    {
        $xml = $this->buildSampleXml();
        $this->importer->importXml($xml, $this->sequence->id);

        $invoice = Invoice::first();
        $this->assertNotNull($invoice);
        $this->assertCount(1, $invoice->lines);

        $line = $invoice->lines->first();
        $this->assertEquals('Consulenza IT - Gennaio 2026', $line->description);
        $this->assertEquals('1.00', $line->quantity);
        // 2000.00 EUR = 200000 cents
        $this->assertEquals(200000, $line->unit_price);
        $this->assertEquals(200000, $line->total);
    }

    public function test_finds_or_creates_vat_rate()
    {
        $xml = $this->buildSampleXml();
        $this->importer->importXml($xml, $this->sequence->id);

        // The import service maps 22% to the R22 enum case
        $line = Invoice::first()->lines->first();
        $this->assertEquals(VatRate::R22, $line->vat_rate);
    }

    public function test_imports_withholding_tax_data()
    {
        $xml = $this->buildSampleXml();
        $this->importer->importXml($xml, $this->sequence->id);

        $invoice = Invoice::first();
        $this->assertTrue($invoice->withholding_tax_enabled);
        $this->assertEquals(20.00, $invoice->withholding_tax_percent);
        // 400.00 EUR = 40000 cents
        $this->assertEquals(40000, $invoice->withholding_tax_amount);
    }

    public function test_imports_payment_details()
    {
        $xml = $this->buildSampleXml();
        $this->importer->importXml($xml, $this->sequence->id);

        $invoice = Invoice::first();
        $this->assertEquals('MP05', $invoice->payment_method->value);
        $this->assertEquals('TP02', $invoice->payment_terms->value);
        $this->assertEquals('BBVA', $invoice->bank_name);
        $this->assertEquals('IT60X0542811101000000123456', $invoice->bank_iban);
    }

    public function test_imports_pec_to_contact()
    {
        $xml = $this->buildSampleXmlWithPec();
        $this->importer->importXml($xml, $this->sequence->id);

        $contact = Contact::first();
        $this->assertEquals('flexi@pec.it', $contact->pec);
    }

    public function test_handles_invalid_xml_gracefully()
    {
        $this->importer->importXml('not valid xml', $this->sequence->id);

        $stats = $this->importer->getStats();
        $this->assertEquals(1, $stats['errors']);
        $this->assertEquals(0, $stats['invoices_imported']);
        $this->assertNotEmpty($this->importer->getErrors());
    }

    public function test_imports_multiple_xml_files()
    {
        $xml1 = $this->buildSampleXml();
        $xml2 = $this->buildSampleXmlWithPec();

        $this->importer->importXml($xml1, $this->sequence->id);
        $this->importer->importXml($xml2, $this->sequence->id);

        $stats = $this->importer->getStats();
        $this->assertEquals(2, $stats['invoices_imported']);
        $this->assertEquals(2, Invoice::count());
    }

    // --- Purchase import tests ---

    public function test_imports_purchase_invoice_from_xml()
    {
        $purchaseSequence = Sequence::create([
            'name' => 'Fatture Acquisto',
            'pattern' => 'FA-{SEQ}',
            'type' => 'purchase',
        ]);

        $xml = $this->buildSamplePurchaseXml();
        $this->importer->importXml($xml, $purchaseSequence->id, 'purchase');

        $stats = $this->importer->getStats();
        $this->assertEquals(1, $stats['invoices_imported']);
        $this->assertEquals(0, $stats['errors']);

        // Should be a PurchaseInvoice, not a regular Invoice
        $invoice = PurchaseInvoice::first();
        $this->assertNotNull($invoice);
        $this->assertEquals('IT26-AEUI-2526695', $invoice->number);
        $this->assertEquals('2026-02-16', $invoice->date->format('Y-m-d'));
        $this->assertEquals(InvoiceStatus::Generated, $invoice->status);
        $this->assertEquals('purchase', $invoice->type);
        $this->assertEquals($purchaseSequence->id, $invoice->sequence_id);
    }

    public function test_purchase_import_creates_supplier_contact()
    {
        $purchaseSequence = Sequence::create([
            'name' => 'Fatture Acquisto',
            'pattern' => 'FA-{SEQ}',
            'type' => 'purchase',
        ]);

        $xml = $this->buildSamplePurchaseXml();
        $this->importer->importXml($xml, $purchaseSequence->id, 'purchase');

        // Contact should be the seller (CedentePrestatore), not the buyer
        $contact = Contact::first();
        $this->assertNotNull($contact);
        $this->assertEquals('Amazon EU S.a r.l., Succursale Italiana', $contact->name);
        $this->assertEquals('08973230967', $contact->vat_number);
        $this->assertEquals(1, $contact->is_supplier);
        $this->assertEquals(0, $contact->is_customer);
    }

    public function test_purchase_import_marks_existing_contact_as_supplier()
    {
        $purchaseSequence = Sequence::create([
            'name' => 'Fatture Acquisto',
            'pattern' => 'FA-{SEQ}',
            'type' => 'purchase',
        ]);

        $existing = Contact::create([
            'name' => 'Amazon IT',
            'vat_number' => '08973230967',
            'is_customer' => true,
            'is_supplier' => false,
        ]);

        $xml = $this->buildSamplePurchaseXml();
        $this->importer->importXml($xml, $purchaseSequence->id, 'purchase');

        $this->assertEquals(1, Contact::count());
        $existing->refresh();
        $this->assertTrue((bool) $existing->is_supplier);
    }

    public function test_purchase_import_creates_invoice_lines()
    {
        $purchaseSequence = Sequence::create([
            'name' => 'Fatture Acquisto',
            'pattern' => 'FA-{SEQ}',
            'type' => 'purchase',
        ]);

        $xml = $this->buildSamplePurchaseXml();
        $this->importer->importXml($xml, $purchaseSequence->id, 'purchase');

        $invoice = PurchaseInvoice::first();
        $this->assertCount(2, $invoice->lines);

        $firstLine = $invoice->lines->first();
        $this->assertStringContainsString('Hag', $firstLine->description);
        $this->assertEquals(2294, $firstLine->unit_price);
    }

    public function test_purchase_invoices_are_not_visible_from_invoice_model()
    {
        $purchaseSequence = Sequence::create([
            'name' => 'Fatture Acquisto',
            'pattern' => 'FA-{SEQ}',
            'type' => 'purchase',
        ]);

        // Import one sales and one purchase invoice
        $this->importer->importXml($this->buildSampleXml(), $this->sequence->id);
        $this->importer->importXml($this->buildSamplePurchaseXml(), $purchaseSequence->id, 'purchase');

        // Invoice model should only return sales invoices
        $this->assertEquals(1, Invoice::count());
        $this->assertEquals('sales', Invoice::first()->type);

        // PurchaseInvoice model should only return purchase invoices
        $this->assertEquals(1, PurchaseInvoice::count());
        $this->assertEquals('purchase', PurchaseInvoice::first()->type);
    }

    public function test_zip_import_filters_out_p7m_and_metadata_files()
    {
        // Create a temp ZIP simulating Agenzia delle Entrate bulk export.
        // It contains: plain .xml, .p7m, _metaDato.xml, and .p7m_metaDato.xml.
        // Only the plain .xml files should be imported.

        $xmlInvoice1 = $this->buildSampleXml();
        $xmlInvoice2 = $this->buildSampleXmlWithPec();

        $tempZip = tempnam(sys_get_temp_dir(), 'fatturino_test_zip_');
        $zip = new \ZipArchive;
        $zip->open($tempZip, \ZipArchive::CREATE);
        $zip->addFromString('IT01641790702_ABC123.xml', $xmlInvoice1);
        $zip->addFromString('IT01879020517_DEF456.xml', $xmlInvoice2);
        // .p7m files are now imported (service auto-detects and extracts P7M)
        // This fake content will fail parsing → 1 error
        $zip->addFromString('IT01879020517_GHI789.xml.p7m', 'fake-p7m-content');
        // Metadata files are still skipped by the ZIP filter
        $zip->addFromString('IT01641790702_ABC123.xml_metaDato.xml', '<meta>fake</meta>');
        $zip->addFromString('IT01879020517_GHI789.xml.p7m_metaDato.xml', '<meta>fake</meta>');
        $zip->close();

        $zipContent = file_get_contents($tempZip);
        unlink($tempZip);

        $uploadedFile = UploadedFile::fake()->createWithContent('fatture.zip', $zipContent);

        Livewire::test(Index::class)
            ->set('importType', 'xml_sales')
            ->set('xmlFile', $uploadedFile)
            ->call('runImport')
            ->assertSet('importResult.type', 'xml_sales')
            ->assertSet('importResult.stats.invoices_imported', 2)
            ->assertSet('importResult.stats.errors', 1);

        $this->assertEquals(2, Invoice::count());
    }

    /**
     * Build a sample SDI XML string for testing (customer with SDI code).
     */
    private function buildSampleXml(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<p:FatturaElettronica xmlns:p="http://ivaservizi.agenziaentrate.gov.it/docs/xsd/fatture/v1.2" versione="FPR12">
  <FatturaElettronicaHeader>
    <DatiTrasmissione>
      <IdTrasmittente>
        <IdPaese>IT</IdPaese>
        <IdCodice>11359591002</IdCodice>
      </IdTrasmittente>
      <ProgressivoInvio>1yuGk</ProgressivoInvio>
      <FormatoTrasmissione>FPR12</FormatoTrasmissione>
      <CodiceDestinatario>M5UXCR1</CodiceDestinatario>
    </DatiTrasmissione>
    <CedentePrestatore>
      <DatiAnagrafici>
        <IdFiscaleIVA>
          <IdPaese>IT</IdPaese>
          <IdCodice>04826950166</IdCodice>
        </IdFiscaleIVA>
        <CodiceFiscale>LNRDNL87S06G388W</CodiceFiscale>
        <Anagrafica>
          <Nome>Daniele</Nome>
          <Cognome>Lenares</Cognome>
        </Anagrafica>
        <RegimeFiscale>RF01</RegimeFiscale>
      </DatiAnagrafici>
      <Sede>
        <Indirizzo>Via Treviglio, 18E</Indirizzo>
        <CAP>24040</CAP>
        <Comune>Calvenzano</Comune>
        <Provincia>BG</Provincia>
        <Nazione>IT</Nazione>
      </Sede>
    </CedentePrestatore>
    <CessionarioCommittente>
      <DatiAnagrafici>
        <IdFiscaleIVA>
          <IdPaese>IT</IdPaese>
          <IdCodice>04037090984</IdCodice>
        </IdFiscaleIVA>
        <Anagrafica>
          <Denominazione>TRIANGLE CORPORATION SRL</Denominazione>
        </Anagrafica>
      </DatiAnagrafici>
      <Sede>
        <Indirizzo>PIAZZA CAVOUR N 21</Indirizzo>
        <CAP>25038</CAP>
        <Comune>ROVATO</Comune>
        <Provincia>BS</Provincia>
        <Nazione>IT</Nazione>
      </Sede>
    </CessionarioCommittente>
  </FatturaElettronicaHeader>
  <FatturaElettronicaBody>
    <DatiGenerali>
      <DatiGeneraliDocumento>
        <TipoDocumento>TD01</TipoDocumento>
        <Divisa>EUR</Divisa>
        <Data>2026-02-02</Data>
        <Numero>4-2026-FE</Numero>
        <DatiRitenuta>
          <TipoRitenuta>RT01</TipoRitenuta>
          <ImportoRitenuta>400.00</ImportoRitenuta>
          <AliquotaRitenuta>20.00</AliquotaRitenuta>
          <CausalePagamento>A</CausalePagamento>
        </DatiRitenuta>
        <ImportoTotaleDocumento>2440.00</ImportoTotaleDocumento>
      </DatiGeneraliDocumento>
    </DatiGenerali>
    <DatiBeniServizi>
      <DettaglioLinee>
        <NumeroLinea>1</NumeroLinea>
        <Descrizione>Consulenza IT - Gennaio 2026</Descrizione>
        <Quantita>1.00</Quantita>
        <UnitaMisura>pz</UnitaMisura>
        <PrezzoUnitario>2000.00</PrezzoUnitario>
        <PrezzoTotale>2000.00</PrezzoTotale>
        <AliquotaIVA>22.00</AliquotaIVA>
        <Ritenuta>SI</Ritenuta>
      </DettaglioLinee>
      <DatiRiepilogo>
        <AliquotaIVA>22.00</AliquotaIVA>
        <ImponibileImporto>2000.00</ImponibileImporto>
        <Imposta>440.00</Imposta>
        <EsigibilitaIVA>I</EsigibilitaIVA>
      </DatiRiepilogo>
    </DatiBeniServizi>
    <DatiPagamento>
      <CondizioniPagamento>TP02</CondizioniPagamento>
      <DettaglioPagamento>
        <Beneficiario>Daniele Lenares</Beneficiario>
        <ModalitaPagamento>MP05</ModalitaPagamento>
        <ImportoPagamento>2040.00</ImportoPagamento>
        <IstitutoFinanziario>BBVA</IstitutoFinanziario>
        <IBAN>IT60X0542811101000000123456</IBAN>
      </DettaglioPagamento>
    </DatiPagamento>
  </FatturaElettronicaBody>
</p:FatturaElettronica>
XML;
    }

    /**
     * Build a sample purchase invoice XML (we are the buyer, supplier is CedentePrestatore).
     */
    private function buildSamplePurchaseXml(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<ns2:FatturaElettronica xmlns:ns2="http://ivaservizi.agenziaentrate.gov.it/docs/xsd/fatture/v1.2" versione="FPR12">
  <FatturaElettronicaHeader>
    <DatiTrasmissione>
      <IdTrasmittente><IdPaese>IT</IdPaese><IdCodice>08973230967</IdCodice></IdTrasmittente>
      <ProgressivoInvio>Efskd</ProgressivoInvio>
      <FormatoTrasmissione>FPR12</FormatoTrasmissione>
      <CodiceDestinatario>SZLUBAI</CodiceDestinatario>
    </DatiTrasmissione>
    <CedentePrestatore>
      <DatiAnagrafici>
        <IdFiscaleIVA><IdPaese>IT</IdPaese><IdCodice>08973230967</IdCodice></IdFiscaleIVA>
        <Anagrafica><Denominazione>Amazon EU S.a r.l., Succursale Italiana</Denominazione></Anagrafica>
        <RegimeFiscale>RF01</RegimeFiscale>
      </DatiAnagrafici>
      <Sede>
        <Indirizzo>38 avenue John F. Kennedy</Indirizzo>
        <CAP>01855</CAP>
        <Comune>Luxemburgo</Comune>
        <Nazione>LU</Nazione>
      </Sede>
    </CedentePrestatore>
    <CessionarioCommittente>
      <DatiAnagrafici>
        <IdFiscaleIVA><IdPaese>IT</IdPaese><IdCodice>04826950166</IdCodice></IdFiscaleIVA>
        <Anagrafica><Denominazione>Daniele Lenares</Denominazione></Anagrafica>
      </DatiAnagrafici>
      <Sede>
        <Indirizzo>Via Treviglio 18E</Indirizzo>
        <CAP>24040</CAP>
        <Comune>Calvenzano</Comune>
        <Nazione>IT</Nazione>
      </Sede>
    </CessionarioCommittente>
  </FatturaElettronicaHeader>
  <FatturaElettronicaBody>
    <DatiGenerali>
      <DatiGeneraliDocumento>
        <TipoDocumento>TD01</TipoDocumento>
        <Divisa>EUR</Divisa>
        <Data>2026-02-16</Data>
        <Numero>IT26-AEUI-2526695</Numero>
        <ImportoTotaleDocumento>27.99</ImportoTotaleDocumento>
      </DatiGeneraliDocumento>
    </DatiGenerali>
    <DatiBeniServizi>
      <DettaglioLinee>
        <NumeroLinea>1</NumeroLinea>
        <Descrizione>Hag - Capsule Caffe Decaffeinato</Descrizione>
        <Quantita>1.00</Quantita>
        <PrezzoUnitario>22.94</PrezzoUnitario>
        <PrezzoTotale>22.94</PrezzoTotale>
        <AliquotaIVA>22.00</AliquotaIVA>
      </DettaglioLinee>
      <DettaglioLinee>
        <NumeroLinea>2</NumeroLinea>
        <Descrizione>Costi di spedizione</Descrizione>
        <Quantita>1.00</Quantita>
        <PrezzoUnitario>0.00</PrezzoUnitario>
        <PrezzoTotale>0.00</PrezzoTotale>
        <AliquotaIVA>22.00</AliquotaIVA>
      </DettaglioLinee>
      <DatiRiepilogo>
        <AliquotaIVA>22.00</AliquotaIVA>
        <ImponibileImporto>22.95</ImponibileImporto>
        <Imposta>5.05</Imposta>
      </DatiRiepilogo>
    </DatiBeniServizi>
    <DatiPagamento>
      <CondizioniPagamento>TP02</CondizioniPagamento>
      <DettaglioPagamento>
        <ModalitaPagamento>MP08</ModalitaPagamento>
        <ImportoPagamento>27.99</ImportoPagamento>
      </DettaglioPagamento>
    </DatiPagamento>
  </FatturaElettronicaBody>
</ns2:FatturaElettronica>
XML;
    }

    /**
     * Build a sample SDI XML with PEC destination (no SDI code).
     */
    private function buildSampleXmlWithPec(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<p:FatturaElettronica xmlns:p="http://ivaservizi.agenziaentrate.gov.it/docs/xsd/fatture/v1.2" versione="FPR12">
  <FatturaElettronicaHeader>
    <DatiTrasmissione>
      <IdTrasmittente>
        <IdPaese>IT</IdPaese>
        <IdCodice>11359591002</IdCodice>
      </IdTrasmittente>
      <ProgressivoInvio>1xONz</ProgressivoInvio>
      <FormatoTrasmissione>FPR12</FormatoTrasmissione>
      <CodiceDestinatario>0000000</CodiceDestinatario>
      <PECDestinatario>flexi@pec.it</PECDestinatario>
    </DatiTrasmissione>
    <CedentePrestatore>
      <DatiAnagrafici>
        <IdFiscaleIVA>
          <IdPaese>IT</IdPaese>
          <IdCodice>04826950166</IdCodice>
        </IdFiscaleIVA>
        <CodiceFiscale>LNRDNL87S06G388W</CodiceFiscale>
        <Anagrafica>
          <Nome>Daniele</Nome>
          <Cognome>Lenares</Cognome>
        </Anagrafica>
        <RegimeFiscale>RF01</RegimeFiscale>
      </DatiAnagrafici>
      <Sede>
        <Indirizzo>Via Treviglio, 18E</Indirizzo>
        <CAP>24040</CAP>
        <Comune>Calvenzano</Comune>
        <Provincia>BG</Provincia>
        <Nazione>IT</Nazione>
      </Sede>
    </CedentePrestatore>
    <CessionarioCommittente>
      <DatiAnagrafici>
        <IdFiscaleIVA>
          <IdPaese>IT</IdPaese>
          <IdCodice>13235870964</IdCodice>
        </IdFiscaleIVA>
        <Anagrafica>
          <Denominazione>Flexi S.r.l.</Denominazione>
        </Anagrafica>
      </DatiAnagrafici>
      <Sede>
        <Indirizzo>Viale Andrea Doria 10</Indirizzo>
        <CAP>20124</CAP>
        <Comune>Milano</Comune>
        <Provincia>MI</Provincia>
        <Nazione>IT</Nazione>
      </Sede>
    </CessionarioCommittente>
  </FatturaElettronicaHeader>
  <FatturaElettronicaBody>
    <DatiGenerali>
      <DatiGeneraliDocumento>
        <TipoDocumento>TD01</TipoDocumento>
        <Divisa>EUR</Divisa>
        <Data>2026-01-02</Data>
        <Numero>1-2026-FE</Numero>
        <DatiRitenuta>
          <TipoRitenuta>RT01</TipoRitenuta>
          <ImportoRitenuta>500.00</ImportoRitenuta>
          <AliquotaRitenuta>20.00</AliquotaRitenuta>
          <CausalePagamento>A</CausalePagamento>
        </DatiRitenuta>
        <ImportoTotaleDocumento>3050.00</ImportoTotaleDocumento>
      </DatiGeneraliDocumento>
    </DatiGenerali>
    <DatiBeniServizi>
      <DettaglioLinee>
        <NumeroLinea>1</NumeroLinea>
        <Descrizione>Consulenza IT - Dicembre 2025</Descrizione>
        <Quantita>1.00</Quantita>
        <UnitaMisura>pz</UnitaMisura>
        <PrezzoUnitario>2500.00</PrezzoUnitario>
        <PrezzoTotale>2500.00</PrezzoTotale>
        <AliquotaIVA>22.00</AliquotaIVA>
        <Ritenuta>SI</Ritenuta>
      </DettaglioLinee>
      <DatiRiepilogo>
        <AliquotaIVA>22.00</AliquotaIVA>
        <ImponibileImporto>2500.00</ImponibileImporto>
        <Imposta>550.00</Imposta>
        <EsigibilitaIVA>I</EsigibilitaIVA>
      </DatiRiepilogo>
    </DatiBeniServizi>
    <DatiPagamento>
      <CondizioniPagamento>TP02</CondizioniPagamento>
      <DettaglioPagamento>
        <Beneficiario>Daniele Lenares</Beneficiario>
        <ModalitaPagamento>MP05</ModalitaPagamento>
        <ImportoPagamento>2550.00</ImportoPagamento>
        <IstitutoFinanziario>BBVA</IstitutoFinanziario>
        <IBAN>IT60X0542811101000000123456</IBAN>
      </DettaglioPagamento>
    </DatiPagamento>
  </FatturaElettronicaBody>
</p:FatturaElettronica>
XML;
    }
}
