<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

class CheckDocumentStorageCommand extends Command
{
    protected $signature = 'documents:check-storage {--write : Verify write, read and delete permissions with a temporary object}';

    protected $description = 'Verify the configured fiscal document storage without changing application data';

    public function handle(): int
    {
        $diskName = 'documents';
        $disk = Storage::disk($diskName);

        try {
            if (! $this->option('write')) {
                $this->info("Document storage disk [{$diskName}] is configured.");

                return self::SUCCESS;
            }

            $path = 'healthchecks/'.bin2hex(random_bytes(16)).'.txt';
            $contents = 'fatturino-document-storage-check';
            $disk->put($path, $contents);

            if (! $disk->exists($path) || $disk->get($path) !== $contents) {
                $this->error('Document storage read-after-write verification failed.');

                return self::FAILURE;
            }

            $disk->delete($path);
            $this->info('Document storage write/read/delete verification succeeded.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Document storage verification failed: '.$exception->getMessage());

            return self::FAILURE;
        }
    }
}
