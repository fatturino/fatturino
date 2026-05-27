<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Enums\SdiStatus;
use App\Models\EiInboundLog;
use App\Models\EiOutboundLog;
use App\Models\FiscalDocument;
use App\Models\PurchaseInvoice;
use App\Models\SelfInvoice;
use App\Settings\InvoiceSettings;
use App\Settings\OpenApiSettings;
use Illuminate\Support\Facades\Log;

class SdiInboundProcessor
{
    public function __construct(
        private readonly BusinessFingerprintService $businessFingerprintService,
        private readonly InboundEventFingerprintService $inboundEventFingerprintService,
        private readonly SdiUuidLinkService $sdiUuidLinkService,
        private readonly SdiCostGuardService $sdiCostGuardService,
        private readonly OpenApiSdiService $openApiSdiService,
    ) {}

    public function process(string $eventName, array $data, EiInboundLog $inboundLog, OpenApiSettings $settings): array
    {
        $sourceUuid = $this->inboundEventFingerprintService->extractSourceUuid($eventName, $data);

        $businessFingerprint = $this->extractBusinessFingerprint($eventName, $data);
        $eventFingerprint = $this->inboundEventFingerprintService->build($eventName, $data, $businessFingerprint);

        if (EiInboundLog::query()->where('event_fingerprint', $eventFingerprint)->where('id', '!=', $inboundLog->id)->exists()) {
            $inboundLog->update([
                'source_uuid' => $sourceUuid,
                'notification_type' => $data['notification']['type'] ?? null,
                'business_fingerprint' => $businessFingerprint,
                'processing_status' => 'duplicate',
                'processed_at' => now(),
            ]);

            return ['status' => 'duplicate'];
        }

        $inboundLog->update([
            'source_uuid' => $sourceUuid,
            'notification_type' => $data['notification']['type'] ?? null,
            'business_fingerprint' => $businessFingerprint,
            'event_fingerprint' => $eventFingerprint,
            'attempts' => $inboundLog->attempts + 1,
        ]);

        try {
            $result = match ($eventName) {
                'supplier-invoice' => $this->processSupplierInvoice($data, $inboundLog, $settings),
                'customer-notification' => $this->processCustomerNotification($data, $inboundLog),
                'customer-invoice' => $this->processCustomerInvoice($data, $inboundLog),
                default => ['status' => 'ignored'],
            };

            $inboundLog->update([
                'processing_status' => in_array($result['status'], ['ok', 'duplicate', 'ignored'], true) ? 'processed' : 'failed',
                'processed_at' => now(),
                'linked_fiscal_document_id' => $result['fiscal_document_id'] ?? $inboundLog->linked_fiscal_document_id,
                'error_message' => $result['error'] ?? null,
            ]);

            return $result;
        } catch (\Throwable $e) {
            Log::channel('fe-openapi')->error('SDI inbound processing failed', [
                'event' => $eventName,
                'error' => $e->getMessage(),
            ]);

            $inboundLog->update([
                'processing_status' => 'failed_retryable',
                'error_message' => $e->getMessage(),
            ]);

            return ['status' => 'error', 'error' => $e->getMessage()];
        }
    }

