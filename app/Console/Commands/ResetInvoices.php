<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ResetInvoices extends Command
{
    protected $signature = 'invoices:reset
                            {--force : Skip confirmation prompt}';

    protected $description = 'Delete all invoices and invoice lines, and reset auto-increment counters';

    public function handle(): int
    {
        if (! $this->option('force') && ! $this->confirm('Delete ALL invoices and invoice lines? This cannot be undone.')) {
            $this->info('Aborted.');

            return self::SUCCESS;
        }

        // Fiscal document lines reference fiscal documents, but we delete them
        // explicitly to be safe and to reset its auto-increment counter too.
        $invoiceLinesDeleted = DB::table('fiscal_documents_lines')->delete();
        $invoicesDeleted = DB::table('fiscal_documents')->delete();

        $this->resetAutoIncrement('fiscal_documents_lines');
        $this->resetAutoIncrement('fiscal_documents');

        $this->info("Deleted {$invoicesDeleted} invoices and {$invoiceLinesDeleted} invoice lines.");
        $this->info('Auto-increment counters reset.');
        $this->info('Invoice sequences will restart from 1 on next use.');

        return self::SUCCESS;
    }

    /**
     * Reset the auto-increment counter for a table.
     *
     * SQLite stores counters in sqlite_sequence, MySQL uses AUTO_INCREMENT,
     * PostgreSQL uses SERIAL sequences.
     */
    private function resetAutoIncrement(string $table): void
    {
        $driver = DB::getDriverName();

        match ($driver) {
            'sqlite' => $this->resetSqliteSequence($table),
            'mysql' => DB::statement("ALTER TABLE `{$table}` AUTO_INCREMENT = 1"),
            'pgsql' => $this->resetPostgresSequence($table),
            default => null, // unknown driver, skip silently
        };
    }

    private function resetSqliteSequence(string $table): void
    {
        // sqlite_sequence table only exists when at least one AUTOINCREMENT
        // column was used. It's safe to attempt the delete silently.
        DB::statement("DELETE FROM sqlite_sequence WHERE name = '{$table}'");
    }

    private function resetPostgresSequence(string $table): void
    {
        DB::statement("select setval(pg_get_serial_sequence('{$table}', 'id'), 1, false)");
    }
}
