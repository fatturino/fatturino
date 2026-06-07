<?php

use App\Enums\PaymentStatus;
use App\Models\Contact;
use App\Models\PurchaseInvoice;
use App\Models\SelfInvoice;
use App\Models\Sequence;
use App\Services\InvoiceXmlImportService;

test('xml import sets created_at to invoice issue date', function () {
    $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<p:FatturaElettronica xmlns:p="http://ivaservizi.agenziaentrate.gov.it/docs/xsd/fatture/v1.2" versione="FPR12">
  <FatturaElettronicaHeader>
    <DatiTrasmissione>
      <IdTrasmittente>
        <IdPaese>IT</IdPaese>
        <IdCodice>12345678901</IdCodice>
      </IdTrasmittente>
      <ProgressivoInvio>00001</ProgressivoInvio>
      <FormatoTrasmissione>FPR12</FormatoTrasmissione>
      <CodiceDestinatario>0000000</CodiceDestinatario>
    </DatiTrasmissione>
    <CedentePrestatore>
      <DatiAnagrafici>
        <IdFiscaleIVA>
          <IdPaese>IT</IdPaese>
          <IdCodice>01234567890</IdCodice>
        </IdFiscaleIVA>
        <Anagrafica>
          <Denominazione>Fornitore Test SRL</Denominazione>
        </Anagrafica>
        <RegimeFiscale>RF01</RegimeFiscale>
      </DatiAnagrafici>
      <Sede>
        <Indirizzo>Via Roma 1</Indirizzo>
        <CAP>20100</CAP>
        <Comune>Milano</Comune>
        <Provincia>MI</Provincia>
        <Nazione>IT</Nazione>
      </Sede>
    </CedentePrestatore>
    <CessionarioCommittente>
      <DatiAnagrafici>
        <IdFiscaleIVA>
          <IdPaese>IT</IdPaese>
          <IdCodice>09876543210</IdCodice>
        </IdFiscaleIVA>
        <Anagrafica>
          <Denominazione>Cliente Test SRL</Denominazione>
        </Anagrafica>
      </DatiAnagrafici>
      <Sede>
        <Indirizzo>Via Verdi 2</Indirizzo>
        <CAP>00100</CAP>
        <Comune>Roma</Comune>
        <Provincia>RM</Provincia>
        <Nazione>IT</Nazione>
      </Sede>
    </CessionarioCommittente>
  </FatturaElettronicaHeader>
  <FatturaElettronicaBody>
    <DatiGenerali>
      <DatiGeneraliDocumento>
        <TipoDocumento>TD01</TipoDocumento>
        <Divisa>EUR</Divisa>
        <Data>2026-04-30</Data>
        <Numero>TEST-XML-001</Numero>
        <ImportoTotaleDocumento>122.00</ImportoTotaleDocumento>
      </DatiGeneraliDocumento>
    </DatiGenerali>
    <DatiBeniServizi>
      <DettaglioLinee>
        <NumeroLinea>1</NumeroLinea>
        <Descrizione>Servizio test</Descrizione>
        <Quantita>1.00</Quantita>
        <PrezzoUnitario>100.00</PrezzoUnitario>
        <PrezzoTotale>100.00</PrezzoTotale>
        <AliquotaIVA>22.00</AliquotaIVA>
      </DettaglioLinee>
      <DatiRiepilogo>
        <AliquotaIVA>22.00</AliquotaIVA>
        <ImponibileImporto>100.00</ImponibileImporto>
        <Imposta>22.00</Imposta>
        <EsigibilitaIVA>I</EsigibilitaIVA>
      </DatiRiepilogo>
    </DatiBeniServizi>
  </FatturaElettronicaBody>
</p:FatturaElettronica>
XML;

    app(InvoiceXmlImportService::class)->importXml($xml, null, 'purchase');

    $invoice = PurchaseInvoice::query()->where('number', 'TEST-XML-001')->first();

    expect($invoice)->not->toBeNull();
    expect($invoice->created_at?->toDateString())->toBe('2026-04-30');
});

