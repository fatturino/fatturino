<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Throwable;

class ImportSqliteToPostgresCommand extends Command
{
    protected $signature = 'database:import-sqlite-to-postgres
                            {--source= : Absolute path to the SQLite source database}
                            {--batch-size=500 : Rows per committed target transaction}
                            {--report= : Absolute JSON report destination}
                            {--force : Allow a non-empty target table}';

    protected $description = 'Copy a validated SQLite database into PostgreSQL using idempotent primary-key upserts';

    private const TABLES = [
        'users',
        'password_reset_tokens',
        'sessions',
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
    ];

    private const PRIMARY_KEYS = [
        'password_reset_tokens' => 'email',
        'sessions' => 'id',
        'cache' => 'key',
        'cache_locks' => 'key',
        'job_batches' => 'id',
    ];

    // Application migrations seed configuration defaults in this table.
    // During a SQLite cutover, the source is authoritative and must replace them.
    private const TABLES_WITH_MIGRATION_DEFAULTS = ['settings'];

    public function handle(): int
    {
        $source = (string) ($this->option('source') ?: config('database.connections.sqlite_migration_source.database'));
        $batchSize = max(1, (int) $this->option('batch-size'));
        $reportPath = (string) ($this->option('report') ?: storage_path('app/migration/import.json'));
        $report = ['started_at' => now()->toIso8601String(), 'source' => $source, 'tables' => [], 'success' => false];

        try {
            if (DB::getDriverName() !== 'pgsql') {
                throw new \RuntimeException('The default connection must be PostgreSQL.');
            }
            if (! File::isFile($source) || ! File::isReadable($source)) {
                throw new \RuntimeException('SQLite migration source is missing or unreadable.');
            }

            config(['database.connections.sqlite_migration_source.database' => $source]);
            DB::purge('sqlite_migration_source');

            foreach (self::TABLES as $table) {
                $report['tables'][$table] = $this->copyTable($table, $batchSize);
            }
            $this->realignSequences();
            $report['success'] = true;
        } catch (Throwable $exception) {
            $report['error'] = $exception->getMessage();
            $this->error($exception->getMessage());
        } finally {
            $report['finished_at'] = now()->toIso8601String();
            File::ensureDirectoryExists(dirname($reportPath));
            File::put($reportPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            Log::info('SQLite to PostgreSQL import completed', ['report' => $report]);
        }

        return $report['success'] ? self::SUCCESS : self::FAILURE;
    }

    private function copyTable(string $table, int $batchSize): array
    {
        $source = DB::connection('sqlite_migration_source');
        $primaryKey = self::PRIMARY_KEYS[$table] ?? 'id';
        $columns = $this->sourceColumns($table);
        $targetTypes = $this->targetColumnTypes($table);
        $nullableTemporalColumns = $this->nullableTemporalColumns($table);
        $unsupported = array_values(array_diff($columns, array_keys($targetTypes)));
        if ($unsupported !== []) {
            throw new \RuntimeException("Schema drift in {$table}; target is missing: ".implode(', ', $unsupported));
        }

        $sourceCount = (int) $source->table($table)->count();
        $targetCount = (int) DB::table($table)->count();
        if (in_array($table, self::TABLES_WITH_MIGRATION_DEFAULTS, true)) {
            $cleared = $this->replaceMigrationDefaults($table, $targetCount);
            if ($cleared > 0) {
                $this->info("Cleared {$cleared} migration default rows from {$table}; SQLite is authoritative.");
            }
        }
        if ($targetCount > 0 && ! $this->option('force') && ! in_array($table, self::TABLES_WITH_MIGRATION_DEFAULTS, true)) {
            throw new \RuntimeException("Target table {$table} is not empty; review and rerun with --force if resuming is intended.");
        }

        $copied = 0;
        $lastKey = null;
        do {
            $query = $source->table($table)->orderBy($primaryKey)->limit($batchSize);
            if ($lastKey !== null) {
                $query->where($primaryKey, '>', $lastKey);
            }
            $rows = $query->get()->map(fn (object $row) => $this->normalizeRow((array) $row, $targetTypes, $nullableTemporalColumns))->all();
            if ($rows === []) {
                break;
            }

            DB::transaction(function () use ($table, $rows, $primaryKey, &$copied): void {
                foreach ($rows as $row) {
                    DB::table($table)->updateOrInsert([$primaryKey => $row[$primaryKey]], $row);
                    $copied++;
                }
            });
            $lastKey = $rows[array_key_last($rows)][$primaryKey];
        } while (count($rows) === $batchSize);

        $this->info("Imported {$copied}/{$sourceCount} rows from {$table}");

        return compact('sourceCount', 'targetCount', 'copied');
    }

    private function replaceMigrationDefaults(string $table, int $targetCount): int
    {
        if ($targetCount === 0) {
            return 0;
        }

        DB::transaction(function () use ($table): void {
            DB::table($table)->delete();
        });

        return $targetCount;
    }

    private function sourceColumns(string $table): array
    {
        return collect(DB::connection('sqlite_migration_source')->select("pragma table_info('{$table}')"))->pluck('name')->all();
    }

    private function targetColumnTypes(string $table): array
    {
        return collect(DB::select('select column_name, data_type from information_schema.columns where table_schema = current_schema() and table_name = ?', [$table]))
            ->mapWithKeys(fn (object $column) => [$column->column_name => $column->data_type])
            ->all();
    }

    private function nullableTemporalColumns(string $table): array
    {
        return collect(DB::select("select column_name from information_schema.columns where table_schema = current_schema() and table_name = ? and is_nullable = 'YES' and data_type in ('date', 'timestamp with time zone', 'timestamp without time zone')", [$table]))
            ->pluck('column_name')
            ->all();
    }

    private function normalizeRow(array $row, array $targetTypes, array $nullableTemporalColumns): array
    {
        foreach ($row as $column => $value) {
            if ($value === null) {
                continue;
            }

            $type = $targetTypes[$column] ?? null;
            if ($value === '' && in_array($column, $nullableTemporalColumns, true)) {
                $row[$column] = null;

                continue;
            }
            if ($type === 'boolean') {
                $row[$column] = in_array($value, [true, 1, '1', 't', 'true'], true);
            }
            if ($type === 'json' || $type === 'jsonb') {
                json_decode((string) $value, true, 512, JSON_THROW_ON_ERROR);
            }
        }

        return $row;
    }

    private function realignSequences(): void
    {
        foreach (self::TABLES as $table) {
            if (! $this->hasIntegerId($table)) {
                continue;
            }
            DB::statement("select setval(pg_get_serial_sequence('{$table}', 'id'), coalesce((select max(id) from \"{$table}\"), 1), true)");
        }
    }

    private function hasIntegerId(string $table): bool
    {
        return in_array($this->targetColumnTypes($table)['id'] ?? null, ['smallint', 'integer', 'bigint'], true);
    }
}
