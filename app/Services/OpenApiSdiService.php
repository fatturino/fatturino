<?php

namespace App\Services;

use App\Settings\OpenApiSettings;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * HTTP client for interacting with OpenAPI SDI (Sistema di Interscambio) APIs.
 *
 * Handles sending invoices, retrieving supplier invoices, managing
 * business configurations, and webhook setup.
 */
class OpenApiSdiService
{
    // Shared codice destinatario assigned to all OpenAPI tenants
    public const CODICE_DESTINATARIO = 'JKKZDGR';

    private const CONNECT_TIMEOUT_SECONDS = 5;

    private const REQUEST_TIMEOUT_SECONDS = 20;

    private string $baseUrl;

    private ?string $apiToken;

    private bool $isSandbox;

    public function __construct(protected OpenApiSettings $settings)
    {
        $this->isSandbox = $this->settings->sandbox;
        $this->baseUrl = $this->isSandbox
          ? config('fe-openapi.sandbox_url')
          : config('fe-openapi.production_url');
        $this->apiToken = $this->settings->api_token;
    }

    /**
     * Send an electronic invoice XML to SDI via OpenAPI
     */
    public function sendInvoice(string $xmlContent): array
    {
        if (empty($this->apiToken)) {
            throw new \Exception(
                'OpenAPI SDI API token not configured. Please configure it in Settings > OpenAPI.',
            );
        }

        try {
            /** @var Response $response */
            $response = $this->newRequest()
                ->withHeaders([
                    'Content-Type' => 'application/xml',
                    'Accept' => 'application/json',
                ])
                ->withBody($xmlContent, 'application/xml')
                ->post("{$this->baseUrl}/invoices");

            $body = $response->json();

            Log::channel('fe-openapi')->info('OpenAPI SDI invoice submission', [
                'status' => $response->status(),
                'success' => $body['success'] ?? false,
                'uuid' => $body['data']['uuid'] ?? null,
                'error' => $body['error'] ?? null,
                'message' => $body['message'] ?? null,
            ]);

            if ($response->successful() && ($body['success'] ?? false)) {
                return [
                    'success' => true,
                    'uuid' => $body['data']['uuid'] ?? null,
                    'file_id' => $body['data']['file_id'] ?? null,
                    'message' => $body['message'] ?? 'Fattura inviata con successo allo SDI',
                ];
            }

            $errorMessage = $body['message'] ?? 'Unknown error occurred';
            $errorCode = $body['error'] ?? null;

            return [
                'success' => false,
                'error_code' => $errorCode,
                'error_message' => $errorMessage,
                'http_status' => $response->status(),
            ];
        } catch (\Exception $e) {
            Log::channel('fe-openapi')->error('OpenAPI SDI invoice submission failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error_message' => 'Failed to connect to OpenAPI SDI: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Validate XML structure before sending to SDI
     */
    public function validateXml(string $xmlContent): array
    {
        try {
            $xml = new \SimpleXMLElement($xmlContent);

            $errors = [];

            if (! isset($xml->FatturaElettronicaHeader)) {
                $errors[] = __('app.invoices.xml_errors.missing_header');
            }

            if (! isset($xml->FatturaElettronicaBody)) {
                $errors[] = __('app.invoices.xml_errors.missing_body');
            }

            if (isset($xml->FatturaElettronicaHeader->DatiTrasmissione)) {
                $datiTrasmissione = $xml->FatturaElettronicaHeader->DatiTrasmissione;

                if (! isset($datiTrasmissione->IdTrasmittente)) {
                    $errors[] = __('app.invoices.xml_errors.missing_sender_id');
                }

                if (! isset($datiTrasmissione->ProgressivoInvio)) {
                    $errors[] = __('app.invoices.xml_errors.missing_progressive');
                }

                if (! isset($datiTrasmissione->FormatoTrasmissione)) {
                    $errors[] = __('app.invoices.xml_errors.missing_format');
                }

                if (
                    ! isset($datiTrasmissione->CodiceDestinatario) &&
                    ! isset($datiTrasmissione->PECDestinatario)
                ) {
                    $errors[] = __('app.invoices.xml_errors.missing_recipient');
                }
            } else {
                $errors[] = __('app.invoices.xml_errors.missing_transmission_data');
            }

            // Validate customer fiscal identifier: SDI requires at least IdFiscaleIVA or a valid CodiceFiscale (11-16 chars)
            $committente = $xml->FatturaElettronicaHeader->CessionarioCommittente ?? null;
            if ($committente) {
                $datiAnag = $committente->DatiAnagrafici ?? null;
                $hasVat = isset($datiAnag->IdFiscaleIVA->IdCodice) && (string) $datiAnag->IdFiscaleIVA->IdCodice !== '';
                $taxCode = (string) ($datiAnag->CodiceFiscale ?? '');
                $hasTaxCode = strlen($taxCode) >= 11 && strlen($taxCode) <= 16;

                if (! $hasVat && ! $hasTaxCode) {
                    $errors[] = __('app.invoices.xml_errors.missing_customer_fiscal_id');
                }
            }

            if (count($errors) > 0) {
                return [
                    'valid' => false,
                    'errors' => $errors,
                ];
            }

            return [
                'valid' => true,
                'message' => 'XML structure is valid',
            ];
        } catch (\Exception $e) {
            return [
                'valid' => false,
                'errors' => [
                    __('app.invoices.xml_errors.parsing_error', [
                        'error' => $e->getMessage(),
                    ]),
                ],
            ];
        }
    }

    /**
     * Check if the service is configured and ready to use
     */
    public function isConfigured(): bool
    {
        return ! empty($this->baseUrl) && ! empty($this->apiToken);
    }

    /**
     * Get the current environment (sandbox or production)
     */
    public function getEnvironment(): string
    {
        return $this->isSandbox ? 'sandbox' : 'production';
    }

    /**
     * Strip country prefix (e.g. "IT") from fiscal ID. OpenAPI expects the
     * numeric part only, while Fatturino stores the full VAT number with prefix.
     */
    private function normalizeFiscalId(string $fiscalId): string
    {
        return preg_replace('/^[A-Z]{2}/i', '', $fiscalId);
    }

    /**
     * Check the activation status of the electronic invoicing service with OpenAPI
     */
    public function checkActivationStatus(string $fiscalId): array
    {
        $fiscalId = $this->normalizeFiscalId($fiscalId);

        if (! $this->isConfigured()) {
            return [
                'activated' => false,
                'error' => 'Service not configured',
                'message' => 'OpenAPI SDI API token is not configured',
            ];
        }

        try {
            $url = "{$this->baseUrl}/business_registry_configurations/{$fiscalId}";

            Log::channel('fe-openapi')->info('checkActivationStatus request', [
                'url' => $url,
                'token_prefix' => $this->apiToken ? substr($this->apiToken, 0, 8).'...' : 'null',
            ]);

            /** @var Response $response */
            $response = $this->newRequest()->get($url);

            Log::channel('fe-openapi')->info('checkActivationStatus response', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            if ($response->successful()) {
                $body = $response->json();

                return [
                    'activated' => true,
                    'data' => $body['data'] ?? null,
                    'message' => 'Service is active',
                ];
            }

            if ($response->status() === 404) {
                return [
                    'activated' => false,
                    'message' => 'Business registry configuration not found. You need to register with OpenAPI.',
                    'registration_required' => true,
                ];
            }

            return [
                'activated' => false,
                'error' => $response->status(),
                'message' => $response->json()['message'] ?? 'Unknown error',
            ];
        } catch (\Exception $e) {
            Log::channel('fe-openapi')->error('OpenAPI SDI activation check failed', [
                'error' => $e->getMessage(),
            ]);

            return [
                'activated' => false,
                'error' => 'connection_failed',
                'message' => 'Failed to connect to OpenAPI: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Get configuration summary for display
     */
    public function getConfigurationSummary(): array
    {
        return [
            'configured' => $this->isConfigured(),
            'environment' => $this->getEnvironment(),
            'base_url' => $this->baseUrl,
            'has_token' => ! empty($this->apiToken),
            'token_preview' => $this->apiToken
              ? substr($this->apiToken, 0, 10).'...'
              : null,
        ];
    }

    /**
     * Register a new BusinessRegistryConfiguration with OpenAPI
     */
    public function registerBusinessConfiguration(
        string $fiscalId,
        string $email,
    ): array {
        $fiscalId = $this->normalizeFiscalId($fiscalId);

        if (! $this->isConfigured()) {
            return [
                'success' => false,
                'error' => 'Service not configured',
                'message' => 'OpenAPI SDI API token is not configured',
            ];
        }

        try {
            $url = "{$this->baseUrl}/business_registry_configurations";
            $payload = [
                'fiscal_id' => $fiscalId,
                'email' => $email,
                'apply_legal_storage' => false,
                'apply_signature' => false,
            ];
            Log::channel('fe-openapi')->info('registerBusinessConfiguration request', [
                'url' => $url,
                'payload' => $payload,
                'token_prefix' => $this->apiToken ? substr($this->apiToken, 0, 8).'...' : 'null',
            ]);

            /** @var Response $response */
            $response = $this->newRequest()->post($url, $payload);
            Log::channel('fe-openapi')->info('registerBusinessConfiguration response', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            if ($response->successful()) {
                $body = $response->json();

                return [
                    'success' => true,
                    'data' => $body['data'] ?? null,
                    'message' => 'Business registry configuration created successfully. Check your email for activation instructions.',
                ];
            }

            $body = $response->json();

            return [
                'success' => false,
                'error' => $response->status(),
                'message' => $body['message'] ?? 'Failed to register business configuration',
            ];
        } catch (\Exception $e) {
            Log::channel('fe-openapi')->error('OpenAPI SDI registration failed', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'connection_failed',
                'message' => 'Failed to connect to OpenAPI: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Retrieve supplier invoices (passive invoices) from OpenAPI
     */
    public function getSupplierInvoices(array $filters = []): array
    {
        if (! $this->isConfigured()) {
            return [
                'success' => false,
                'error' => 'Service not configured',
                'message' => 'OpenAPI SDI API token is not configured',
            ];
        }

        try {
            $params = [
                // type=1 = fatture passive (ricevute), type=0 = fatture attive (inviate)
                'type' => '1',
            ];

            if (! empty($filters['sender'])) {
                $params['mittente'] = $filters['sender'];
            }

            if (! empty($filters['recipient'])) {
                $params['destinatario'] = $filters['recipient'];
            }

            if (! empty($filters['page'])) {
                $params['page'] = $filters['page'];
            }

            if (! empty($filters['per_page'])) {
                $params['per_page'] = $filters['per_page'];
            }

            /** @var Response $response */
            $response = $this->newRequest()->get(
                "{$this->baseUrl}/invoices",
                $params,
            );

            if ($response->successful()) {
                $body = $response->json();

                return [
                    'success' => true,
                    'data' => $body['data'] ?? [],
                    'meta' => $body['meta'] ?? null,
                    'links' => $body['links'] ?? null,
                ];
            }

            $body = $response->json();

            return [
                'success' => false,
                'error' => $response->status(),
                'message' => $body['message'] ?? 'Failed to retrieve supplier invoices',
            ];
        } catch (\Exception $e) {
            Log::channel('fe-openapi')->error('OpenAPI SDI supplier invoices retrieval failed', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'connection_failed',
                'message' => 'Failed to connect to OpenAPI: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Retrieve a single invoice by UUID
     */
    public function getInvoiceByUuid(string $uuid): array
    {
        if (! $this->isConfigured()) {
            return [
                'success' => false,
                'error' => 'Service not configured',
                'message' => 'OpenAPI SDI API token is not configured',
            ];
        }

        try {
            /** @var Response $response */
            $response = $this->newRequest()->get(
                "{$this->baseUrl}/invoices/{$uuid}",
            );

            if ($response->successful()) {
                $body = $response->json();

                return [
                    'success' => true,
                    'data' => $body['data'] ?? null,
                ];
            }

            if ($response->status() === 404) {
                return [
                    'success' => false,
                    'error' => 'not_found',
                    'message' => 'Invoice not found',
                ];
            }

            $body = $response->json();

            return [
                'success' => false,
                'error' => $response->status(),
                'message' => $body['message'] ?? 'Failed to retrieve invoice',
            ];
        } catch (\Exception $e) {
            Log::channel('fe-openapi')->error('OpenAPI SDI invoice retrieval failed', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'connection_failed',
                'message' => 'Failed to connect to OpenAPI: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Download invoice XML file by UUID
     */
    public function downloadInvoiceXml(string $uuid): array
    {
        if (! $this->isConfigured()) {
            return [
                'success' => false,
                'error' => 'Service not configured',
                'message' => 'OpenAPI SDI API token is not configured',
            ];
        }

        try {
            /** @var Response $response */
            $response = $this->newRequest()
                ->accept('application/xml')
                ->get("{$this->baseUrl}/invoices_download/{$uuid}");

            if ($response->successful()) {
                $body = $response->body();

                // API returns JSON even when Accept: application/xml is set.
                // Extract the nested payload and convert it to FatturaElettronica XML.
                if (! str_starts_with(ltrim($body), '<')) {
                    $json = json_decode($body, true);
                    $payload = $json['data']['payload'] ?? null;

                    if (empty($payload)) {
                        return [
                            'success' => false,
                            'error' => 'empty_payload',
                            'message' => "No invoice payload found in API response for UUID {$uuid}",
                        ];
                    }

                    if (is_string($payload)) {
                        $payload = json_decode($payload, true);
                    }

                    $body = $this->convertInvoicePayloadToXml($payload);
                }

                return [
                    'success' => true,
                    'xml' => $body,
                    'content_type' => $response->header('Content-Type'),
                ];
            }

            if ($response->status() === 404) {
                return [
                    'success' => false,
                    'error' => 'not_found',
                    'message' => 'Invoice file not found',
                ];
            }

            return [
                'success' => false,
                'error' => $response->status(),
                'message' => 'Failed to download invoice XML',
            ];
        } catch (\Exception $e) {
            Log::channel('fe-openapi')->error('OpenAPI SDI invoice download failed', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'connection_failed',
                'message' => 'Failed to connect to OpenAPI: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Configure API callback endpoints for receiving SDI events
     */
    public function configureApiCallbacks(
        string $fiscalId,
        string $webhookUrl,
        string $authHeader,
    ): array {
        $fiscalId = $this->normalizeFiscalId($fiscalId);

        if (! $this->isConfigured()) {
            return [
                'success' => false,
                'error' => 'Service not configured',
                'message' => 'OpenAPI SDI API token is not configured',
            ];
        }

        $events = ['supplier-invoice', 'customer-notification', 'customer-invoice'];

        $callbacks = array_map(
            fn (string $event) => [
                'event' => $event,
                'url' => $webhookUrl,
                'auth_header' => $authHeader,
            ],
            $events,
        );

        try {
            /** @var Response $response */
            $response = $this->newRequest()->post(
                "{$this->baseUrl}/api_configurations",
                [
                    'fiscal_id' => $fiscalId,
                    'callbacks' => $callbacks,
                ],
            );

            $body = $response->json();

            Log::channel('fe-openapi')->info('OpenAPI SDI callback configuration', [
                'status' => $response->status(),
                'success' => $body['success'] ?? false,
                'fiscal_id' => $fiscalId,
                'webhook_url' => $webhookUrl,
                'events' => $events,
            ]);

            if ($response->successful() && ($body['success'] ?? false)) {
                return [
                    'success' => true,
                    'data' => $body['data'] ?? [],
                    'message' => 'API callbacks configured successfully',
                ];
            }

            return [
                'success' => false,
                'error' => $body['error'] ?? $response->status(),
                'message' => $body['message'] ?? 'Failed to configure API callbacks',
            ];
        } catch (\Exception $e) {
            Log::channel('fe-openapi')->error('OpenAPI SDI callback configuration failed', [
                'fiscal_id' => $fiscalId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'connection_failed',
                'message' => 'Failed to connect to OpenAPI: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Retrieve current API callback configurations for a fiscal ID
     */
    public function getApiConfigurations(string $fiscalId): array
    {
        $fiscalId = $this->normalizeFiscalId($fiscalId);

        if (! $this->isConfigured()) {
            return [
                'success' => false,
                'error' => 'Service not configured',
                'message' => 'OpenAPI SDI API token is not configured',
            ];
        }

        try {
            /** @var Response $response */
            $response = $this->newRequest()->get(
                "{$this->baseUrl}/api_configurations",
                [
                    'fiscal_id' => $fiscalId,
                ],
            );

            if ($response->successful()) {
                $body = $response->json();

                return [
                    'success' => true,
                    'data' => $body['data'] ?? [],
                ];
            }

            $body = $response->json();

            return [
                'success' => false,
                'error' => $response->status(),
                'message' => $body['message'] ?? 'Failed to retrieve API configurations',
            ];
        } catch (\Exception $e) {
            Log::channel('fe-openapi')->error('OpenAPI SDI API configurations retrieval failed', [
                'fiscal_id' => $fiscalId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'connection_failed',
                'message' => 'Failed to connect to OpenAPI: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Simulate a webhook event via the OpenAPI sandbox simulate-callbacks endpoint.
     *
     * Requires the api_configuration ID (not the type slug) per the updated spec.
     * Retrieve the config ID first via getApiConfigurations().
     */
    public function simulateWebhookEvent(
        string $type,
        array $payload,
    ): array {
        if (! $this->isConfigured()) {
            return [
                'success' => false,
                'error' => 'Service not configured',
                'message' => 'OpenAPI SDI API token is not configured',
            ];
        }

        if (! $this->isSandbox) {
            return [
                'success' => false,
                'error' => 'sandbox_only',
                'message' => 'Simulation is only available in sandbox mode',
            ];
        }

        $allowedTypes = ['supplier-invoice', 'customer-notification', 'customer-invoice'];

        if (! in_array($type, $allowedTypes)) {
            return [
                'success' => false,
                'error' => 'invalid_type',
                'message' => "Invalid simulation type: {$type}. Allowed: ".
                  implode(', ', $allowedTypes),
            ];
        }

        try {
            /** @var Response $response */
            $response = $this->newRequest()->post(
                "{$this->baseUrl}/simulate/{$type}",
                $payload,
            );

            $body = $response->json();

            Log::channel('fe-openapi')->info('OpenAPI SDI simulation request', [
                'type' => $type,
                'status' => $response->status(),
                'response' => $body,
            ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'message' => $body['message'] ?? 'Simulation triggered successfully',
                ];
            }

            return [
                'success' => false,
                'error' => $body['error'] ?? $response->status(),
                'message' => $body['message'] ?? 'Simulation failed',
            ];
        } catch (\Exception $e) {
            Log::channel('fe-openapi')->error('OpenAPI SDI simulation failed', [
                'type' => $type,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'connection_failed',
                'message' => 'Failed to connect to OpenAPI: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Get notifications for a specific invoice.
     *
     * Uses GET /notifications?invoice_uuid={uuid} per updated spec
     * (replaces legacy /invoices_notifications/{uuid}).
     */
    public function getInvoiceNotifications(string $uuid): array
    {
        if (! $this->isConfigured()) {
            return [
                'success' => false,
                'error' => 'Service not configured',
                'message' => 'OpenAPI SDI API token is not configured',
            ];
        }

        try {
            /** @var Response $response */
            $response = $this->newRequest()->get(
                "{$this->baseUrl}/invoices_notifications/{$uuid}",
            );

            $body = $response->json();

            if ($response->successful() && ($body['success'] ?? false)) {
                return [
                    'success' => true,
                    'notifications' => $body['data'] ?? [],
                ];
            }

            if ($response->status() === 404) {
                return [
                    'success' => false,
                    'error' => 'not_found',
                    'message' => 'Invoice not found',
                ];
            }

            return [
                'success' => false,
                'error' => $body['error'] ?? $response->status(),
                'message' => $body['message'] ?? 'Failed to retrieve notifications',
            ];
        } catch (\Exception $e) {
            Log::channel('fe-openapi')->error('OpenAPI SDI notifications retrieval failed', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'connection_failed',
                'message' => 'Failed to connect to OpenAPI: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Reactivate a suspended account after wallet topup.
     */
    public function reactivateAccount(string $fiscalId): array
    {
        $fiscalId = $this->normalizeFiscalId($fiscalId);

        if (! $this->isConfigured()) {
            return [
                'success' => false,
                'error' => 'Service not configured',
                'message' => 'OpenAPI SDI API token is not configured',
            ];
        }

        try {
            /** @var Response $response */
            $response = $this->newRequest()->patch(
                "{$this->baseUrl}/business_registry_configurations/{$fiscalId}/activate",
                ['active' => true],
            );

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json()['data'] ?? null,
                    'message' => 'Account reactivated successfully',
                ];
            }

            $body = $response->json();

            return [
                'success' => false,
                'error' => $response->status(),
                'message' => $body['message'] ?? 'Failed to reactivate account',
            ];
        } catch (\Exception $e) {
            Log::channel('fe-openapi')->error('OpenAPI SDI account reactivation failed', [
                'fiscal_id' => $fiscalId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'connection_failed',
                'message' => 'Failed to connect to OpenAPI: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Import an invoice received outside of SDI (e.g. from email or foreign supplier).
     *
     * The XML must be base64-encoded. The sdi_id is the ID assigned by the sender's SDI.
     */
    public function importExtraSdiInvoice(
        string $xmlContent,
        string $sdiId,
        string $filename,
    ): array {
        if (! $this->isConfigured()) {
            return [
                'success' => false,
                'error' => 'Service not configured',
                'message' => 'OpenAPI SDI API token is not configured',
            ];
        }

        try {
            /** @var Response $response */
            $response = $this->newRequest()->post(
                "{$this->baseUrl}/supplier_invoice_imports",
                [
                    'invoice' => base64_encode($xmlContent),
                    'sdi_id' => $sdiId,
                    'invoice_file_name' => $filename,
                ],
            );

            if ($response->successful()) {
                $body = $response->json();

                return [
                    'success' => true,
                    'uuids' => $body['data']['uuids'] ?? [],
                    'message' => 'Invoice imported successfully',
                ];
            }

            $body = $response->json();

            return [
                'success' => false,
                'error' => $response->status(),
                'message' => $body['message'] ?? 'Failed to import invoice',
            ];
        } catch (\Exception $e) {
            Log::channel('fe-openapi')->error('OpenAPI SDI extra-SDI invoice import failed', [
                'sdi_id' => $sdiId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'connection_failed',
                'message' => 'Failed to connect to OpenAPI: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Download invoice as PDF (for UI previews).
     */
    public function downloadInvoicePdf(string $uuid): array
    {
        if (! $this->isConfigured()) {
            return [
                'success' => false,
                'error' => 'Service not configured',
                'message' => 'OpenAPI SDI API token is not configured',
            ];
        }

        try {
            /** @var Response $response */
            $response = $this->newRequest()
                ->accept('application/pdf')
                ->get("{$this->baseUrl}/invoices_download/{$uuid}");

            if ($response->successful()) {
                return [
                    'success' => true,
                    'pdf' => $response->body(),
                    'content_type' => 'application/pdf',
                ];
            }

            if ($response->status() === 404) {
                return [
                    'success' => false,
                    'error' => 'not_found',
                    'message' => 'Invoice PDF not found',
                ];
            }

            return [
                'success' => false,
                'error' => $response->status(),
                'message' => 'Failed to download invoice PDF',
            ];
        } catch (\Exception $e) {
            Log::channel('fe-openapi')->error('OpenAPI SDI invoice PDF download failed', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'connection_failed',
                'message' => 'Failed to connect to OpenAPI: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Convert a FatturaElettronica JSON payload (snake_case keys from the OpenAPI response)
     * into a valid FatturaElettronica XML string that InvoiceXmlImportService can parse.
     */
    private function convertInvoicePayloadToXml(array $payload): string
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = false;

        $root = $dom->createElement('FatturaElettronica');
        $dom->appendChild($root);

        $this->appendArrayToXml($payload, $root, $dom);

        return $dom->saveXML();
    }

    /**
     * Recursively convert an array to XML child nodes, translating snake_case keys to PascalCase.
     */
    private function appendArrayToXml(array $data, \DOMElement $parent, \DOMDocument $dom): void
    {
        foreach ($data as $key => $value) {
            $tagName = is_string($key) ? $this->snakeToPascalCase($key) : $parent->tagName;

            if (is_array($value) && array_is_list($value)) {
                // Repeated elements (e.g. FatturaElettronicaBody, DettaglioLinee)
                foreach ($value as $item) {
                    $child = $dom->createElement($tagName);
                    $parent->appendChild($child);

                    if (is_array($item)) {
                        $this->appendArrayToXml($item, $child, $dom);
                    } else {
                        $child->appendChild($dom->createTextNode((string) ($item ?? '')));
                    }
                }
            } elseif (is_array($value)) {
                $child = $dom->createElement($tagName);
                $parent->appendChild($child);
                $this->appendArrayToXml($value, $child, $dom);
            } else {
                $child = $dom->createElement($tagName);
                $child->appendChild($dom->createTextNode((string) ($value ?? '')));
                $parent->appendChild($child);
            }
        }
    }

    private function newRequest(): PendingRequest
    {
        return Http::withToken($this->apiToken)
            ->connectTimeout(self::CONNECT_TIMEOUT_SECONDS)
            ->timeout(self::REQUEST_TIMEOUT_SECONDS);
    }

    /**
     * Convert snake_case to PascalCase: "dati_trasmissione" → "DatiTrasmissione"
     */
    private function snakeToPascalCase(string $snake): string
    {
        return str_replace(' ', '', ucwords(str_replace('_', ' ', $snake)));
    }
}