test('self invoice xml import records full payment on invoice date', function () {
    $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<p:FatturaElettronica xmlns:p="http://ivaservizi.agenziaentrate.gov.it/docs/xsd/fatture/v1.2" versione="FPR12">
  <FatturaElettronicaHeader>
    <DatiTrasmissione>
      <IdTrasmittente>
        <IdPaese>IT</IdPaese>
        <IdCodice>12345678901</IdCodice>
      </IdTrasmittente>
      <ProgressivoInvio>00002</ProgressivoInvio>
      <FormatoTrasmissione>FPR12</FormatoTrasmissione>
      <CodiceDestinatario>0000000</CodiceDestinatario>
    </DatiTrasmissione>
    <CedentePrestatore>
      <DatiAnagrafici>
        <IdFiscaleIVA>
          <IdPaese>DE</IdPaese>
          <IdCodice>123456789</IdCodice>
        </IdFiscaleIVA>
        <Anagrafica>
          <Denominazione>Fornitore Estero GmbH</Denominazione>
        </Anagrafica>
        <RegimeFiscale>RF01</RegimeFiscale>
      </DatiAnagrafici>
      <Sede>
        <Indirizzo>Teststrasse 1</Indirizzo>
        <CAP>10115</CAP>
        <Comune>Berlin</Comune>
        <Nazione>DE</Nazione>
      </Sede>
    </CedentePrestatore>
    <CessionarioCommittente>
      <DatiAnagrafici>
        <IdFiscaleIVA>
          <IdPaese>IT</IdPaese>
          <IdCodice>12345678903</IdCodice>
        </IdFiscaleIVA>
        <CodiceFiscale>12345678903</CodiceFiscale>
        <Anagrafica>
          <Denominazione>Test Company SRL</Denominazione>
        </Anagrafica>
      </DatiAnagrafici>
      <Sede>
        <Indirizzo>Via Test 1</Indirizzo>
        <CAP>20100</CAP>
        <Comune>Milano</Comune>
        <Provincia>MI</Provincia>
        <Nazione>IT</Nazione>
      </Sede>
    </CessionarioCommittente>
  </FatturaElettronicaHeader>
  <FatturaElettronicaBody>
    <DatiGenerali>
      <DatiGeneraliDocumento>
        <TipoDocumento>TD17</TipoDocumento>
        <Divisa>EUR</Divisa>
        <Data>2026-05-01</Data>
        <Numero>SELF-XML-001</Numero>
        <ImportoTotaleDocumento>122.00</ImportoTotaleDocumento>
      </DatiGeneraliDocumento>
      <DatiFattureCollegate>
        <IdDocumento>SUP-001</IdDocumento>
        <Data>2026-04-30</Data>
      </DatiFattureCollegate>
    </DatiGenerali>
    <DatiBeniServizi>
      <DettaglioLinee>
        <NumeroLinea>1</NumeroLinea>
        <Descrizione>Servizio estero</Descrizione>
        <Quantita>1.00</Quantita>
        <PrezzoUnitario>100.00</PrezzoUnitario>
        <PrezzoTotale>100.00</PrezzoTotale>
        <AliquotaIVA>22.00</AliquotaIVA>
      </DettaglioLinee>
      <DatiRiepilogo>
        <AliquotaIVA>22.00</AliquotaIVA>
        <ImponibileImporto>100.00</ImponibileImporto>
        <Imposta>22.00</Imposta>
        <EsigibilitaIVA>I</EsigibilitaIVA>
      </DatiRiepilogo>
    </DatiBeniServizi>
  </FatturaElettronicaBody>
</p:FatturaElettronica>
XML;

    app(InvoiceXmlImportService::class)->importXml($xml, null, 'self_invoice');

    $invoice = SelfInvoice::query()->where('number', 'SELF-XML-001')->first();

    expect($invoice)->not->toBeNull()
        ->and($invoice->payment_status)->toBe(PaymentStatus::Paid)
        ->and($invoice->total_paid)->toBe($invoice->total_gross)
        ->and($invoice->payments()->count())->toBe(1)
        ->and($invoice->payments()->first()?->paid_at?->toDateString())->toBe('2026-05-01');
});

