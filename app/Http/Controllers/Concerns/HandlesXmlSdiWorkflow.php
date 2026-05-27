<?php

namespace App\Http\Controllers\Concerns;

use App\Enums\InvoiceStatus;
use App\Enums\SdiStatus;
use App\Models\EiOutboundLog;
use App\Services\BusinessFingerprintService;
use App\Services\SdiUuidLinkService;
use App\Services\XmlWorkflowService;
use App\Support\InvoiceAuditDispatcher;
use Illuminate\Http\JsonResponse;

trait HandlesXmlSdiWorkflow
{
    protected function downloadXmlDocument(object $document, object $xmlService, XmlWorkflowService $xmlWorkflow)
    {
        $document->loadMissing(['contact', 'lines']);

        $xml = $xmlService->generate($document);
        $fileName = $xmlService->generateFileName($document);

        return $xmlWorkflow->downloadResponse($xml, $fileName);
    }

    protected function validateXmlDocument(
        object $document,
        object $xmlService,
        XmlWorkflowService $xmlWorkflow,
        string $notEditableMessage,
        string $invalidStateMessage
    ): JsonResponse {
        if (! $document->isSdiEditable()) {
            return response()->json(['success' => false, 'error' => $notEditableMessage], 422);
        }

        if (! $document->status->canValidateXml()) {
            return response()->json(['success' => false, 'error' => $invalidStateMessage], 422);
        }

        $document->loadMissing(['contact', 'lines']);
        $xml = $xmlService->generate($document);

        $validationResult = $xmlWorkflow->validate($xml);
        if (! $validationResult['valid']) {
            return response()->json([
                'success' => false,
                'errors' => $validationResult['errors'] ?? ['Validazione XML fallita.'],
            ], 422);
        }

        $document->update(['status' => InvoiceStatus::XmlValidated]);

        return response()->json(['success' => true, 'message' => 'XML validato con successo.']);
    }

    protected function sendXmlDocumentToSdi(
        object $document,
        object $xmlService,
        XmlWorkflowService $xmlWorkflow,
        string $notEditableMessage,
        string $invalidStateMessage,
        string $sentMessage
    ): JsonResponse {
        if (! $document->isSdiEditable()) {
            return response()->json(['success' => false, 'error' => $notEditableMessage], 422);
        }

        if (! $document->status->canSendToSdi()) {
            return response()->json(['success' => false, 'error' => $invalidStateMessage], 422);
        }

        $document->loadMissing(['contact', 'lines']);
        $xml = $xmlService->generate($document);
        $fileName = $xmlService->generateFileName($document);
        $sendResult = $xmlWorkflow->send($xml, $fileName);

        if (! ($sendResult['success'] ?? false)) {
            $errorMessage = $sendResult['error_message'] ?? 'Invio allo SDI fallito.';

            EiOutboundLog::create([
                'fiscal_document_id' => $document->id,
                'event_type' => 'send_failed',
                'status' => SdiStatus::Error->value,
                'message' => $errorMessage,
                'raw_payload' => $sendResult,
            ]);

            return response()->json(['success' => false, 'error' => $errorMessage], 422);
        }

        $fingerprint = app(BusinessFingerprintService::class)->buildFromXml($xml);

        $document->update([
            'status' => InvoiceStatus::Sent,
            'sdi_status' => SdiStatus::Sent,
            'sdi_uuid' => $sendResult['uuid'] ?? $document->sdi_uuid,
            'sdi_file_id' => $sendResult['file_id'] ?? $document->sdi_file_id,
            'sdi_message' => $sendResult['message'] ?? $sentMessage,
            'business_fingerprint' => $fingerprint,
            'sdi_primary_channel' => 'outbound',
        ]);

        EiOutboundLog::firstOrCreate([
            'fiscal_document_id' => $document->id,
            'event_type' => 'sent',
            'status' => SdiStatus::Sent->value,
        ], [
            'source_uuid' => $document->sdi_uuid,
            'message' => $sendResult['message'] ?? $sentMessage,
            'business_fingerprint' => $fingerprint,
            'raw_payload' => $sendResult,
        ]);

        if (! empty($document->sdi_uuid)) {
            app(SdiUuidLinkService::class)->linkOutbound($document->id, $document->sdi_uuid, $fingerprint, 'manual');
        }

        InvoiceAuditDispatcher::dispatch($document, 'sdi_sent', [
            'provider' => $xmlWorkflow->providerId(),
            'uuid' => $document->sdi_uuid,
        ]);

        return response()->json(['success' => true, 'message' => $sentMessage]);
    }
}
