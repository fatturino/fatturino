<?php

namespace App\Http\Controllers\Concerns;

use App\Enums\InvoiceStatus;
use App\Enums\SdiStatus;
use App\Models\EiOutboundLog;
use App\Services\DocumentEventRecorder;
use App\Services\PostHogTelemetryService;
use App\Services\SdiSubmissionService;
use App\Services\XmlWorkflowService;
use App\Support\InvoiceAuditDispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Throwable;

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
                return $this->workflowErrorResponse($document, $notEditableMessage);
            }

            return back()->withErrors(['action' => $notEditableMessage]);
        }

        if (! $document->status->canValidateXml()) {
            if (request()->expectsJson()) {
                return $this->workflowErrorResponse($document, $invalidStateMessage);
            }

            return back()->withErrors(['action' => $invalidStateMessage]);
        }

        $document->loadMissing(['contact', 'lines']);
        $xml = $xmlService->generate($document);

        $validationResult = $xmlWorkflow->validate($xml);
        if (! $validationResult['valid']) {
            $errors = $validationResult['errors'] ?? ['Validazione XML fallita.'];
            app(DocumentEventRecorder::class)->xmlValidated($document, false, implode(' ', $errors));

            if (! request()->expectsJson()) {
                return back()->withErrors(['action' => implode(' ', $errors)]);
            }

            return $this->workflowErrorResponse($document, 'Validazione XML fallita.', $errors);
        }

        $document->update(['status' => InvoiceStatus::XmlValidated]);
        $document->refresh();
        app(DocumentEventRecorder::class)->xmlValidated($document, true, 'XML validato con successo.');

        if (! request()->expectsJson()) {
            return back()->with('toast', [
                'type' => 'success',
                'title' => 'Operazione completata',
                'message' => 'XML validato con successo.',
                'duration' => 4500,
            ]);
        }

        return $this->workflowSuccessResponse($document, 'XML validato con successo.');
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
                return $this->workflowErrorResponse($document, $notEditableMessage);
            }

            return back()->withErrors(['action' => $notEditableMessage]);
        }

        if (! $document->status->canSendToSdi()) {
            if (request()->expectsJson()) {
                return $this->workflowErrorResponse($document, $invalidStateMessage);
            }

            return back()->withErrors(['action' => $invalidStateMessage]);
        }

        $document->loadMissing(['contact', 'lines']);
        $xml = $xmlService->generate($document);
        $fileName = $xmlService->generateFileName($document);
        $submissionResult = app(SdiSubmissionService::class)->send(
            $document,
            $xml,
            $fileName,
            $xmlWorkflow,
            $sentMessage,
        );

        if (! ($submissionResult['success'] ?? false)) {
            $errorMessage = $submissionResult['error_message'] ?? 'Invio allo SDI fallito.';
            $outboundLog = null;

            $this->runSdiSideEffect('record outbound send failure log', function () use (&$outboundLog, $document, $errorMessage, $submissionResult) {
                $outboundLog = EiOutboundLog::create([
                    'fiscal_document_id' => $document->id,
                    'event_type' => ! empty($submissionResult['outcome_unknown']) ? 'send_outcome_unknown' : 'send_failed',
                    'status' => SdiStatus::Error->value,
                    'message' => $errorMessage,
                    'raw_payload' => array_filter([
                        'submission_id' => $submissionResult['submission']->id ?? null,
                        'submission_status' => $submissionResult['submission']->status?->value ?? null,
                    ]),
                ]);
            }, $document);

            $this->runSdiSideEffect('record failed document event', function () use ($document, $outboundLog, $errorMessage) {
                app(DocumentEventRecorder::class)->record($document, [
                    'event_type' => 'sdi_sent',
                    'channel' => 'sdi',
                    'status' => 'failed',
                    'title' => 'Invio SDI fallito',
                    'message' => $errorMessage,
                    'technical_reference_type' => 'ei_outbound_log',
                    'technical_reference_id' => $outboundLog?->id,
                ]);
            }, $document);

            if (request()->expectsJson()) {
                return $this->workflowErrorResponse($document, $errorMessage);
            }

            return back()->withErrors(['action' => $errorMessage]);
        }

        $providerId = $xmlWorkflow->providerId();
        $outboundLog = EiOutboundLog::query()
            ->where('fiscal_document_id', $document->id)
            ->where('event_type', 'sent')
            ->where('status', SdiStatus::Sent->value)
            ->first();

        $this->runSdiSideEffect('record document event', function () use ($document, $outboundLog, $submissionResult, $sentMessage) {
            app(DocumentEventRecorder::class)->sdiSent(
                $document,
                $outboundLog?->id,
                $submissionResult['sent_message'] ?? $sentMessage
            );
        }, $document);

        $this->runSdiSideEffect('dispatch audit', function () use ($document, $providerId) {
            InvoiceAuditDispatcher::dispatch($document, 'sdi_sent', [
                'provider' => $providerId,
                'uuid' => $document->sdi_uuid,
            ]);
        }, $document);

        $this->runSdiSideEffect('capture telemetry', function () use ($document, $providerId) {
            app(PostHogTelemetryService::class)->capture(
                'document_sent_to_sdi',
                array_merge(
                    app(PostHogTelemetryService::class)->documentProperties($document),
                    ['provider' => $providerId]
                ),
                request()->user()
            );
        }, $document);

        $document->refresh();

        if (! request()->expectsJson()) {
            return back()->with('toast', [
                'type' => 'success',
                'title' => 'Operazione completata',
                'message' => $sentMessage,
                'duration' => 4500,
            ]);
        }

        return $this->workflowSuccessResponse($document, $sentMessage);
    }

    protected function workflowSuccessResponse(object $document, string $message): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'document' => $this->workflowDocumentPayload($document),
        ]);
    }

    protected function workflowErrorResponse(object $document, string $message, array $errors = []): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => $message,
            'errors' => $errors,
            'document' => $this->workflowDocumentPayload($document),
        ], 422);
    }

    protected function workflowDocumentPayload(object $document): array
    {
        $status = $document->status;
        $sdiStatus = $document->sdi_status;

        return [
            'id' => $document->id,
            'status' => $status instanceof \BackedEnum ? $status->value : $status,
            'sdi_status' => $sdiStatus instanceof \BackedEnum ? $sdiStatus->value : $sdiStatus,
            'is_sdi_editable' => $document->isSdiEditable(),
        ];
    }

    private function runSdiSideEffect(string $operation, callable $callback, object $document): void
    {
        try {
            $callback();
        } catch (Throwable $exception) {
            Log::channel('fe-openapi')->error('SDI send side effect failed after successful provider submission', [
                'operation' => $operation,
                'fiscal_document_id' => $document->id ?? null,
                'sdi_uuid' => $document->sdi_uuid ?? null,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
