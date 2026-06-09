<?php

namespace App\Services;

use App\Settings\CompanySettings;

class InvoiceTenantGuardService
{
    public function __construct(
        private readonly BusinessFingerprintService $businessFingerprintService,
        private readonly CompanySettings $companySettings,
    ) {}

    public function matchesInboundInvoice(string $xml): bool
    {
        return $this->matchesRole($xml, 'customer');
    }

    public function matchesOutboundInvoice(string $xml): bool
    {
        return $this->matchesRole($xml, 'supplier');
    }

    private function matchesRole(string $xml, string $role): bool
    {
        $companyFiscalId = $this->businessFingerprintService->normalizeFiscalIdentifier(
            $this->companySettings->company_vat_number
        );

        if ($companyFiscalId === null) {
            return false;
        }

        $invoiceFiscalId = match ($role) {
            'supplier' => $this->businessFingerprintService->extractSupplierFiscalIdFromXml($xml),
            default => $this->businessFingerprintService->extractCustomerFiscalIdFromXml($xml),
        };

        if ($invoiceFiscalId === null) {
            return false;
        }

        return hash_equals($companyFiscalId, $invoiceFiscalId);
    }
}
