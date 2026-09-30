<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Throwable;

class VerifySqlitePostgresMigrationCommand extends Command
{
    protected $signature = 'database:verify-sqlite-postgres
                            {--source= : Absolute path to the SQLite source database}
                            {--report= : Absolute JSON report destination}';

    protected $description = 'Compare SQLite source and PostgreSQL target counts, canonical hashes, references and sequences';

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

    public function handle(): int
    {
        $source = (string) ($this->option('source') ?: config('database.connections.sqlite_migration_source.database'));
        $reportPath = (string) ($this->option('report') ?: storage_path('app/migration/verification.json'));
        $report = ['started_at' => now()->toIso8601String(), 'source' => $source, 'checks' => [], 'success' => false];

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
                $this->compareTable($report, $table);
            }
            $this->checkForeignKeys($report);
            $this->checkSequences($report);
            $report['success'] = collect($report['checks'])->every(fn (array $check) => $check['passed']);
        } catch (Throwable $exception) {
            $report['checks'][] = ['name' => 'exception', 'passed' => false, 'detail' => $exception->getMessage()];
            $this->error($exception->getMessage());
        } finally {
            $report['finished_at'] = now()->toIso8601String();
            File::ensureDirectoryExists(dirname($reportPath));
            File::put($reportPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            Log::info('SQLite to PostgreSQL verification completed', ['report' => $report]);
        }

        return $report['success'] ? self::SUCCESS : self::FAILURE;
    }

    private function compareTable(array &$report, string $table): void
    {
        $source = DB::connection('sqlite_migration_source');
        $primaryKey = self::PRIMARY_KEYS[$table] ?? 'id';
        $sourceCount = (int) $source->table($table)->count();
        $targetCount = (int) DB::table($table)->count();
        $this->check($report, "{$table}.count", $sourceCount === $targetCount, "source={$sourceCount}; target={$targetCount}");

        $types = $this->targetColumnTypes($table);
        $nullableTemporalColumns = $this->nullableTemporalColumns($table);
        $sourceHash = $this->canonicalHash($source, $table, $primaryKey, $types, $nullableTemporalColumns);
        $targetHash = $this->canonicalHash(DB::connection(), $table, $primaryKey, $types, $nullableTemporalColumns);
        $hashesMatch = hash_equals($sourceHash, $targetHash);
        $diagnostic = $hashesMatch
            ? null
            : $this->firstRowDifference($source, $table, $primaryKey, $types, $nullableTemporalColumns);
        $this->check(
            $report,
            "{$table}.sha256",
            $hashesMatch,
            "source={$sourceHash}; target={$targetHash}",
            $diagnostic,
        );
    }

    private function canonicalHash($connection, string $table, string $primaryKey, array $types, array $nullableTemporalColumns): string
    {
        $hash = hash_init('sha256');
        $connection->table($table)->orderBy($primaryKey)->chunk(500, function ($rows) use ($hash, $types, $nullableTemporalColumns): void {
            foreach ($rows as $row) {
                $values = (array) $row;
                ksort($values);
                foreach ($values as $key => $value) {
                    $value = $this->normalizeValue($value, $types[$key] ?? null, in_array($key, $nullableTemporalColumns, true));
                    hash_update($hash, $key.'='.str_replace(['\\', "\n", "\r", "\0"], ['\\\\', '\\n', '\\r', '\\0'], (string) $value)."\n");
                }
                hash_update($hash, "--row--\n");
            }
        }, $primaryKey);

        return hash_final($hash);
    }

    private function firstRowDifference($source, string $table, string $primaryKey, array $types, array $nullableTemporalColumns): ?array
    {
        foreach ($source->table($table)->orderBy($primaryKey)->cursor() as $sourceRow) {
            $sourceValues = $this->normalizeRow((array) $sourceRow, $types, $nullableTemporalColumns);
            $targetRow = DB::table($table)->where($primaryKey, $sourceValues[$primaryKey])->first();

            if ($targetRow === null) {
                return [
                    'primary_key' => $sourceValues[$primaryKey],
                    'missing' => 'target',
                ];
            }

            $targetValues = $this->normalizeRow((array) $targetRow, $types, $nullableTemporalColumns);
            $columns = [];

            foreach ($sourceValues as $column => $sourceValue) {
                $targetValue = $targetValues[$column] ?? null;
                if ($sourceValue === $targetValue) {
                    continue;
                }

                $columns[$column] = [
                    'source' => $this->diagnosticValue($sourceValue),
                    'target' => $this->diagnosticValue($targetValue),
                ];
            }

            if ($columns !== []) {
                return [
                    'primary_key' => $sourceValues[$primaryKey],
                    'columns' => $columns,
                ];
            }
        }

        return null;
    }

    private function normalizeRow(array $row, array $types, array $nullableTemporalColumns): array
    {
        ksort($row);

        foreach ($row as $column => $value) {
            $row[$column] = $this->normalizeValue(
                $value,
                $types[$column] ?? null,
                in_array($column, $nullableTemporalColumns, true),
            );
        }

        return $row;
    }

    private function diagnosticValue(mixed $value): string
    {
        return mb_strimwidth((string) $value, 0, 500, '…');
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

    private function normalizeValue(mixed $value, ?string $type, bool $isNullableTemporalColumn): string
    {
        if ($value === null || ($value === '' && $isNullableTemporalColumn)) {
            return '<null>';
        }
        if ($type === 'boolean') {
            return in_array($value, [true, 1, '1', 't', 'true'], true) ? '1' : '0';
        }
        if ($type === 'json' || $type === 'jsonb') {
            $decoded = json_decode((string) $value, true, 512, JSON_THROW_ON_ERROR);

            return json_encode($this->sortJson($decoded), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        }
        if ($type === 'date') {
            $normalized = (string) $value;

            if (preg_match('/^\d{4}-\d{2}-\d{2}/', $normalized) === 1) {
                return substr($normalized, 0, 10);
            }
        }
        if (str_starts_with((string) $type, 'numeric')) {
            return $this->normalizeDecimal($value);
        }

        return (string) $value;
    }

    private function normalizeDecimal(mixed $value): string
    {
        $normalized = (string) $value;
        if (str_contains($normalized, '.')) {
            $normalized = rtrim(rtrim($normalized, '0'), '.');
        }

        return $normalized === '' || $normalized === '-0' ? '0' : $normalized;
    }

    private function sortJson(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        if (! array_is_list($value)) {
            ksort($value);
        }
        foreach ($value as $key => $item) {
            $value[$key] = $this->sortJson($item);
        }

        return $value;
    }

    private function checkForeignKeys(array &$report): void
    {
        $orphans = (int) DB::selectOne('select count(*) as total from fiscal_documents_lines l left join fiscal_documents d on d.id = l.fiscal_document_id where d.id is null')->total;
        $this->check($report, 'fiscal_documents_lines.orphans', $orphans === 0, "orphans={$orphans}");

        $invalidJson = (int) DB::selectOne('select count(*) as total from fiscal_documents where json_typeof(metadata) is null or (sdi_payload is not null and json_typeof(sdi_payload) is null)')->total;
        $this->check($report, 'fiscal_documents.json', $invalidJson === 0, "invalid={$invalidJson}");
    }

    private function checkSequences(array &$report): void
    {
        foreach (self::TABLES as $table) {
            if (! $this->hasIntegerId($table)) {
                continue;
            }
            $result = DB::selectOne("select coalesce(max(id), 0) as max_id, pg_sequence_last_value(pg_get_serial_sequence('{$table}', 'id')::regclass) as last_value from \"{$table}\"");
            $this->check($report, "{$table}.sequence", $result->last_value >= $result->max_id, "max={$result->max_id}; sequence={$result->last_value}");
        }
    }

    private function hasIntegerId(string $table): bool
    {
        return in_array($this->targetColumnTypes($table)['id'] ?? null, ['smallint', 'integer', 'bigint'], true);
    }

    private function check(array &$report, string $name, bool $passed, string $detail, ?array $diagnostic = null): void
    {
        $check = compact('name', 'passed', 'detail');
        if ($diagnostic !== null) {
            $check['diagnostic'] = $diagnostic;
        }
        $report['checks'][] = $check;

        $message = ($passed ? 'PASS' : 'FAIL').": {$name} ({$detail})";
        if ($diagnostic !== null) {
            $message .= ' diagnostic='.json_encode($diagnostic, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        }
        $this->{$passed ? 'info' : 'error'}($message);
    }
}
