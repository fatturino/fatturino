<?php

namespace App\Contracts;

/**
 * Contract for SDI (Sistema di Interscambio) provider plugins.
 *
 * Each provider plugin (e.g., OpenAPI, Aruba) implements this interface
 * to handle electronic invoice submission, validation, and retrieval.
 *
 * Provider-specific operations (activation, webhook config, simulation)
 * stay in the plugin's own service classes, not in this contract.
 *
 * Default binding: NullSdiProvider (no provider configured).
 */
interface SdiProvider
{
    /** Unique provider identifier (e.g. 'openapi', 'aruba') */
    public function id(): string;

    /** Human-readable name for UI display */
    public function name(): string;

    /** Whether the provider is properly configured and ready to use */
    public function isConfigured(): bool;

    /** Whether the provider has been activated (registered with SDI) */
    public function isActivated(): bool;

    /**
     * Send an electronic invoice XML to SDI.
     *
     * @param  string  $xmlContent  The FatturaPA XML content
     * @param  string  $fileName  SDI-compliant filename (e.g. IT04826950166_00001.xml)
     * @return array{success: bool, uuid?: string, message?: string, error_code?: string, error_message?: string}
     */
    public function sendInvoice(string $xmlContent, string $fileName = ''): array;

    /**
     * Validate XML structure before sending to SDI.
     *
     * @return array{valid: bool, errors?: string[], message?: string}
     */
    public function validateXml(string $xmlContent): array;

    /**
     * Retrieve supplier (passive) invoices from SDI.
     *
     * @return array{success: bool, data?: array, meta?: array}
     */
    public function getSupplierInvoices(array $filters = []): array;

    /**
     * Download invoice XML by provider-specific identifier.
     *
     * @return array{success: bool, xml?: string, error?: string}
     */
    public function downloadInvoiceXml(string $identifier): array;

    /**
     * Get notifications for a specific invoice.
     *
     * @return array{success: bool, notifications?: array, error?: string}
     */
    public function getInvoiceNotifications(string $identifier): array;

    /** Route name for the provider's settings page */
    public function settingsRouteName(): string;
}
