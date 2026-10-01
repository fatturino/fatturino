<?php

namespace App\Console\Commands;

use App\Models\FiscalDocument;
use App\Services\CreditNoteXmlService;
use App\Services\DocumentStorageService;
use App\Services\InvoiceXmlService;
use App\Services\SelfInvoiceXmlService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

class BackfillDocumentStorageCommand extends Command
{
    protected $signature = 'documents:backfill-storage {--dry-run : Report document snapshots without changing storage or the database} {--limit= : Maximum documents to process}';

    protected $description = 'Copy local snapshots to S3 and regenerate missing outbound XML snapshots safely';

    public function handle(
        DocumentStorageService $storage,
        InvoiceXmlService $invoiceXmlService,
        CreditNoteXmlService $creditNoteXmlService,
        SelfInvoiceXmlService $selfInvoiceXmlService,
    ): int {
        if (config('filesystems.disks.documents.driver') !== 's3') {
            $this->error('Document storage must use the S3 driver before running this command.');

            return self::FAILURE;
        }

        $query = FiscalDocument::withoutGlobalScopes()->orderBy('id');

        if ($this->option('limit')) {
            $query->limit((int) $this->option('limit'));
        }

        $stats = ['documents' => 0, 'copied' => 0, 'regenerated_xml' => 0, 'skipped' => 0, 'errors' => 0];
        foreach ($query->cursor() as $document) {
            $stats['documents']++;
            try {
                if ($document->xml_path && Storage::disk('local')->exists($document->xml_path)) {
                    $this->migrateSnapshot($storage, $document->xml_path, $stats);
                }

                if ($document->pdf_path) {
                    $path = $document->pdf_path;
                    $this->migrateSnapshot($storage, $path, $stats);
                }

                $this->backfillMissingOutboundXml(
                    $document,
                    $storage,
                    $invoiceXmlService,
                    $creditNoteXmlService,
                    $selfInvoiceXmlService,
                    $stats,
                );
            } catch (Throwable $exception) {
                $stats['errors']++;
                $this->error("Failed document {$document->id}: {$exception->getMessage()}");
            }
        }

        $this->table(
            ['Documents', 'Copied', 'Regenerated XML', 'Skipped', 'Errors'],
            [[$stats['documents'], $stats['copied'], $stats['regenerated_xml'], $stats['skipped'], $stats['errors']]],
        );

        return $stats['errors'] === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function migrateSnapshot(DocumentStorageService $storage, string $path, array &$stats): void
    {
        if ($storage->exists($path)) {
            $stats['skipped']++;
            $this->line("Already stored: {$path}");

            return;
        }

        if ($this->option('dry-run')) {
            $stats['copied']++;
            $this->line("Would copy: {$path}");

            return;
        }

        $storage->migrateFromLocal($path);
        $stats['copied']++;
        $this->line("Copied: {$path}");
    }

    private function backfillMissingOutboundXml(
        FiscalDocument $document,
        DocumentStorageService $storage,
        InvoiceXmlService $invoiceXmlService,
        CreditNoteXmlService $creditNoteXmlService,
        SelfInvoiceXmlService $selfInvoiceXmlService,
        array &$stats,
    ): void {
        if ($document->xml_path && $storage->exists($document->xml_path)) {
            return;
        }

        [$xmlService, $category] = match ($document->type) {
            'sales' => [$invoiceXmlService, 'sales'],
            'credit_note' => [$creditNoteXmlService, 'credit-notes'],
            'self_invoice' => [$selfInvoiceXmlService, 'self-invoices'],
            default => [null, null],
        };

        if ($xmlService === null) {
            return;
        }

        $filename = $xmlService->generateFileName($document);
        $path = sprintf(
            'documents/xml/%s/document-%s/%d/%s',
            $category,
            $document->public_id,
            $document->date?->year ?? $document->fiscal_year,
            $filename,
        );

        if ($this->option('dry-run')) {
            $stats['regenerated_xml']++;
            $this->line("Would regenerate XML: {$path}");

            return;
        }

        $document->loadMissing(['contact', 'lines']);
        $xml = $xmlService->generate($document);
        $path = $storage->storeXml(
            $xml,
            $category.'/document-'.$document->public_id,
            (int) ($document->date?->year ?? $document->fiscal_year),
            $filename,
        );
        $document->update(['xml_path' => $path]);

        $stats['regenerated_xml']++;
        $this->line("Regenerated XML: {$path}");
    }
}
