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
use Illuminate\Http\RedirectResponse;

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
    ): JsonResponse|RedirectResponse {
        if (! $document->isSdiEditable()) {
            if (request()->expectsJson()) {
                return response()->json(['success' => false, 'error' => $notEditableMessage], 422);
            }

            return back()->withErrors(['action' => $notEditableMessage]);
        }

        if (! $document->status->canValidateXml()) {
            if (request()->expectsJson()) {
                return response()->json(['success' => false, 'error' => $invalidStateMessage], 422);
            }

            return back()->withErrors(['action' => $invalidStateMessage]);
        }

        $document->loadMissing(['contact', 'lines']);
        $xml = $xmlService->generate($document);

        $validationResult = $xmlWorkflow->validate($xml);
        if (! $validationResult['valid']) {
            $errors = $validationResult['errors'] ?? ['Validazione XML fallita.'];

            if (! request()->expectsJson()) {
                return back()->withErrors(['action' => implode(' ', $errors)]);
            }

            return response()->json([
                'success' => false,
                'errors' => $errors,
            ], 422);
        }

        $document->update(['status' => InvoiceStatus::XmlValidated]);

        if (! request()->expectsJson()) {
            return back()->with('toast', [
                'type' => 'success',
                'title' => 'Operazione completata',
                'message' => 'XML validato con successo.',
                'duration' => 4500,
            ]);
        }

        return response()->json(['success' => true, 'message' => 'XML validato con successo.']);
    }

    protected function sendXmlDocumentToSdi(
        object $document,
        object $xmlService,
        XmlWorkflowService $xmlWorkflow,
        string $notEditableMessage,
        string $invalidStateMessage,
        string $sentMessage
    ): JsonResponse|RedirectResponse {
        if (! $document->isSdiEditable()) {
            if (request()->expectsJson()) {
                return response()->json(['success' => false, 'error' => $notEditableMessage], 422);
            }

            return back()->withErrors(['action' => $notEditableMessage]);
        }

        if (! $document->status->canSendToSdi()) {
            if (request()->expectsJson()) {
                return response()->json(['success' => false, 'error' => $invalidStateMessage], 422);
            }

            return back()->withErrors(['action' => $invalidStateMessage]);
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

            if (request()->expectsJson()) {
                return response()->json(['success' => false, 'error' => $errorMessage], 422);
            }

            return back()->withErrors(['action' => $errorMessage]);
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

        if (! request()->expectsJson()) {
            return back()->with('toast', [
                'type' => 'success',
                'title' => 'Operazione completata',
                'message' => $sentMessage,
                'duration' => 4500,
            ]);
        }

        return response()->json(['success' => true, 'message' => $sentMessage]);
    }
}
