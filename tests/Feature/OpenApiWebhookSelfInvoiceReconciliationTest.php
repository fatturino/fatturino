<?php

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Enums\SdiStatus;
use App\Models\EiInboundLog;
use App\Models\PurchaseInvoice;
use App\Models\SalesInvoice;
use App\Models\SdiUuidLink;
use App\Models\SelfInvoice;
use App\Services\OpenApiSdiService;
use App\Settings\CompanySettings;
use App\Settings\OpenApiSettings;

afterEach(function () {
    Mockery::close();
});

beforeEach(function () {
    $companySettings = app(CompanySettings::class);
    $companySettings->company_vat_number = '12345678903';
    $companySettings->save();

    $settings = app(OpenApiSettings::class);
    $settings->api_token = 'test-token';
    $settings->sandbox = true;
    $settings->company_sdi_code = 'JKKZDGR';
    $settings->activated = true;
    $settings->webhook_secret = 'webhook-secret';
    $settings->webhook_url = '';
    $settings->save();
});

test('supplier invoice webhook marks existing self-invoice as delivered without creating payments', function () {
    $selfInvoice = SelfInvoice::factory()->create([
        'number' => '1/INT',
        'document_type' => 'TD17',
        'payment_status' => PaymentStatus::Unpaid,
        'sdi_status' => SdiStatus::Sent,
        'total_gross' => 12200,
    ]);

    $service = Mockery::mock(OpenApiSdiService::class);
    $service->shouldReceive('downloadInvoiceXml')
        ->once()
        ->with('inbound-self-uuid')
        ->andReturn([
            'success' => true,
            'xml' => makeInboundInvoiceXml('TD17', '1/INT'),
        ]);
    app()->instance(OpenApiSdiService::class, $service);

    $response = $this
        ->withHeader('Authorization', 'Bearer webhook-secret')
        ->postJson(route('openapi.webhook'), supplierInvoiceWebhookPayload('inbound-self-uuid'));

    $response->assertOk()->assertJson([
        'status' => 'ok',
        'message' => null,
    ]);

    $selfInvoice->refresh();

    expect($selfInvoice->sdi_status)->toBe(SdiStatus::Delivered)
        ->and($selfInvoice->sdi_message)->toBe('Consegnata (ricevuta come acquisto)')
        ->and($selfInvoice->sdi_primary_channel)->toBe('inbound')
        ->and($selfInvoice->payment_status)->toBe(PaymentStatus::Unpaid)
        ->and($selfInvoice->payments()->count())->toBe(0)
        ->and(PurchaseInvoice::withoutGlobalScopes()->where('type', 'purchase')->count())->toBe(0);

    expect(SdiUuidLink::query()->where('inbound_uuid', 'inbound-self-uuid')->value('fiscal_document_id'))
        ->toBe($selfInvoice->id);

    expect(EiInboundLog::query()->latest('id')->first()?->processing_status)
        ->toBe('processed');
});

test('supplier invoice webhook skips missing self-invoice without creating documents', function () {
    $service = Mockery::mock(OpenApiSdiService::class);
    $service->shouldReceive('downloadInvoiceXml')
        ->once()
        ->with('missing-self-uuid')
        ->andReturn([
            'success' => true,
            'xml' => makeInboundInvoiceXml('TD17', '2/INT'),
        ]);
    app()->instance(OpenApiSdiService::class, $service);

    $response = $this
        ->withHeader('Authorization', 'Bearer webhook-secret')
        ->postJson(route('openapi.webhook'), supplierInvoiceWebhookPayload('missing-self-uuid'));

    $response->assertOk()->assertJson([
        'status' => 'ignored',
        'message' => 'Self-invoice not found locally for inbound reconciliation',
    ]);

    expect(SelfInvoice::withoutGlobalScopes()->count())->toBe(0)
        ->and(PurchaseInvoice::withoutGlobalScopes()->where('type', 'purchase')->count())->toBe(0);

    $log = EiInboundLog::query()->latest('id')->first();

    expect($log->processing_status)->toBe('processed')
        ->and($log->linked_fiscal_document_id)->toBeNull()
        ->and($log->error_message)->toBe('Self-invoice not found locally for inbound reconciliation');
});

test('supplier invoice webhook keeps importing real purchase invoices', function () {
    $service = Mockery::mock(OpenApiSdiService::class);
    $service->shouldReceive('downloadInvoiceXml')
        ->once()
        ->with('purchase-uuid')
        ->andReturn([
            'success' => true,
            'xml' => makeInboundInvoiceXml('TD01', 'FORN-2026-001'),
        ]);
    app()->instance(OpenApiSdiService::class, $service);

    $response = $this
        ->withHeader('Authorization', 'Bearer webhook-secret')
        ->postJson(route('openapi.webhook'), supplierInvoiceWebhookPayload('purchase-uuid'));

    $response->assertOk()->assertJson([
        'status' => 'ok',
        'message' => null,
    ]);

    $purchase = PurchaseInvoice::withoutGlobalScopes()->first();

    expect($purchase)->not->toBeNull()
        ->and($purchase->number)->toBe('FORN-2026-001')
        ->and(SelfInvoice::withoutGlobalScopes()->where('type', 'self_invoice')->count())->toBe(0);
});

