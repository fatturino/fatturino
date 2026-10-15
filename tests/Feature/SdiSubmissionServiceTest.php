<?php

use App\Contracts\SdiSubmissionDriver;
use App\Enums\InvoiceStatus;
use App\Models\FiscalDocument;
use App\Sdi\SdiSubmissionCommand;
use App\Sdi\SdiSubmissionResult;
use App\Services\SdiSubmissionDriverRegistry;
use App\Services\SdiSubmissionService;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

test('a unique submission lock collision is reported without invoking the driver', function () {
    $document = FiscalDocument::factory()->create(['status' => InvoiceStatus::XmlValidated]);
    app()->instance(SdiSubmissionDriverRegistry::class, new SdiSubmissionDriverRegistry([
        new NeverCalledSubmissionDriver,
    ]));
    config()->set('sdi.driver', 'never-called');

    DB::shouldReceive('transaction')
        ->once()
        ->andThrow(new UniqueConstraintViolationException('pgsql', 'insert into sdi_outbound_submissions', [], new RuntimeException('duplicate')));

    $result = app(SdiSubmissionService::class)->send($document, sdiSubmissionXml(), 'IT123.xml', 'Sent');

    expect($result['success'])->toBeFalse()
        ->and($result['error_message'])->toContain('Esiste già un invio SDI');
});

test('a non-unique database failure is not reported as a submission collision', function () {
    $document = FiscalDocument::factory()->create(['status' => InvoiceStatus::XmlValidated]);
    app()->instance(SdiSubmissionDriverRegistry::class, new SdiSubmissionDriverRegistry([
        new NeverCalledSubmissionDriver,
    ]));
    config()->set('sdi.driver', 'never-called');

    $exception = new QueryException('pgsql', 'insert into sdi_outbound_submissions', [], new RuntimeException('database unavailable'));
    DB::shouldReceive('transaction')->once()->andThrow($exception);

    app(SdiSubmissionService::class)->send($document, sdiSubmissionXml(), 'IT123.xml', 'Sent');
})->throws(QueryException::class, 'database unavailable');

final class NeverCalledSubmissionDriver implements SdiSubmissionDriver
{
    public function key(): string
    {
        return 'never-called';
    }

    public function submit(SdiSubmissionCommand $command): SdiSubmissionResult
    {
        throw new RuntimeException('The submission driver must not be invoked.');
    }
}

function sdiSubmissionXml(): string
{
    return '<FatturaElettronica><FatturaElettronicaHeader><CedentePrestatore><DatiAnagrafici><IdFiscaleIVA><IdCodice>12345678903</IdCodice></IdFiscaleIVA></DatiAnagrafici></CedentePrestatore><CessionarioCommittente><DatiAnagrafici><IdFiscaleIVA><IdCodice>98765432109</IdCodice></IdFiscaleIVA></DatiAnagrafici></CessionarioCommittente></FatturaElettronicaHeader><FatturaElettronicaBody><DatiGenerali><DatiGeneraliDocumento><TipoDocumento>TD01</TipoDocumento><Numero>SUBMISSION-1</Numero><Data>2026-10-14</Data><ImportoTotaleDocumento>1.00</ImportoTotaleDocumento></DatiGeneraliDocumento></DatiGenerali></FatturaElettronicaBody></FatturaElettronica>';
}
