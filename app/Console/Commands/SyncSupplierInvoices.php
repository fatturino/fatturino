<?php

namespace App\Console\Commands;

use App\Contracts\SdiProvider;
use App\Models\Contact;
use App\Models\PurchaseInvoice;
use App\Settings\CompanySettings;
use Illuminate\Console\Command;

class SyncSupplierInvoices extends Command
{
    protected $signature = 'invoices:sync-suppliers
                            {--page=1 : Page number to start from}
                            {--per-page=50 : Number of invoices per page}
                            {--all : Sync all pages of invoices}
                            {--sender= : Filter by sender VAT number}';

    protected $description = 'Sync supplier invoices from SDI into purchase invoices';

    /**
     * Service is injected here (not in constructor) to avoid resolving settings
     * during Artisan bootstrap, which would fail if migrations haven't run yet.
     */
    public function handle(SdiProvider $service): int
    {
        $this->info('Starting supplier invoice synchronization...');

        if (! $service->isConfigured()) {
            $this->error('SDI service is not configured.');

            return self::FAILURE;
        }

        $settings = app(CompanySettings::class);
        $companyVat = $settings->company_vat_number;

        $filters = [
            'per_page' => $this->option('per-page'),
            'recipient' => $companyVat,
        ];

        if ($this->option('sender')) {
            $filters['sender'] = $this->option('sender');
        }

        $syncedCount = 0;
        $skippedCount = 0;
        $errorCount = 0;
        $currentPage = (int) $this->option('page');
        $syncAll = $this->option('all');

        do {
            $filters['page'] = $currentPage;

            $this->info("Fetching page {$currentPage}...");

            $result = $service->getSupplierInvoices($filters);

            if (! $result['success']) {
                $this->error("Failed to fetch invoices: {$result['message']}");

                return self::FAILURE;
            }

            $invoices = $result['data'] ?? [];
            $meta = $result['meta'] ?? null;

            if (empty($invoices)) {
                $this->info('No more invoices to sync.');
                break;
            }

            $progressBar = $this->output->createProgressBar(count($invoices));
            $progressBar->start();

            foreach ($invoices as $invoiceData) {
                try {
                    $contact = $this->findOrCreateContact($invoiceData);
                    $invoice = PurchaseInvoice::createOrUpdateFromSdiData($invoiceData, $contact);

                    if ($invoice->wasRecentlyCreated) {
                        $syncedCount++;
                    } else {
                        $skippedCount++;
                    }
                } catch (\Exception $e) {
                    $errorCount++;
                    $this->newLine();
                    $this->error("Error processing invoice {$invoiceData['uuid']}: {$e->getMessage()}");
                }

                $progressBar->advance();
            }

            $progressBar->finish();
            $this->newLine();

            $hasMorePages = false;
            if ($meta && isset($meta['current_page'], $meta['last_page'])) {
                $hasMorePages = $meta['current_page'] < $meta['last_page'];
                $this->info("Page {$meta['current_page']} of {$meta['last_page']}");
            }

            $currentPage++;

            if (! $syncAll || ! $hasMorePages) {
                break;
            }

        } while (true);

        $this->newLine();
        $this->info('Synchronization completed!');
        $this->table(
            ['Status', 'Count'],
            [
                ['New invoices', $syncedCount],
                ['Already synced', $skippedCount],
                ['Errors', $errorCount],
            ]
        );

        return self::SUCCESS;
    }

    /**
     * Find an existing Contact by VAT number or create one from SDI payload data.
     */
    private function findOrCreateContact(array $invoiceData): Contact
    {
        $payload = $invoiceData['payload'] ?? [];
        $header = $payload['fattura_elettronica_header'] ?? [];
        $supplier = $header['cedente_prestatore'] ?? [];
        $supplierData = $supplier['dati_anagrafici'] ?? [];
        $supplierAddress = $supplier['sede'] ?? [];

        $name = $supplierData['anagrafica']['denominazione']
            ?? trim(($supplierData['anagrafica']['nome'] ?? '').' '.($supplierData['anagrafica']['cognome'] ?? ''));

        $vatNumber = $supplierData['id_fiscale_iva']['id_codice'] ?? null;
        $taxCode = $supplierData['codice_fiscale'] ?? null;

        $addressParts = array_filter([
            $supplierAddress['indirizzo'] ?? '',
            $supplierAddress['numero_civico'] ?? '',
        ]);
        $address = implode(', ', $addressParts) ?: null;

        // Use VAT number as unique key when available; otherwise fall back to name
        $uniqueKey = $vatNumber
            ? ['vat_number' => $vatNumber]
            : ['name' => $name ?: 'Unknown'];

        return Contact::firstOrCreate($uniqueKey, [
            'name' => $name ?: 'Unknown',
            'vat_number' => $vatNumber,
            'tax_code' => $taxCode,
            'address' => $address,
            'city' => $supplierAddress['comune'] ?? null,
            'postal_code' => $supplierAddress['cap'] ?? null,
            'province' => $supplierAddress['provincia'] ?? null,
            'country' => $supplierAddress['nazione'] ?? 'IT',
            'country_code' => $supplierAddress['nazione'] ?? 'IT',
            'is_supplier' => true,
        ]);
    }
}
