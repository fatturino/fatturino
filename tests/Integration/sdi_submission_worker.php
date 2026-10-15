<?php

use App\Contracts\SdiSubmissionDriver;
use App\Models\FiscalDocument;
use App\Sdi\SdiSubmissionCommand;
use App\Sdi\SdiSubmissionResult;
use App\Services\SdiSubmissionDriverRegistry;
use App\Services\SdiSubmissionService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[$documentId, $readyPath, $releasePath] = array_slice($argv, 1);

file_put_contents($readyPath, (string) getmypid());

$deadline = microtime(true) + 10;
while (! file_exists($releasePath)) {
    if (microtime(true) >= $deadline) {
        fwrite(STDERR, "Timed out waiting for the concurrent test barrier.\n");
        exit(2);
    }

    usleep(10_000);
}

final class IntegrationSubmissionDriver implements SdiSubmissionDriver
{
    public function key(): string
    {
        return 'integration';
    }

    public function submit(SdiSubmissionCommand $command): SdiSubmissionResult
    {
        DB::table('sdi_integration_remote_calls')->insert([
            'submission_id' => $command->submissionId,
            'created_at' => now(),
        ]);

        return SdiSubmissionResult::unknown('Integration driver deliberately returns an uncertain outcome.');
    }
}

app()->instance(SdiSubmissionDriverRegistry::class, new SdiSubmissionDriverRegistry([
    new IntegrationSubmissionDriver,
]));
config()->set('sdi.driver', 'integration');

$document = FiscalDocument::withoutGlobalScopes()->findOrFail($documentId);

$result = app(SdiSubmissionService::class)->send(
    $document,
    '<FatturaElettronica><FatturaElettronicaHeader><CedentePrestatore><DatiAnagrafici><IdFiscaleIVA><IdCodice>12345678903</IdCodice></IdFiscaleIVA></DatiAnagrafici></CedentePrestatore><CessionarioCommittente><DatiAnagrafici><IdFiscaleIVA><IdCodice>98765432109</IdCodice></IdFiscaleIVA></DatiAnagrafici></CessionarioCommittente></FatturaElettronicaHeader><FatturaElettronicaBody><DatiGenerali><DatiGeneraliDocumento><TipoDocumento>TD01</TipoDocumento><Numero>CONCURRENT-1</Numero><Data>2026-10-14</Data><ImportoTotaleDocumento>1.00</ImportoTotaleDocumento></DatiGeneraliDocumento></DatiGenerali></FatturaElettronicaBody></FatturaElettronica>',
    'IT12345678903_CONCURRENT.xml',
    'Integration submission',
);

echo json_encode(['success' => $result['success']]);
