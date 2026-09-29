<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Throwable;

class DatabaseMigrationPreflightCommand extends Command
{
    protected $signature = 'database:migration-preflight
                            {--source= : Absolute path to the SQLite source database}
                            {--report= : Absolute JSON report destination}
                            {--require-postgres : Fail unless the default connection is PostgreSQL}';

    protected $description = 'Validate a SQLite source database before a controlled PostgreSQL migration';

    private const REQUIRED_TABLES = [
        'users',
        'cache',
        'cache_locks',
        'jobs',
        'job_batches',
        'failed_jobs',
        'settings',
        'sequences',
        'contacts',
        'fiscal_documents',
        'fiscal_documents_lines',
        'ei_outbound_logs',
        'ei_inbound_logs',
        'sdi_uuid_links',
        'payments',
        'personal_access_tokens',
        'document_events',
        'sdi_outbound_submissions',
        'sessions',
        'migrations',
    ];

    public function handle(): int
    {
        $source = (string) ($this->option('source') ?: config('database.connections.sqlite_migration_source.database'));
        $reportPath = (string) ($this->option('report') ?: storage_path('app/migration/preflight.json'));
        $report = [
            'started_at' => now()->toIso8601String(),
            'source' => $source,
            'checks' => [],
            'success' => false,
        ];

        try {
            $this->assertReadableSource($source);
            config(['database.connections.sqlite_migration_source.database' => $source]);
            DB::purge('sqlite_migration_source');

            $this->check($report, 'sqlite_integrity', $this->sqliteScalar('PRAGMA integrity_check') === 'ok');
            $this->check($report, 'foreign_keys', $this->sqliteScalar('PRAGMA foreign_key_check') === null);
            $this->check($report, 'encoding', $this->sqliteScalar('PRAGMA encoding') === 'UTF-8');
            $this->checkRequiredTables($report);
            $this->check($report, 'source_connection', DB::connection('sqlite_migration_source')->getDriverName() === 'sqlite');

            if ($this->option('require-postgres')) {
                $this->check($report, 'target_connection', DB::getDriverName() === 'pgsql');
                $this->check($report, 'target_connectivity', DB::selectOne('select 1 as connected')->connected === 1);
            }

            $report['success'] = collect($report['checks'])->every(fn (array $check) => $check['passed']);
        } catch (Throwable $exception) {
            $report['checks'][] = ['name' => 'exception', 'passed' => false, 'detail' => $exception->getMessage()];
            $this->error($exception->getMessage());
        } finally {
            $report['finished_at'] = now()->toIso8601String();
            File::ensureDirectoryExists(dirname($reportPath));
            File::put($reportPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            Log::channel(config('logging.default'))->info('Database migration preflight completed', ['report' => $report]);
        }

        if (! $report['success']) {
            $this->error("Preflight failed. Read {$reportPath} before proceeding.");

            return self::FAILURE;
        }

        $this->info("Preflight passed. Report: {$reportPath}");

        return self::SUCCESS;
    }

    private function assertReadableSource(string $source): void
    {
        if ($source === '' || ! File::isFile($source) || ! File::isReadable($source)) {
            throw new \RuntimeException('SQLite migration source is missing or unreadable.');
        }
    }

    private function checkRequiredTables(array &$report): void
    {
        $tables = collect(DB::connection('sqlite_migration_source')->select("select name from sqlite_master where type = 'table'"))
            ->pluck('name')
            ->all();
        $missing = array_values(array_diff(self::REQUIRED_TABLES, $tables));

        $this->check($report, 'required_tables', $missing === [], $missing === [] ? null : 'Missing: '.implode(', ', $missing));
    }

    private function sqliteScalar(string $sql): mixed
    {
        $row = DB::connection('sqlite_migration_source')->selectOne($sql);

        return $row === null ? null : array_values((array) $row)[0];
    }

    private function check(array &$report, string $name, bool $passed, ?string $detail = null): void
    {
        $report['checks'][] = compact('name', 'passed', 'detail');
        $this->{$passed ? 'info' : 'error'}(($passed ? 'PASS' : 'FAIL').": {$name}".($detail ? " ({$detail})" : ''));
    }
}
