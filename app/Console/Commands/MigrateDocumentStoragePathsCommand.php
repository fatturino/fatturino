<?php

namespace App\Console\Commands;

use App\Models\FiscalDocument;
use App\Services\DocumentStorageService;
use Illuminate\Console\Command;
use Throwable;

class MigrateDocumentStoragePathsCommand extends Command
{
    protected $signature = 'documents:migrate-s3-paths
        {--dry-run : Report legacy S3 snapshots without changing storage or the database}
        {--limit= : Maximum documents to process}';

    protected $description = 'Move S3 document snapshots to the canonical format/category/year/public-id/filename layout';

    public function handle(DocumentStorageService $storage): int
    {
        if (config('filesystems.disks.documents.driver') !== 's3') {
            $this->error('Document storage must use the S3 driver before running this command.');

            return self::FAILURE;
        }

        $query = FiscalDocument::withoutGlobalScopes()->orderBy('id');
        if ($this->option('limit')) {
            $query->limit((int) $this->option('limit'));
        }

        $stats = ['documents' => 0, 'migrated' => 0, 'cleaned' => 0, 'skipped' => 0, 'errors' => 0];
        foreach ($query->cursor() as $document) {
            $stats['documents']++;

            foreach (['xml_path' => 'xml', 'pdf_path' => 'pdf'] as $attribute => $format) {
                if (! $document->{$attribute}) {
                    continue;
                }

                try {
                    $this->migrateSnapshot($document, $attribute, $format, $storage, $stats);
                } catch (Throwable $exception) {
                    $stats['errors']++;
                    $this->error("Failed document {$document->id} {$attribute}: {$exception->getMessage()}");
                }
            }
        }

        $this->table(
            ['Documents', 'Migrated', 'Legacy cleaned', 'Skipped', 'Errors'],
            [[$stats['documents'], $stats['migrated'], $stats['cleaned'], $stats['skipped'], $stats['errors']]],
        );

        return $stats['errors'] === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function migrateSnapshot(FiscalDocument $document, string $attribute, string $format, DocumentStorageService $storage, array &$stats): void
    {
        $source = $document->{$attribute};
        $target = $this->targetPath($document, $format, basename($source), $storage);

        if ($source === $target) {
            $this->cleanupLegacyCopy($document, $format, $target, $storage, $stats);

            return;
        }

        if (! $storage->exists($source)) {
            if ($storage->exists($target)) {
                if ($this->option('dry-run')) {
                    $this->line("Would recover database path: {$source} -> {$target}");
                } else {
                    $document->update([$attribute => $target]);
                    $this->line("Recovered database path: {$source} -> {$target}");
                }
                $stats['migrated']++;

                return;
            }

            throw new \RuntimeException("Source snapshot is missing: {$source}");
        }

        if ($this->option('dry-run')) {
            $this->line("Would move: {$source} -> {$target}");
            $stats['migrated']++;

            return;
        }

        if ($storage->exists($target)) {
            if (! $storage->hasSameContents($source, $target)) {
                throw new \RuntimeException("Canonical destination contains different content: {$target}");
            }
        } else {
            $storage->copy($source, $target);
            if (! $storage->hasSameContents($source, $target)) {
                throw new \RuntimeException("Copied snapshot integrity check failed: {$target}");
            }
        }

        $document->update([$attribute => $target]);
        $storage->delete($source);

        $stats['migrated']++;
        $this->line("Moved: {$source} -> {$target}");
    }

    private function cleanupLegacyCopy(FiscalDocument $document, string $format, string $target, DocumentStorageService $storage, array &$stats): void
    {
        $legacy = $this->legacyPath($document, $format, basename($target), $storage);
        if ($legacy === $target || ! $storage->exists($legacy)) {
            $stats['skipped']++;

            return;
        }

        if ($this->option('dry-run')) {
            $this->line("Would clean legacy copy: {$legacy}");
            $stats['cleaned']++;

            return;
        }

        if (! $storage->hasSameContents($legacy, $target)) {
            throw new \RuntimeException("Legacy copy contains different content: {$legacy}");
        }

        $storage->delete($legacy);
        $stats['cleaned']++;
        $this->line("Cleaned legacy copy: {$legacy}");
    }

    private function targetPath(FiscalDocument $document, string $format, string $filename, DocumentStorageService $storage): string
    {
        return $storage->snapshotPath($format, $this->category($document), $this->year($document), $document->public_id, $filename);
    }

    private function legacyPath(FiscalDocument $document, string $format, string $filename, DocumentStorageService $storage): string
    {
        return $storage->snapshotPath($format, $this->category($document).'/document-'.$document->public_id, $this->year($document), '', $filename);
    }

    private function category(FiscalDocument $document): string
    {
        return match ($document->type) {
            'purchase' => 'purchase',
            'credit_note' => 'credit-notes',
            'self_invoice' => 'self-invoices',
            default => 'sales',
        };
    }

    private function year(FiscalDocument $document): int
    {
        return (int) ($document->date?->year ?? $document->fiscal_year);
    }
}