    private function processSupplierInvoice(array $data, EiInboundLog $inboundLog, OpenApiSettings $settings): array
    {
        $uuid = $data['invoice']['uuid'] ?? '';
        if ($uuid === '') {
            return ['status' => 'error', 'error' => 'Missing supplier invoice UUID'];
        }

        if (! $this->sdiCostGuardService->shouldFetchInvoice($uuid, 'supplier-invoice')) {
            $existing = $this->sdiUuidLinkService->resolveDocumentByUuid($uuid)
                ?? FiscalDocument::withoutGlobalScopes()->where('sdi_uuid', $uuid)->first();

            return [
                'status' => 'duplicate',
                'fiscal_document_id' => $existing?->id,
            ];
        }

        $downloadResult = $this->openApiSdiService->downloadInvoiceXml($uuid);
        if (! ($downloadResult['success'] ?? false)) {
            $level = $settings->sandbox ? 'warning' : 'error';
            Log::channel('fe-openapi')->{$level}('Supplier invoice download failed', ['uuid' => $uuid]);

            return ['status' => 'error', 'error' => 'Failed to download supplier invoice'];
        }

        $xml = $downloadResult['xml'];
        $fingerprint = $this->businessFingerprintService->buildFromXml($xml);

        $documentType = $this->extractXmlField($xml, 'TipoDocumento');
        $isSelfInvoiceType = in_array($documentType, ['TD17', 'TD18', 'TD19', 'TD28', 'TD29'], true);

        if ($isSelfInvoiceType) {
            $resolvedSelf = SelfInvoice::withoutGlobalScopes()
                ->where('business_fingerprint', $fingerprint)
                ->orWhere('number', $this->extractXmlField($xml, 'Numero'))
                ->first();

            if ($resolvedSelf) {
                $resolvedSelf->update([
                    'sdi_uuid' => $resolvedSelf->sdi_uuid ?: $uuid,
                    'business_fingerprint' => $resolvedSelf->business_fingerprint ?: $fingerprint,
                    'sdi_status' => SdiStatus::Delivered,
                    'sdi_message' => 'Consegnata (ricevuta come acquisto)',
                    'sdi_primary_channel' => 'inbound',
                ]);

                if ($resolvedSelf->payment_status !== PaymentStatus::Paid) {
                    $docDate = $this->extractDocumentDate($xml);
                    if ($docDate) {
                        $exists = $resolvedSelf->payments()->where('paid_at', $docDate)->exists();
                        if (! $exists) {
                            $resolvedSelf->payments()->create([
                                'amount' => $resolvedSelf->total_gross,
                                'paid_at' => $docDate,
                            ]);
                            $resolvedSelf->recalculatePaymentStatus();
                        }
                    }
                }

                $this->sdiUuidLinkService->linkInbound($resolvedSelf->id, $uuid, $fingerprint, 'self_invoice_roundtrip');

                return ['status' => 'ok', 'fiscal_document_id' => $resolvedSelf->id];
            }
        }

        $existing = PurchaseInvoice::withoutGlobalScopes()->where('sdi_uuid', $uuid)->first();
        if (! $existing) {
            $existing = PurchaseInvoice::withoutGlobalScopes()->where('business_fingerprint', $fingerprint)->first();
        }

        if ($existing) {
            $existing->update([
                'sdi_uuid' => $existing->sdi_uuid ?: $uuid,
                'business_fingerprint' => $existing->business_fingerprint ?: $fingerprint,
                'sdi_primary_channel' => 'inbound',
            ]);

            $this->sdiUuidLinkService->linkInbound($existing->id, $uuid, $fingerprint, 'reconcile');

            return ['status' => 'duplicate', 'fiscal_document_id' => $existing->id];
        }

        $invoiceSettings = app(InvoiceSettings::class);
        $sequenceId = $invoiceSettings->default_sequence_purchase ?: null;

        $importService = app(InvoiceXmlImportService::class);
        $importService->importXml($xml, $sequenceId, 'purchase', [
            'sdi_uuid' => $uuid,
            'sdi_file_id' => $data['invoice']['file_id'] ?? null,
            'sdi_filename' => $data['invoice']['filename'] ?? null,
            'sdi_received_at' => $data['invoice']['created_at'] ?? null,
            'sdi_payload' => $data['invoice']['payload'] ?? null,
        ]);

        $importErrors = $importService->getErrors();
        if (! empty($importErrors)) {
            return ['status' => 'error', 'error' => implode('; ', $importErrors)];
        }

        $created = PurchaseInvoice::withoutGlobalScopes()->where('sdi_uuid', $uuid)->first();
        if (! $created) {
            $created = PurchaseInvoice::withoutGlobalScopes()->where('business_fingerprint', $fingerprint)->latest('id')->first();
        }

        if (! $created) {
            return ['status' => 'error', 'error' => 'Import completed but invoice not resolvable'];
        }

        $created->update([
            'business_fingerprint' => $fingerprint,
            'sdi_primary_channel' => 'inbound',
        ]);

        $this->sdiUuidLinkService->linkInbound($created->id, $uuid, $fingerprint, 'manual');

        return ['status' => 'ok', 'fiscal_document_id' => $created->id];
    }

