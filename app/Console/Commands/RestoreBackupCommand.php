<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use ZipArchive;

class RestoreBackupCommand extends Command
{
    protected $signature = 'app:restore-backup
        {--file= : Path to a local Spatie ZIP backup}
        {--s3-key= : Object key on the configured s3 disk}
        {--s3-key-is-full-path : Do not prepend the configured backup name to --s3-key}
        {--database-type-file= : Write the restored database type to this file after success}
        {--dry-run : Validate backup contents without restoring}
        {--force : Execute restore}
        {--no-storage : Restore database only}
        {--backup-current : Snapshot current db/storage before restore}';

    protected $description = 'Restore the configured database and storage from a Spatie backup ZIP (local file or S3 key)';

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

            $database = $this->findDatabaseEntry($zip);
            if ($database === null) {
                $zip->close();
                $this->error('Database entry not found or ambiguous (expected one database.sqlite or one db-dumps/*.sql[.gz]).');

                return self::FAILURE;
            }

            $storageEntries = $restoreStorage ? $this->findStorageEntries($zip) : [];
            if ($restoreStorage && count($storageEntries) === 0) {
                $zip->close();
                $this->error('Storage files not found in archive. Use --no-storage if intentional.');

                return self::FAILURE;
            }

            $this->info('Backup archive validated.');
            $this->line('Database: '.$database['entry'].' ('.$database['type'].')');
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

            $this->restoreDatabase($zip, $database, $workspace);
            if ($restoreStorage) {
                $this->restoreStorage($zip, $storageEntries, $workspace);
            }

            $zip->close();

            $this->writeDatabaseTypeFile($database['type']);
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
        $prepend = $this->option('s3-key-is-full-path') ? '' : (config('backup.name') ? config('backup.name').'/' : '');
        $stream = Storage::disk('s3')->readStream($prepend.$sourceS3Key);
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

    private function findDatabaseEntry(ZipArchive $zip): ?array
    {
        $sqliteEntries = [];
        $sqlEntries = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if ($name === false) {
                continue;
            }

            if (preg_match('#(^|/)database\.sqlite$#', $name) === 1) {
                $sqliteEntries[] = $name;
            }
            if (preg_match('#(^|/)db-dumps/.*\.sql(\.gz)?$#', $name) === 1) {
                $sqlEntries[] = $name;
            }
        }

        if (count($sqliteEntries) === 1 && $sqlEntries === []) {
            return ['type' => 'sqlite', 'entry' => $sqliteEntries[0]];
        }
        if (count($sqlEntries) === 1 && $sqliteEntries === []) {
            return [
                'type' => $this->isSqliteDump($zip, $sqlEntries[0]) ? 'sqlite-sql' : 'sql',
                'entry' => $sqlEntries[0],
            ];
        }

