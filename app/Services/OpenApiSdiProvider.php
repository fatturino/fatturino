<?php

namespace App\Services;

use App\Contracts\SdiProvider;
use App\Settings\OpenApiSettings;

class OpenApiSdiProvider implements SdiProvider
{
    public function __construct(
        protected OpenApiSdiService $service,
        protected OpenApiSettings $settings,
    ) {}

    public function id(): string
    {
        return 'openapi';
    }

    public function name(): string
    {
        return 'OpenAPI';
    }

    public function isConfigured(): bool
    {
        return $this->service->isConfigured();
    }

    public function isActivated(): bool
    {
        return $this->settings->activated;
    }

    public function sendInvoice(string $xmlContent, string $fileName = ''): array
    {
        return $this->service->sendInvoice($xmlContent);
    }

    public function validateXml(string $xmlContent): array
    {
        return $this->service->validateXml($xmlContent);
    }

    public function getSupplierInvoices(array $filters = []): array
    {
        return $this->service->getSupplierInvoices($filters);
    }

    public function downloadInvoiceXml(string $identifier): array
    {
        return $this->service->downloadInvoiceXml($identifier);
    }

    public function getInvoiceNotifications(string $identifier): array
    {
        return $this->service->getInvoiceNotifications($identifier);
    }

    public function settingsRouteName(): string
    {
        return 'settings.openapi';
    }
}
