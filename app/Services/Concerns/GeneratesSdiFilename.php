<?php

namespace App\Services\Concerns;

use App\Settings\CompanySettings;

trait GeneratesSdiFilename
{
    /**
     * Build an SDI-compliant filename: {CountryCode}{VatNumber}_{Progressive}.xml
     * e.g. IT04826950166_00001.xml
     *
     * Rules:
     * - CountryCode: 2 uppercase letters from settings
     * - VatNumber: numeric part only (country prefix stripped case-insensitively)
     * - Progressive: alphanumeric, max 5 chars (truncated if document id exceeds 99999)
     */
    protected function buildSdiFilename(CompanySettings $settings, int $documentId): string
    {
        $countryCode = strtoupper($settings->company_country);

        // Strip country prefix case-insensitively, then keep only alphanumeric chars
        $vatNumber = preg_replace('/^' . preg_quote($countryCode, '/') . '/i', '', $settings->company_vat_number);
        $vatNumber = preg_replace('/[^A-Z0-9]/i', '', $vatNumber);
        $vatNumber = strtoupper($vatNumber);

        // Progressive: zero-padded, max 5 alphanumeric chars per SDI spec
        $progressivo = str_pad((string) ($documentId % 100000), 5, '0', STR_PAD_LEFT);

        return $countryCode . $vatNumber . '_' . $progressivo . '.xml';
    }
}
