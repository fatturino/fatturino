<?php

namespace App\Services;

use App\Contracts\SdiProvider;

/**
 * Default SDI provider: no provider configured.
 * All operations return "not configured" responses.
 */
class NullSdiProvider implements SdiProvider
{
    public function id(): string
    {
        return 'none';
    }

    public function name(): string
    {
        return 'No SDI Provider';
    }

    public function isConfigured(): bool
    {
        return false;
    }

    public function isActivated(): bool
    {
        return false;
    }

    public function sendInvoice(string $xmlContent, string $fileName = ''): array
    {
        return [
            'success' => false,
            'error_message' => 'No SDI provider configured. Install a provider plugin.',
        ];
    }

    public function validateXml(string $xmlContent): array
    {
        return [
            'valid' => false,
            'errors' => ['No SDI provider configured. Install a provider plugin.'],
        ];
    }

    public function getSupplierInvoices(array $filters = []): array
    {
        return [
            'success' => false,
            'error' => 'No SDI provider configured.',
        ];
    }

    public function downloadInvoiceXml(string $identifier): array
    {
        return [
            'success' => false,
            'error' => 'No SDI provider configured.',
        ];
    }

    public function getInvoiceNotifications(string $identifier): array
    {
        return [
            'success' => false,
            'error' => 'No SDI provider configured.',
        ];
    }

    public function settingsRouteName(): string
    {
        return '';
    }
}