test('supplier invoice webhook skips invoices for another company vat', function () {
    $service = Mockery::mock(OpenApiSdiService::class);
    $service->shouldReceive('downloadInvoiceXml')
        ->once()
        ->with('foreign-company-uuid')
        ->andReturn([
            'success' => true,
            'xml' => makeInboundInvoiceXml('TD01', 'FORN-2026-999', '99999999999'),
        ]);
    app()->instance(OpenApiSdiService::class, $service);

    $response = $this
        ->withHeader('Authorization', 'Bearer webhook-secret')
        ->postJson(route('openapi.webhook'), supplierInvoiceWebhookPayload('foreign-company-uuid'));

    $response->assertOk()->assertJson([
        'status' => 'ignored',
        'message' => 'Supplier invoice does not belong to current company',
    ]);

    expect(PurchaseInvoice::withoutGlobalScopes()->where('type', 'purchase')->count())->toBe(0)
        ->and(SelfInvoice::withoutGlobalScopes()->count())->toBe(0);
});

test('supplier invoice webhook does not match self-invoice when inbound number differs', function () {
    SelfInvoice::factory()->create([
        'number' => '1/INT',
        'document_type' => 'TD17',
        'payment_status' => PaymentStatus::Unpaid,
        'sdi_status' => SdiStatus::Sent,
    ]);

    $service = Mockery::mock(OpenApiSdiService::class);
    $service->shouldReceive('downloadInvoiceXml')
        ->once()
        ->with('mismatch-self-uuid')
        ->andReturn([
            'success' => true,
            'xml' => makeInboundInvoiceXml('TD17', '2/INT'),
        ]);
    app()->instance(OpenApiSdiService::class, $service);

    $response = $this
        ->withHeader('Authorization', 'Bearer webhook-secret')
        ->postJson(route('openapi.webhook'), supplierInvoiceWebhookPayload('mismatch-self-uuid'));

    $response->assertOk()->assertJson([
        'status' => 'ignored',
        'message' => 'Self-invoice not found locally for inbound reconciliation',
    ]);

    expect(SelfInvoice::withoutGlobalScopes()->count())->toBe(1)
        ->and(PurchaseInvoice::withoutGlobalScopes()->where('type', 'purchase')->count())->toBe(0);
});

test('reconcile command skips missing self-invoice without creating documents', function () {
    $service = Mockery::mock(OpenApiSdiService::class);
    $service->shouldReceive('isConfigured')->once()->andReturnTrue();
    $service->shouldReceive('getSupplierInvoices')
        ->once()
        ->with(Mockery::on(fn (array $filters) => ($filters['recipient'] ?? null) === '12345678903'))
        ->andReturn([
            'success' => true,
            'data' => [[
                'uuid' => 'reconcile-missing-uuid',
                'created_at' => now()->toIso8601String(),
                'filename' => 'IT_RECONCILE.xml',
            ]],
        ]);
    $service->shouldReceive('downloadInvoiceXml')
        ->once()
        ->with('reconcile-missing-uuid')
        ->andReturn([
            'success' => true,
            'xml' => makeInboundInvoiceXml('TD17', '9/INT'),
        ]);
    app()->instance(OpenApiSdiService::class, $service);

    $this->artisan('openapi:reconcile', ['--receive-only' => true])
        ->assertExitCode(0);

    expect(SelfInvoice::withoutGlobalScopes()->count())->toBe(0)
        ->and(PurchaseInvoice::withoutGlobalScopes()->where('type', 'purchase')->count())->toBe(0);
});

test('customer notification NS reopens outbound invoice for correction and resend', function () {
    $invoice = SalesInvoice::factory()->create([
        'status' => InvoiceStatus::Sent,
        'sdi_status' => SdiStatus::Sent,
        'sdi_uuid' => 'outbound-sales-uuid',
    ]);

    $response = $this
        ->withHeader('Authorization', 'Bearer webhook-secret')
        ->postJson(route('openapi.webhook'), [
            'event' => 'customer-notification',
            'data' => [
                'notification' => [
                    'invoice_uuid' => 'outbound-sales-uuid',
                    'type' => 'NS',
                ],
            ],
        ]);

    $response->assertOk()->assertJson([
        'status' => 'ok',
        'message' => null,
    ]);

    $invoice->refresh();

    expect($invoice->status)->toBe(InvoiceStatus::Draft->value)
        ->and($invoice->sdi_status)->toBe(SdiStatus::Rejected->value)
        ->and($invoice->isSdiEditable())->toBeTrue();
});

function supplierInvoiceWebhookPayload(string $uuid): array
{
    return [
        'event' => 'supplier-invoice',
        'data' => [
            'invoice' => [
                'uuid' => $uuid,
            ],
        ],
    ];
}

function makeInboundInvoiceXml(string $documentType, string $number, string $customerVat = '12345678903'): string
{
    return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<FatturaElettronica versione="FPR12">
  <FatturaElettronicaHeader>
    <DatiTrasmissione>
      <IdTrasmittente>
        <IdPaese>IT</IdPaese>
        <IdCodice>12345678901</IdCodice>
      </IdTrasmittente>
      <ProgressivoInvio>00001</ProgressivoInvio>
      <FormatoTrasmissione>FPR12</FormatoTrasmissione>
      <CodiceDestinatario>JKKZDGR</CodiceDestinatario>
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
          <IdCodice>{$customerVat}</IdCodice>
        </IdFiscaleIVA>
        <CodiceFiscale>{$customerVat}</CodiceFiscale>
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
        <TipoDocumento>{$documentType}</TipoDocumento>
        <Divisa>EUR</Divisa>
        <Data>2026-06-01</Data>
        <Numero>{$number}</Numero>
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
</FatturaElettronica>
XML;
}