        return null;
    }

    private function isSqliteDump(ZipArchive $zip, string $entry): bool
    {
        $contents = $zip->getFromName($entry);
        if ($contents === false) {
            throw new \RuntimeException('Unable to inspect database dump from ZIP.');
        }

        if (str_ends_with($entry, '.gz')) {
            $contents = gzdecode($contents);
            if ($contents === false) {
                throw new \RuntimeException('Unable to decompress database dump from ZIP.');
            }
        }

        $contents = ltrim($contents, "\xEF\xBB\xBF \t\r\n");

        return preg_match('/^PRAGMA\s+foreign_keys\s*=\s*OFF\s*;/i', $contents) === 1;
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

    private function restoreDatabase(ZipArchive $zip, array $database, string $workspace): void
    {
        if ($database['type'] === 'sqlite') {
            $this->restoreSqliteFile($zip, $database['entry'], $workspace);

            return;
        }

        if ($database['type'] === 'sqlite-sql') {
            $this->restoreSqliteDump($zip, $database['entry'], $workspace);

            return;
        }

        if (DB::getDriverName() !== 'pgsql') {
            throw new \RuntimeException('A SQL backup requires the PostgreSQL runtime connection.');
        }

        $this->restorePostgresDatabase($zip, $database['entry'], $workspace);
    }

    private function restoreSqliteFile(ZipArchive $zip, string $dbEntry, string $workspace): void
    {
        $dbPath = '/data/database.sqlite';
        $dbDir = dirname($dbPath);
        File::ensureDirectoryExists($dbDir);

        $contents = $zip->getFromName($dbEntry);
        if ($contents === false) {
            throw new \RuntimeException('Unable to extract SQLite database from ZIP.');
        }

        $tmpDbPath = $dbPath.'.restore-in-progress';
        File::delete($tmpDbPath);
        File::put($tmpDbPath, $contents);

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

    private function restoreSqliteDump(ZipArchive $zip, string $dbEntry, string $workspace): void
    {
        $dbPath = '/data/database.sqlite';
        File::ensureDirectoryExists(dirname($dbPath));

        $dumpPath = $this->extractDatabaseDump($zip, $dbEntry, $workspace);
        $tmpDbPath = $dbPath.'.restore-in-progress';
        File::delete($tmpDbPath);

        $process = new Process(['sqlite3', $tmpDbPath], null, null, File::get($dumpPath));
        $process->setTimeout(300);
        $process->run();

        if (! $process->isSuccessful()) {
            File::delete($tmpDbPath);
            throw new \RuntimeException('SQLite restore failed: '.trim($process->getErrorOutput()));
        }

        $integrityCheck = 'sqlite3 '.escapeshellarg($tmpDbPath).' "PRAGMA integrity_check;"';
        $integrityOutput = trim((string) shell_exec($integrityCheck));
        if (strtolower($integrityOutput) !== 'ok') {
            File::delete($tmpDbPath);
            throw new \RuntimeException('Restored database integrity check failed: '.$integrityOutput);
        }

        if (File::exists($dbPath)) {
            File::copy($dbPath, $dbPath.'.pre-restore');
        }

        File::move($tmpDbPath, $dbPath);
    }

    private function restorePostgresDatabase(ZipArchive $zip, string $dbEntry, string $workspace): void
    {
        $dumpPath = $this->extractDatabaseDump($zip, $dbEntry, $workspace);
        $connection = $this->postgresConnection();
        if (! is_array($connection)) {
            throw new \RuntimeException('PostgreSQL connection is not configured.');
        }

        $this->runPsql($connection, ['-c', 'drop schema public cascade; create schema public;']);
        $this->runPsql($connection, ['-f', $dumpPath]);
    }

    private function extractDatabaseDump(ZipArchive $zip, string $dbEntry, string $workspace): string
    {
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

        return $dumpPath;
    }

    private function runPsql(array $connection, array $arguments): void
    {
        $process = new Process([
            $this->postgresBinary('psql'),
            '-v',
            'ON_ERROR_STOP=1',
            '-h',
            (string) ($connection['host'] ?? '127.0.0.1'),
            '-p',
            (string) ($connection['port'] ?? '5432'),
            '-U',
            (string) ($connection['username'] ?? ''),
            '-d',
            (string) ($connection['database'] ?? ''),
            ...$arguments,
        ], null, ['PGPASSWORD' => (string) ($connection['password'] ?? '')]);
        $process->setTimeout(300);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new \RuntimeException('PostgreSQL restore failed: '.trim($process->getErrorOutput()));
        }
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

        if (DB::getDriverName() === 'sqlite') {
            $dbPath = (string) config('database.connections.sqlite.database');
            if (File::exists($dbPath)) {
                File::copy($dbPath, $snapshotDir.'/database.sqlite');
            }
        } elseif (DB::getDriverName() === 'pgsql') {
            $this->snapshotPostgresDatabase($snapshotDir);
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

    private function snapshotPostgresDatabase(string $snapshotDir): void
    {
        $connection = $this->postgresConnection();
        if (! is_array($connection)) {
            throw new \RuntimeException('PostgreSQL connection is not configured.');
        }

        $process = new Process([
            $this->postgresBinary('pg_dump'),
            '-h',
            (string) ($connection['host'] ?? '127.0.0.1'),
            '-p',
            (string) ($connection['port'] ?? '5432'),
            '-U',
            (string) ($connection['username'] ?? ''),
            '-d',
            (string) ($connection['database'] ?? ''),
            '-f',
            $snapshotDir.'/database.sql',
        ], null, ['PGPASSWORD' => (string) ($connection['password'] ?? '')]);
        $process->setTimeout(300);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new \RuntimeException('Unable to snapshot PostgreSQL database: '.trim($process->getErrorOutput()));
        }
    }

    private function postgresConnection(): ?array
    {
        $connection = config('database.connections.'.config('database.default'));
        if (! is_array($connection)) {
            return null;
        }

        return [
            ...$connection,
            'host' => DB::connection()->getConfig('host'),
            'port' => DB::connection()->getConfig('port'),
            'database' => DB::connection()->getDatabaseName(),
            'username' => DB::connection()->getConfig('username'),
            'password' => DB::connection()->getConfig('password'),
        ];
    }

    private function postgresBinary(string $name): string
    {
        $configured = env('POSTGRES_BIN_PATH') ?: getenv('POSTGRES_BIN_PATH');
        if (is_string($configured) && $configured !== '') {
            return rtrim($configured, '/').'/'.$name;
        }

        return $name;
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

    private function writeDatabaseTypeFile(string $databaseType): void
    {
        $path = $this->option('database-type-file');
        if (! is_string($path) || $path === '') {
            return;
        }

        File::ensureDirectoryExists(dirname($path));
        File::put($path, $databaseType."\n");
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
