<?php

namespace App\Console\Commands;

use App\Models\FiscalDocument;
use App\Services\DocumentStorageService;
use Illuminate\Console\Command;
use Throwable;

class BackfillDocumentStorageCommand extends Command
{
    protected $signature = 'documents:backfill-storage {--dry-run : Report legacy snapshots without copying them} {--limit= : Maximum documents to process}';

    protected $description = 'Copy existing local XML and PDF snapshots to the configured document disk safely';

    public function handle(DocumentStorageService $storage): int
    {
        $query = FiscalDocument::withoutGlobalScopes()
            ->where(function ($query) {
                $query->whereNotNull('xml_path')->orWhereNotNull('pdf_path');
            })
            ->orderBy('id');

        if ($this->option('limit')) {
            $query->limit((int) $this->option('limit'));
        }

        $stats = ['documents' => 0, 'snapshots' => 0, 'errors' => 0];
        foreach ($query->cursor() as $document) {
            $stats['documents']++;
            foreach (array_filter([$document->xml_path, $document->pdf_path]) as $path) {
                try {
                    if ($this->option('dry-run')) {
                        $this->line("Would copy: {$path}");
                    } else {
                        $storage->migrateFromLocal($path);
                        $this->line("Copied: {$path}");
                    }
                    $stats['snapshots']++;
                } catch (Throwable $exception) {
                    $stats['errors']++;
                    $this->error("Failed {$path}: {$exception->getMessage()}");
                }
            }
        }

        $this->table(['Documents', 'Snapshots', 'Errors'], [[$stats['documents'], $stats['snapshots'], $stats['errors']]]);

        return $stats['errors'] === 0 ? self::SUCCESS : self::FAILURE;
    }
}
