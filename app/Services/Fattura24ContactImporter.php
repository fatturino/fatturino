<?php

namespace App\Services;

use App\Models\Contact;
use Illuminate\Support\Facades\Log;

/**
 * Import contacts from Fattura24 CSV export
 */
class Fattura24ContactImporter
{
    protected array $stats = [
        'total' => 0,
        'imported' => 0,
        'updated' => 0,
        'skipped' => 0,
        'errors' => 0,
    ];

    protected array $errors = [];

    /**
     * Import contacts from CSV file
     *
     * @param  string  $filePath  Path to Fattura24 CSV export
     * @param  bool  $updateExisting  Whether to update existing contacts based on VAT number
     * @return array Import statistics
     */
    public function import(string $filePath, bool $updateExisting = false): array
    {
        if (! file_exists($filePath)) {
            throw new \InvalidArgumentException("File not found: {$filePath}");
        }

        $this->resetStats();

        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            throw new \RuntimeException("Cannot open file: {$filePath}");
        }

        // Read header line
        $header = fgetcsv($handle, 0, ';');
        if ($header === false) {
            fclose($handle);
            throw new \RuntimeException('Invalid CSV file: cannot read header');
        }

        // Process each row
        $lineNumber = 1;
        while (($row = fgetcsv($handle, 0, ';')) !== false) {
            $lineNumber++;
            $this->stats['total']++;

            try {
                $data = $this->parseRow($header, $row);

                // Skip if no company name
                if (empty($data['name'])) {
                    $this->stats['skipped']++;

                    continue;
                }

                $this->importContact($data, $updateExisting);
            } catch (\Exception $e) {
                $this->stats['errors']++;
                $this->errors[] = "Line {$lineNumber}: ".$e->getMessage();
                Log::error('Fattura24 import error', [
                    'line' => $lineNumber,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        fclose($handle);

        return [
            'stats' => $this->stats,
            'errors' => $this->errors,
        ];
    }

    /**
     * Parse a CSV row into contact data array
     */
    protected function parseRow(array $header, array $row): array
    {
        // Map CSV columns to array keys
        $mapped = array_combine($header, $row);

        // Extract country code from VAT number if present
        $vatNumber = trim($mapped['P.IVA'] ?? '');
        $countryCode = 'IT'; // Default to Italy

        if (! empty($vatNumber) && preg_match('/^([A-Z]{2})/', $vatNumber, $matches)) {
            $countryCode = $matches[1];
        }

        // Clean VAT number (remove country prefix if present)
        if (! empty($vatNumber) && strlen($countryCode) === 2) {
            $vatNumber = preg_replace('/^'.$countryCode.'/', '', $vatNumber);
        }

        // Clean address field (Fattura24 concatenates address, city, province)
        $addressParts = $this->parseAddressField($mapped['Indirizzo'] ?? '');

        // Determine contact type
        $tipo = strtoupper(trim($mapped['Tipo'] ?? ''));
        $isCustomer = str_contains($tipo, 'CLIENTE');
        $isSupplier = str_contains($tipo, 'FORNITORE');

        return [
            'name' => trim($mapped['Rag. Sociale'] ?? ''),
            'vat_number' => $vatNumber,
            'tax_code' => trim($mapped['Cod. fiscale'] ?? ''),
            'country_code' => $countryCode,
            'address' => $addressParts['address'] ?? trim($mapped['Indirizzo'] ?? ''),
            'city' => trim($mapped['Città'] ?? ''),
            'province' => trim($mapped['Provincia'] ?? ''),
            'postal_code' => trim($mapped['CAP'] ?? '') === '00000' ? null : trim($mapped['CAP'] ?? ''),
            'country' => $this->normalizeCountry(trim($mapped['Paese'] ?? '')),
            'phone' => trim($mapped['Telefono'] ?? ''),
            'mobile' => trim($mapped['Cellulare'] ?? ''),
            'email' => trim($mapped['Email'] ?? ''),
            'pec' => trim($mapped['Pec'] ?? ''),
            'sdi_code' => $this->normalizeSdiCode(trim($mapped['Cod. Destinatario'] ?? '')),
            'is_customer' => $isCustomer,
            'is_supplier' => $isSupplier,
            // Store original Fattura24 code as reference
            'notes' => 'Importato da Fattura24 (Cod. '.trim($mapped['Cod.'] ?? '').')',
        ];
    }

    /**
     * Parse Fattura24 concatenated address field
     */
    protected function parseAddressField(string $addressField): array
    {
        // Fattura24 format: "VIA EXAMPLE, 123 - CITY PROVINCE"
        // Try to extract just the street address
        $parts = explode(' - ', $addressField);

        return [
            'address' => trim($parts[0] ?? $addressField),
        ];
    }

    /**
     * Normalize country code
     */
    protected function normalizeCountry(string $country): string
    {
        $country = strtoupper(trim($country));

        // Already a 2-letter code
        if (strlen($country) === 2) {
            return $country;
        }

        // Common mappings
        $mappings = [
            'ITALIA' => 'IT',
            'ITALY' => 'IT',
            'GERMANY' => 'DE',
            'DEUTSCHLAND' => 'DE',
            'FRANCE' => 'FR',
            'SPAIN' => 'ES',
            'USA' => 'US',
            'UNITED STATES' => 'US',
        ];

        return $mappings[$country] ?? $country;
    }

    /**
     * Normalize SDI code (replace XXXXXXX with null)
     */
    protected function normalizeSdiCode(?string $sdiCode): ?string
    {
        $sdiCode = trim($sdiCode ?? '');

        if (empty($sdiCode) || $sdiCode === 'XXXXXXX') {
            return null;
        }

        return $sdiCode;
    }

    /**
     * Import or update a single contact
     */
    protected function importContact(array $data, bool $updateExisting): void
    {
        // Try to find existing contact by VAT number
        $existing = null;
        if (! empty($data['vat_number'])) {
            $existing = Contact::where('vat_number', $data['vat_number'])
                ->where('country_code', $data['country_code'])
                ->first();
        }

        if ($existing) {
            if ($updateExisting) {
                $existing->update($data);
                $this->stats['updated']++;
            } else {
                $this->stats['skipped']++;
            }
        } else {
            Contact::create($data);
            $this->stats['imported']++;
        }
    }

    /**
     * Reset import statistics
     */
    protected function resetStats(): void
    {
        $this->stats = [
            'total' => 0,
            'imported' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => 0,
        ];
        $this->errors = [];
    }

    /**
     * Get import statistics
     */
    public function getStats(): array
    {
        return $this->stats;
    }

    /**
     * Get import errors
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