    private function processCustomerNotification(array $data, EiInboundLog $inboundLog): array
    {
        $notification = $data['notification'] ?? [];
        $invoiceUuid = '';
        $notificationType = '';

        if (is_array($notification)) {
            $invoiceUuid = (string) ($notification['invoice_uuid'] ?? $data['uuid'] ?? '');
            $notificationType = (string) ($notification['type'] ?? '');
        } elseif (is_string($notification)) {
            $invoiceUuid = (string) ($data['uuid'] ?? '');
            $notificationType = $notification;
        }

        if ($invoiceUuid === '' || $notificationType === '') {
            return ['status' => 'error', 'error' => 'Missing notification UUID or type'];
        }

        $invoice = $this->sdiUuidLinkService->resolveDocumentByUuid($invoiceUuid);

        if (! $invoice && $inboundLog->business_fingerprint) {
            $invoice = FiscalDocument::withoutGlobalScopes()
                ->where('business_fingerprint', $inboundLog->business_fingerprint)
                ->first();
        }

        if (! $invoice) {
            $download = $this->openApiSdiService->downloadInvoiceXml($invoiceUuid);
            if (($download['success'] ?? false) && isset($download['xml'])) {
                $fingerprint = $this->businessFingerprintService->buildFromXml($download['xml']);
                $invoice = FiscalDocument::withoutGlobalScopes()->where('business_fingerprint', $fingerprint)->first();
            }
        }

        if (! $invoice) {
            return ['status' => 'error', 'error' => 'Invoice not found for notification'];
        }

        $newStatus = SdiStatus::fromNotificationType($notificationType);
        if (! $newStatus) {
            return ['status' => 'error', 'error' => 'Unknown notification type'];
        }

        $message = $this->buildNotificationMessage($notificationType, $notification);

        $invoice->update([
            'sdi_status' => $newStatus->value,
            'sdi_message' => $message,
        ]);

        EiOutboundLog::firstOrCreate(
            [
                'fiscal_document_id' => $invoice->id,
                'event_type' => $notificationType,
                'status' => $newStatus->value,
            ],
            [
                'source_uuid' => $invoiceUuid,
                'message' => $message,
                'business_fingerprint' => $invoice->business_fingerprint,
                'raw_payload' => $data,
            ]
        );

        $this->sdiUuidLinkService->linkInbound($invoice->id, $invoiceUuid, $invoice->business_fingerprint ?? '-', 'reconcile');

        return ['status' => 'ok', 'fiscal_document_id' => $invoice->id];
    }

    private function processCustomerInvoice(array $data, EiInboundLog $inboundLog): array
    {
        $invoiceUuid = $data['invoice']['uuid'] ?? '';
        if ($invoiceUuid === '') {
            return ['status' => 'ignored'];
        }

        $invoice = $this->sdiUuidLinkService->resolveDocumentByUuid($invoiceUuid)
            ?? FiscalDocument::withoutGlobalScopes()->where('sdi_uuid', $invoiceUuid)->first();

        if (! $invoice && $inboundLog->business_fingerprint) {
            $invoice = FiscalDocument::withoutGlobalScopes()->where('business_fingerprint', $inboundLog->business_fingerprint)->first();
        }

        if (! $invoice) {
            return ['status' => 'error', 'error' => 'Invoice not found for customer-invoice'];
        }

        EiOutboundLog::firstOrCreate(
            [
                'fiscal_document_id' => $invoice->id,
                'event_type' => 'received',
                'status' => SdiStatus::Sent->value,
            ],
            [
                'source_uuid' => $invoiceUuid,
                'message' => __('app.invoices.sdi_log_received_by_sdi'),
                'business_fingerprint' => $invoice->business_fingerprint,
                'raw_payload' => $data,
            ]
        );

        $this->sdiUuidLinkService->linkOutbound($invoice->id, $invoiceUuid, $invoice->business_fingerprint ?? '-', 'manual');

        return ['status' => 'ok', 'fiscal_document_id' => $invoice->id];
    }

    private function extractBusinessFingerprint(string $eventName, array $data): ?string
    {
        $payload = $data['invoice']['payload'] ?? null;
        if (is_array($payload) && ! empty($payload)) {
            return $this->businessFingerprintService->buildFromPayload($payload);
        }

        if ($eventName === 'customer-notification') {
            return null;
        }

        return null;
    }

    private function extractXmlField(string $xml, string $tag): ?string
    {
        if (preg_match('/<'.$tag.'[^>]*>([^<]+)<\/'.$tag.'>/', $xml, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }

    private function extractDocumentDate(string $xml): ?string
    {
        if (preg_match('/<DatiGeneraliDocumento>(.*?)<\/DatiGeneraliDocumento>/s', $xml, $block)) {
            if (preg_match('/<Data>([^<]+)<\/Data>/', $block[1], $dateMatch)) {
                return trim($dateMatch[1]);
            }
        }

        return null;
    }

    private function buildNotificationMessage(string $type, array $notification): string
    {
        $descriptions = [
            'NS' => 'Notifica di Scarto',
            'RC' => 'Ricevuta di Consegna',
            'MC' => 'Mancata Consegna',
            'DT' => 'Decorrenza Termini',
            'NE' => 'Esito Committente',
            'AT' => 'Attestazione',
            'EC' => 'Esito Cessionario',
        ];

        $message = $descriptions[$type] ?? $type;

        $errorList = $notification['message']['lista_errori']['Errore'] ?? null;
        if ($errorList) {
            $code = $errorList['Codice'] ?? '';
            $desc = $errorList['Descrizione'] ?? '';
            if ($code || $desc) {
                $message .= " - {$code}: {$desc}";
            }
        }

        $sdiId = $notification['message']['identificativo_sdi'] ?? null;
        if ($sdiId) {
            $message .= " (SDI: {$sdiId})";
        }

        return $message;
    }
}
