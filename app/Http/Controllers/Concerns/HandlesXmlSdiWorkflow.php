<?php

namespace App\Http\Controllers\Concerns;

use App\Enums\InvoiceStatus;
use App\Jobs\SendDocumentToSdiJob;
use App\Models\FiscalDocument;
use App\Models\SdiOutboundSubmission;
use App\Services\DocumentEventRecorder;
use App\Services\DocumentStorageService;
use App\Services\SdiSubmissionService;
use App\Services\XmlWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Throwable;

trait HandlesXmlSdiWorkflow
{
    protected function downloadXmlDocument(object $document, object $xmlService, XmlWorkflowService $xmlWorkflow)
    {
        $document->loadMissing(['contact', 'lines']);

        $xml = $document->xml_path
            ? app(DocumentStorageService::class)->getXml($document->xml_path)
            : null;

        $xml ??= $xmlService->generate($document);
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

        try {
            $xmlPath = $this->storeXmlSnapshot($document, $xml, $xmlService->generateFileName($document));
        } catch (Throwable $exception) {
            $message = 'XML valido ma non archiviabile: invio SDI bloccato finché lo storage documentale non è disponibile.';
            Log::error('Unable to persist validated XML snapshot', [
                'fiscal_document_id' => $document->id,
                'exception' => $exception,
            ]);

            if (! request()->expectsJson()) {
                return back()->withErrors(['action' => $message]);
            }

            return $this->workflowErrorResponse($document, $message);
        }

        $document->update(['status' => InvoiceStatus::XmlValidated, 'xml_path' => $xmlPath]);
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
        try {
            $xml = $document->xml_path
                ? app(DocumentStorageService::class)->getXml($document->xml_path)
                : null;

            if ($xml === null) {
                // Compatibility path for documents validated before snapshots existed.
                $xml = $xmlService->generate($document);
                $xmlPath = $this->storeXmlSnapshot($document, $xml, $xmlService->generateFileName($document));
                $document->update(['xml_path' => $xmlPath]);
            }
        } catch (Throwable $exception) {
            $message = 'XML non archiviabile: invio SDI bloccato finché lo storage documentale non è disponibile.';
            Log::error('Unable to retrieve or persist XML snapshot before SDI submission', [
                'fiscal_document_id' => $document->id,
                'exception' => $exception,
            ]);

            if (request()->expectsJson()) {
                return $this->workflowErrorResponse($document, $message);
            }

            return back()->withErrors(['action' => $message]);
        }

        $fileName = $xmlService->generateFileName($document);
        $fiscalDocument = $document instanceof FiscalDocument
            ? $document
            : FiscalDocument::withoutGlobalScopes()->findOrFail($document->id);

        $acquired = app(SdiSubmissionService::class)->acquireForSend($fiscalDocument, $xml);

        if ($acquired instanceof SdiOutboundSubmission === false) {
            $errorMessage = $acquired['error_message'] ?? 'Invio allo SDI fallito.';

            if (request()->expectsJson()) {
                return $this->workflowErrorResponse($document, $errorMessage);
            }

            return back()->withErrors(['action' => $errorMessage]);
        }

        $queuedMessage = 'Invio allo SDI accodato.';

        $this->runSdiSideEffect('record queued document event', function () use ($document, $acquired, $queuedMessage) {
            app(DocumentEventRecorder::class)->sdiQueued($document, $acquired->id, $queuedMessage);
        }, $document);

        SendDocumentToSdiJob::dispatch(
            $acquired->id,
            $fileName,
            $sentMessage,
            request()->user()?->id,
        )->afterResponse();

        $document->refresh();

        if (! request()->expectsJson()) {
            return back()->with('toast', [
                'type' => 'success',
                'title' => 'Operazione accodata',
                'message' => $queuedMessage,
                'duration' => 4500,
            ]);
        }

        return $this->workflowSuccessResponse($document, $queuedMessage);
    }

    private function storeXmlSnapshot(object $document, string $xml, string $filename): string
    {
        return app(DocumentStorageService::class)->storeXml(
            $xml,
            $this->documentStorageCategory($document),
            (int) $document->date->year,
            $document->public_id,
            $filename,
        );
    }

    private function documentStorageCategory(object $document): string
    {
        $category = match ($document->type) {
            'purchase' => 'purchase',
            'credit_note' => 'credit-notes',
            'self_invoice' => 'self-invoices',
            default => 'sales',
        };

        return $category;
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
            Log::channel('fe-openapi')->error('SDI send side effect failed while queueing submission', [
                'operation' => $operation,
                'fiscal_document_id' => $document->id ?? null,
                'sdi_uuid' => $document->sdi_uuid ?? null,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