test('self invoice xml import does not advance sequence when importing older historical numbers', function () {
    $sequence = Sequence::create([
        'name' => 'Autofatture',
        'type' => 'self_invoice',
        'pattern' => '{SEQ}-{ANNO}-AF',
    ]);

    $contact = Contact::create(['name' => 'Fornitore storico']);

    SelfInvoice::create([
        'number' => '27-2026-AF',
        'sequential_number' => 27,
        'date' => '2026-05-10',
        'contact_id' => $contact->id,
        'sequence_id' => $sequence->id,
        'fiscal_year' => 2026,
        'document_type' => 'TD17',
    ]);

    $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<p:FatturaElettronica xmlns:p="http://ivaservizi.agenziaentrate.gov.it/docs/xsd/fatture/v1.2" versione="FPR12">
  <FatturaElettronicaHeader>
    <DatiTrasmissione>
      <IdTrasmittente>
        <IdPaese>IT</IdPaese>
        <IdCodice>12345678901</IdCodice>
      </IdTrasmittente>
      <ProgressivoInvio>00003</ProgressivoInvio>
      <FormatoTrasmissione>FPR12</FormatoTrasmissione>
      <CodiceDestinatario>0000000</CodiceDestinatario>
    </DatiTrasmissione>
    <CedentePrestatore>
      <DatiAnagrafici>
        <IdFiscaleIVA>
          <IdPaese>DE</IdPaese>
          <IdCodice>123456789</IdCodice>
        </IdFiscaleIVA>
        <Anagrafica>
          <Denominazione>Fornitore Estero GmbH</Denominazione>
        </Anagrafica>
        <RegimeFiscale>RF01</RegimeFiscale>
      </DatiAnagrafici>
      <Sede>
        <Indirizzo>Teststrasse 1</Indirizzo>
        <CAP>10115</CAP>
        <Comune>Berlin</Comune>
        <Nazione>DE</Nazione>
      </Sede>
    </CedentePrestatore>
    <CessionarioCommittente>
      <DatiAnagrafici>
        <IdFiscaleIVA>
          <IdPaese>IT</IdPaese>
          <IdCodice>12345678903</IdCodice>
        </IdFiscaleIVA>
        <CodiceFiscale>12345678903</CodiceFiscale>
        <Anagrafica>
          <Denominazione>Test Company SRL</Denominazione>
        </Anagrafica>
      </DatiAnagrafici>
      <Sede>
        <Indirizzo>Via Test 1</Indirizzo>
        <CAP>20100</CAP>
        <Comune>Milano</Comune>
        <Provincia>MI</Provincia>
        <Nazione>IT</Nazione>
      </Sede>
    </CessionarioCommittente>
  </FatturaElettronicaHeader>
  <FatturaElettronicaBody>
    <DatiGenerali>
      <DatiGeneraliDocumento>
        <TipoDocumento>TD17</TipoDocumento>
        <Divisa>EUR</Divisa>
        <Data>2026-03-01</Data>
        <Numero>19-2026-AF</Numero>
        <ImportoTotaleDocumento>122.00</ImportoTotaleDocumento>
      </DatiGeneraliDocumento>
      <DatiFattureCollegate>
        <IdDocumento>SUP-019</IdDocumento>
        <Data>2026-02-28</Data>
      </DatiFattureCollegate>
    </DatiGenerali>
    <DatiBeniServizi>
      <DettaglioLinee>
        <NumeroLinea>1</NumeroLinea>
        <Descrizione>Servizio estero storico</Descrizione>
        <Quantita>1.00</Quantita>
        <PrezzoUnitario>100.00</PrezzoUnitario>
        <PrezzoTotale>100.00</PrezzoTotale>
        <AliquotaIVA>22.00</AliquotaIVA>
      </DettaglioLinee>
      <DatiRiepilogo>
        <AliquotaIVA>22.00</AliquotaIVA>
        <ImponibileImporto>100.00</ImponibileImporto>
        <Imposta>22.00</Imposta>
        <EsigibilitaIVA>I</EsigibilitaIVA>
      </DatiRiepilogo>
    </DatiBeniServizi>
  </FatturaElettronicaBody>
</p:FatturaElettronica>
XML;

    app(InvoiceXmlImportService::class)->importXml($xml, $sequence->id, 'self_invoice');

    $imported = SelfInvoice::query()->where('number', '19-2026-AF')->first();

    expect($imported)->not->toBeNull()
        ->and($imported->sequential_number)->toBe(19)
        ->and($sequence->fresh()->getNextNumber(2026))->toBe(28)
        ->and($sequence->fresh()->getFormattedNumber(2026))->toBe('28-2026-AF');
});
