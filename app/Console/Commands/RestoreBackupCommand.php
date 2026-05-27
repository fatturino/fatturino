<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class RestoreBackupCommand extends Command
{
    protected $signature = 'app:restore-backup
        {--file= : Path to a local Spatie ZIP backup}
        {--s3-key= : Object key on the configured s3 disk}
        {--dry-run : Validate backup contents without restoring}
        {--force : Execute restore}
        {--no-storage : Restore database only}
        {--backup-current : Snapshot current db/storage before restore}';

    protected $description = 'Restore SQLite database and storage from a Spatie backup ZIP (local file or S3 key)';

    public function handle(): int
    {
        $isDryRun = (bool) $this->option('dry-run');
        $restoreStorage = ! (bool) $this->option('no-storage');
        $sourceFile = $this->option('file');
        $sourceS3Key = $this->option('s3-key');

        if (($sourceFile && $sourceS3Key) || (! $sourceFile && ! $sourceS3Key)) {
            $this->error('Provide exactly one source: --file or --s3-key.');

            return self::FAILURE;
        }

        if (! $isDryRun && ! (bool) $this->option('force')) {
            $this->error('Refusing to restore without --force. Use --dry-run to validate safely.');

            return self::FAILURE;
        }

        $workspace = storage_path('app/restore-temp/'.now()->format('YmdHis'));
        File::ensureDirectoryExists($workspace);

        try {
            $zipPath = $this->prepareZip($workspace, $sourceFile, $sourceS3Key);
            $zip = new ZipArchive;
            if ($zip->open($zipPath) !== true) {
                $this->error('Unable to open ZIP archive.');

                return self::FAILURE;
            }

            $dbEntry = $this->findDatabaseDumpEntry($zip);
            if ($dbEntry === null) {
                $zip->close();
                $this->error('Database dump not found in archive (expected db-dumps/*.sql or *.sql.gz).');

                return self::FAILURE;
            }

            $storageEntries = $restoreStorage ? $this->findStorageEntries($zip) : [];
            if ($restoreStorage && count($storageEntries) === 0) {
                $zip->close();
                $this->error('Storage files not found in archive. Use --no-storage if intentional.');

                return self::FAILURE;
            }

            $this->info('Backup archive validated.');
            $this->line('DB dump: '.$dbEntry);
            if ($restoreStorage) {
                $this->line('Storage entries: '.count($storageEntries));
            }

            if ($isDryRun) {
                $zip->close();

                return self::SUCCESS;
            }

            $this->enterMaintenanceMode();

            if ((bool) $this->option('backup-current')) {
                $this->snapshotCurrentState($workspace.'/snapshot-current');
            }

            $this->restoreDatabase($zip, $dbEntry, $workspace);
            if ($restoreStorage) {
                $this->restoreStorage($zip, $storageEntries, $workspace);
            }

            $zip->close();

            Artisan::call('optimize:clear');
            $this->leaveMaintenanceMode();

            $this->info('Restore completed successfully.');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->leaveMaintenanceMode();
            $this->error('Restore failed: '.$e->getMessage());

            return self::FAILURE;
        } finally {
            File::deleteDirectory($workspace);
        }
    }

    private function prepareZip(string $workspace, ?string $sourceFile, ?string $sourceS3Key): string
    {
        if ($sourceFile) {
            if (! File::exists($sourceFile)) {
                throw new \RuntimeException('Local file not found: '.$sourceFile);
            }

            return $sourceFile;
        }

        $local = $workspace.'/backup.zip';
        $stream = Storage::disk('s3')->readStream($sourceS3Key);
        if ($stream === false) {
            throw new \RuntimeException('Unable to download backup from s3 key: '.$sourceS3Key);
        }

        $target = fopen($local, 'wb');
        if ($target === false) {
            throw new \RuntimeException('Unable to create temporary backup file.');
        }

        stream_copy_to_stream($stream, $target);
        fclose($stream);
        fclose($target);

        return $local;
    }

    private function findDatabaseDumpEntry(ZipArchive $zip): ?string
    {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if ($name === false) {
                continue;
            }

            if (preg_match('#(^|/)db-dumps/.*\.sql(\.gz)?$#', $name) === 1) {
                return $name;
            }
        }

        return null;
    }

    private function findStorageEntries(ZipArchive $zip): array
    {
        $matched = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if ($name === false || str_ends_with($name, '/')) {
                continue;
            }

            if ($this->mapStorageDestination($name) !== null) {
                $matched[] = $name;
            }
        }

        return $matched;
    }

    private function restoreDatabase(ZipArchive $zip, string $dbEntry, string $workspace): void
    {
        $dbPath = (string) config('database.connections.sqlite.database');
        if ($dbPath === '') {
            throw new \RuntimeException('SQLite database path is not configured.');
        }

        $dbDir = dirname($dbPath);
        File::ensureDirectoryExists($dbDir);

        $dumpPath = $workspace.'/database.sql';
        $rawDumpPath = $workspace.'/database.dump';

        $contents = $zip->getFromName($dbEntry);
        if ($contents === false) {
            throw new \RuntimeException('Unable to extract database dump from ZIP.');
        }

        File::put($rawDumpPath, $contents);

        if (str_ends_with($dbEntry, '.gz')) {
            $this->decompressGzip($rawDumpPath, $dumpPath);
        } else {
            File::move($rawDumpPath, $dumpPath);
        }

        $tmpDbPath = $workspace.'/database-restored.sqlite';
        File::put($tmpDbPath, '');

        $command = 'sqlite3 '.escapeshellarg($tmpDbPath).' < '.escapeshellarg($dumpPath);
        exec($command, $output, $exitCode);
        if ($exitCode !== 0) {
            throw new \RuntimeException('SQLite import failed.');
        }

        $integrityCheck = 'sqlite3 '.escapeshellarg($tmpDbPath).' "PRAGMA integrity_check;"';
        $integrityOutput = trim((string) shell_exec($integrityCheck));
        if (strtolower($integrityOutput) !== 'ok') {
            throw new \RuntimeException('Restored database integrity check failed: '.$integrityOutput);
        }

        $backupDbPath = $dbPath.'.pre-restore';
        if (File::exists($dbPath)) {
            File::copy($dbPath, $backupDbPath);
        }

        File::move($tmpDbPath, $dbPath);
    }

    private function restoreStorage(ZipArchive $zip, array $storageEntries, string $workspace): void
    {
        foreach ($storageEntries as $entry) {
            $destination = $this->mapStorageDestination($entry);
            if ($destination === null) {
                continue;
            }

            $content = $zip->getFromName($entry);
            if ($content === false) {
                throw new \RuntimeException('Unable to extract storage entry: '.$entry);
            }

            File::ensureDirectoryExists(dirname($destination));
            File::put($destination, $content);
        }
    }

    private function mapStorageDestination(string $entry): ?string
    {
        $markers = [
            'storage/app/private/documents/' => storage_path('app/private/documents/'),
            'storage/app/public/' => storage_path('app/public/'),
        ];

        foreach ($markers as $marker => $targetBase) {
            $pos = strpos($entry, $marker);
            if ($pos === false) {
                continue;
            }

            $relative = substr($entry, $pos + strlen($marker));
            if ($relative === false || $relative === '') {
                return null;
            }

            return $targetBase.$relative;
        }

        return null;
    }

    private function snapshotCurrentState(string $snapshotDir): void
    {
        File::ensureDirectoryExists($snapshotDir);

        $dbPath = (string) config('database.connections.sqlite.database');
        if (File::exists($dbPath)) {
            File::copy($dbPath, $snapshotDir.'/database.sqlite');
        }

        $documents = storage_path('app/private/documents');
        if (File::isDirectory($documents)) {
            File::copyDirectory($documents, $snapshotDir.'/documents');
        }

        $public = storage_path('app/public');
        if (File::isDirectory($public)) {
            File::copyDirectory($public, $snapshotDir.'/public');
        }

        $this->line('Snapshot created at: '.$snapshotDir);
    }

    private function decompressGzip(string $source, string $destination): void
    {
        $in = gzopen($source, 'rb');
        if ($in === false) {
            throw new \RuntimeException('Unable to open compressed db dump.');
        }

        $out = fopen($destination, 'wb');
        if ($out === false) {
            gzclose($in);
            throw new \RuntimeException('Unable to create SQL dump file.');
        }

        while (! gzeof($in)) {
            $chunk = gzread($in, 8192);
            if ($chunk === false) {
                fclose($out);
                gzclose($in);
                throw new \RuntimeException('Failed while decompressing db dump.');
            }
            fwrite($out, $chunk);
        }

        fclose($out);
        gzclose($in);
    }

    private function enterMaintenanceMode(): void
    {
        Artisan::call('down');
    }

    private function leaveMaintenanceMode(): void
    {
        Artisan::call('up');
    }
}
