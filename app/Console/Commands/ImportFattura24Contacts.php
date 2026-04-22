<?php

namespace App\Console\Commands;

use App\Services\Fattura24ContactImporter;
use Illuminate\Console\Command;

class ImportFattura24Contacts extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'import:fattura24-contacts
                            {file : Path to Fattura24 CSV file}
                            {--update : Update existing contacts based on VAT number}';

    /**
     * The console command description.
     */
    protected $description = 'Import contacts from Fattura24 CSV export (Rubrica)';

    /**
     * Execute the console command.
     */
    public function handle(Fattura24ContactImporter $importer): int
    {
        $filePath = $this->argument('file');
        $updateExisting = $this->option('update');

        $this->info('Importing contacts from Fattura24...');
        $this->newLine();

        if (! file_exists($filePath)) {
            $this->error("File not found: {$filePath}");

            return self::FAILURE;
        }

        try {
            $result = $importer->import($filePath, $updateExisting);

            $this->displayResults($result);

            if ($result['stats']['errors'] > 0) {
                return self::FAILURE;
            }

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Import failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * Display import results
     */
    protected function displayResults(array $result): void
    {
        $stats = $result['stats'];

        $this->info('Import completed!');
        $this->newLine();

        $this->table(
            ['Metric', 'Count'],
            [
                ['Total rows', $stats['total']],
                ['Imported', $stats['imported']],
                ['Updated', $stats['updated']],
                ['Skipped', $stats['skipped']],
                ['Errors', $stats['errors']],
            ]
        );

        if (count($result['errors']) > 0) {
            $this->newLine();
            $this->error('Errors occurred during import:');
            foreach ($result['errors'] as $error) {
                $this->line('  • '.$error);
            }
        }
    }
}
