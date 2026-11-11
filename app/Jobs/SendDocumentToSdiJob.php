<?php

namespace App\Jobs;

use App\Enums\SdiStatus;
use App\Enums\SdiSubmissionStatus;
use App\Models\EiOutboundLog;
use App\Models\FiscalDocument;
use App\Models\SdiOutboundSubmission;
use App\Models\User;
use App\Services\DocumentEventRecorder;
use App\Services\DocumentStorageService;
use App\Services\InAppNotificationDispatcher;
use App\Services\PostHogTelemetryService;
use App\Services\SdiSubmissionService;
use App\Support\InvoiceAuditDispatcher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendDocumentToSdiJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Never retry: a second attempt after a provider call risks a double fiscal submit.
     */
    public int $tries = 1;

    public function __construct(
        public readonly int $submissionId,
        public readonly string $fileName,
        public readonly string $sentMessage,
        public readonly ?int $actingUserId = null,
    ) {}

    public function handle(
        SdiSubmissionService $submissions,
        DocumentStorageService $storage,
        DocumentEventRecorder $events,
        InAppNotificationDispatcher $notifications,
        PostHogTelemetryService $telemetry,
    ): void {
        $submission = SdiOutboundSubmission::query()->find($this->submissionId);

        if ($submission === null) {
            return;
        }

        if ($submission->status !== SdiSubmissionStatus::Dispatching) {
            return;
        }

        $document = FiscalDocument::withoutGlobalScopes()->find($submission->fiscal_document_id);

        if ($document === null) {
            $submissions->abandonBeforeProvider($submission, 'Documento non trovato prima dell’invio SDI.');

            return;
        }

        try {
            $xml = $document->xml_path
                ? $storage->getXml($document->xml_path)
                : null;

            if ($xml === null || $xml === '') {
                $message = 'XML non disponibile: invio SDI annullato.';
                $submissions->abandonBeforeProvider($submission, $message);
                $this->recordFailure($document, $submission->fresh(), $message, false, $events, $notifications);

                return;
            }
        } catch (Throwable $exception) {
            $message = 'XML non leggibile: invio SDI annullato.';
            Log::error('Unable to load XML snapshot before queued SDI submission', [
                'fiscal_document_id' => $document->id,
                'submission_id' => $submission->id,
                'exception' => $exception,
            ]);
            $submissions->abandonBeforeProvider($submission, $message);
            $this->recordFailure($document, $submission->fresh(), $message, false, $events, $notifications);

            return;
        }

        $result = $submissions->complete($submission, $xml, $this->fileName, $this->sentMessage);
        $completedSubmission = $result['submission'];
        $document->refresh();

        if (! ($result['success'] ?? false)) {
            $this->recordFailure(
                $document,
                $completedSubmission,
                $result['error_message'] ?? 'Invio allo SDI fallito.',
                ! empty($result['outcome_unknown']) || ! empty($result['local_persist_failed']),
                $events,
                $notifications,
            );

            return;
        }

        $this->recordSuccess($document, $completedSubmission, $result['sent_message'] ?? $this->sentMessage, $events, $notifications, $telemetry);
    }

    public function failed(?Throwable $exception): void
    {
        $submission = SdiOutboundSubmission::query()->find($this->submissionId);

        if ($submission === null || $submission->status !== SdiSubmissionStatus::Dispatching) {
            return;
        }

        // Fail closed: the provider may already have been contacted before the worker crashed.
        $submission->update([
            'status' => SdiSubmissionStatus::OutcomeUnknown,
            'active_document_lock' => $submission->fiscal_document_id,
            'support_message' => 'Invio SDI interrotto nel worker. Non reinviare prima della riconciliazione.',
        ]);

        $document = FiscalDocument::withoutGlobalScopes()->find($submission->fiscal_document_id);

        if ($document === null) {
            return;
        }

        $this->recordFailure(
            $document,
            $submission->fresh(),
            'Invio SDI interrotto. Non reinviare prima della riconciliazione.',
            true,
            app(DocumentEventRecorder::class),
            app(InAppNotificationDispatcher::class),
        );
    }

    private function recordSuccess(
        FiscalDocument $document,
        SdiOutboundSubmission $submission,
        string $sentMessage,
        DocumentEventRecorder $events,
        InAppNotificationDispatcher $notifications,
        PostHogTelemetryService $telemetry,
    ): void {
        $outboundLog = EiOutboundLog::query()
            ->where('fiscal_document_id', $document->id)
            ->where('event_type', 'sent')
            ->where('status', SdiStatus::Sent->value)
            ->first();

        $this->runSideEffect('record document event', function () use ($events, $document, $outboundLog, $sentMessage) {
            $events->sdiSent($document, $outboundLog?->id, $sentMessage);
        }, $document);

        $this->runSideEffect('dispatch audit', function () use ($document, $submission) {
            InvoiceAuditDispatcher::dispatch($document, 'sdi_sent', [
                'provider' => $submission->provider,
                'uuid' => $document->sdi_uuid,
            ]);
        }, $document);

        $this->runSideEffect('capture telemetry', function () use ($telemetry, $document, $submission) {
            $user = $this->actingUserId ? User::query()->find($this->actingUserId) : null;
            $telemetry->capture(
                'document_sent_to_sdi',
                array_merge(
                    $telemetry->documentProperties($document),
                    ['provider' => $submission->provider]
                ),
                $user
            );
        }, $document);

        $this->runSideEffect('notify send accepted', function () use ($notifications, $document, $submission, $sentMessage) {
            $notifications->sdiSendAccepted($document, $submission, $sentMessage);
        }, $document);
    }

    private function recordFailure(
        FiscalDocument $document,
        SdiOutboundSubmission $submission,
        string $errorMessage,
        bool $uncertain,
        DocumentEventRecorder $events,
        InAppNotificationDispatcher $notifications,
    ): void {
        $outboundLog = null;

        $this->runSideEffect('record outbound send failure log', function () use (&$outboundLog, $document, $errorMessage, $submission, $uncertain) {
            $outboundLog = EiOutboundLog::create([
                'fiscal_document_id' => $document->id,
                'event_type' => $uncertain ? 'send_outcome_unknown' : 'send_failed',
                'status' => SdiStatus::Error->value,
                'message' => $errorMessage,
                'raw_payload' => array_filter([
                    'submission_id' => $submission->id,
                    'submission_status' => $submission->status?->value,
                    'provider_error_code' => $submission->provider_error_code,
                    'provider_http_status' => $submission->provider_http_status,
                ], static fn (mixed $value): bool => $value !== null && $value !== ''),
            ]);
        }, $document);

        $this->runSideEffect('record failed document event', function () use ($events, $document, $outboundLog, $errorMessage, $submission, $uncertain) {
            $events->record($document, [
                'event_type' => 'sdi_sent',
                'channel' => 'sdi',
                'status' => 'failed',
                'title' => $uncertain ? 'Invio SDI da verificare' : 'Invio SDI fallito',
                'message' => $errorMessage,
                'error_message' => $submission->provider_error_code,
                'technical_reference_type' => 'ei_outbound_log',
                'technical_reference_id' => $outboundLog?->id,
            ]);
        }, $document);

        $this->runSideEffect('notify send failed', function () use ($notifications, $document, $submission, $errorMessage, $uncertain) {
            $notifications->sdiSendFailed($document, $submission, $errorMessage, $uncertain);
        }, $document);
    }

    private function runSideEffect(string $operation, callable $callback, FiscalDocument $document): void
    {
        try {
            $callback();
        } catch (Throwable $exception) {
            Log::channel('fe-openapi')->error('SDI send side effect failed after queued submission', [
                'operation' => $operation,
                'fiscal_document_id' => $document->id,
                'sdi_uuid' => $document->sdi_uuid,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
