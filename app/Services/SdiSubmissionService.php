<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\SdiStatus;
use App\Enums\SdiSubmissionStatus;
use App\Models\EiOutboundLog;
use App\Models\FiscalDocument;
use App\Models\SdiOutboundSubmission;
use App\Sdi\SdiSubmissionCommand;
use App\Sdi\SdiSubmissionResult;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SdiSubmissionService
{
    public function __construct(
        private readonly BusinessFingerprintService $fingerprints,
        private readonly SdiUuidLinkService $uuidLinks,
        private readonly SdiSubmissionDriverSelector $driverSelector,
        private readonly SdiSubmissionDriverRegistry $drivers,
    ) {}

    /**
     * Acquire the outbound lock and create a dispatching submission without calling the provider.
     *
     * @return array{success: bool, submission: SdiOutboundSubmission, error_message?: string}|SdiOutboundSubmission
     */
    public function acquireForSend(FiscalDocument $document, string $xml): SdiOutboundSubmission|array
    {
        $fingerprint = $this->fingerprints->buildFromXml($xml);
        $driverKey = $this->driverSelector->selectForNewSubmission();

        return $this->acquire($document, $xml, $fingerprint, $driverKey);
    }

    /**
     * Submit an already-acquired dispatching submission to the provider and finalize local state.
     *
     * @return array{success: bool, submission: SdiOutboundSubmission, error_message?: string, sent_message?: string, outcome_unknown?: bool, local_persist_failed?: bool}
     */
    public function complete(SdiOutboundSubmission $submission, string $xml, string $fileName, string $sentMessage): array
    {
        $submission->refresh();

        if ($submission->status !== SdiSubmissionStatus::Dispatching) {
            return [
                'success' => false,
                'submission' => $submission,
                'error_message' => 'Questa submission SDI non è più in stato di invio.',
                'outcome_unknown' => $submission->status->blocksNewSubmission(),
            ];
        }

        $document = FiscalDocument::withoutGlobalScopes()->findOrFail($submission->fiscal_document_id);
        $fingerprint = $submission->business_fingerprint ?: $this->fingerprints->buildFromXml($xml);

        $providerResult = $this->drivers->get($submission->provider)->submit(new SdiSubmissionCommand(
            $xml,
            $submission->id,
            $submission->idempotency_key,
            $fileName,
        ));

        if (! $providerResult->accepted) {
            return $this->recordProviderFailure($submission, $providerResult);
        }

        $submission->update([
            'status' => SdiSubmissionStatus::ProviderAccepted,
            'provider_uuid' => $providerResult->remoteReference,
            'provider_file_id' => $providerResult->remoteFileId,
            'support_message' => $this->supportMessage($providerResult->message ?? $sentMessage),
            'provider_accepted_at' => now(),
        ]);

        try {
            $completedSubmission = DB::transaction(function () use ($document, $submission, $providerResult, $fingerprint, $sentMessage) {
                $lockedDocument = FiscalDocument::withoutGlobalScopes()->lockForUpdate()->findOrFail($document->id);
                $lockedSubmission = SdiOutboundSubmission::query()->lockForUpdate()->findOrFail($submission->id);

                $lockedDocument->update([
                    'status' => InvoiceStatus::Sent,
                    'sdi_status' => SdiStatus::Sent,
                    'sdi_uuid' => $lockedSubmission->provider_uuid,
                    'sdi_file_id' => $lockedSubmission->provider_file_id,
                    'sdi_message' => $lockedSubmission->support_message ?? $sentMessage,
                    'business_fingerprint' => $fingerprint,
                    'sdi_primary_channel' => 'outbound',
                ]);

                $outboundLog = EiOutboundLog::firstOrCreate([
                    'fiscal_document_id' => $lockedDocument->id,
                    'event_type' => 'sent',
                    'status' => SdiStatus::Sent->value,
                ], [
                    'source_uuid' => $lockedSubmission->provider_uuid,
                    'message' => $lockedSubmission->support_message ?? $sentMessage,
                    'business_fingerprint' => $fingerprint,
                    'raw_payload' => $this->minimalProviderPayload($providerResult),
                ]);

                if ($lockedSubmission->provider_uuid) {
                    $this->uuidLinks->linkOutbound($lockedDocument->id, $lockedSubmission->provider_uuid, $fingerprint, 'manual');
                }

                $lockedSubmission->update([
                    'status' => SdiSubmissionStatus::Completed,
                    'active_document_lock' => null,
                    'completed_at' => now(),
                ]);

                return $lockedSubmission->fresh(['fiscalDocument'])->setRelation('outboundLog', $outboundLog);
            });
        } catch (\Throwable) {
            $submission->forceFill([
                'status' => SdiSubmissionStatus::LocalPersistFailed,
                'support_message' => 'Provider accepted the document, but local finalization failed. Reconciliation is required.',
            ])->save();

            return [
                'success' => false,
                'submission' => $submission->fresh(),
                'error_message' => 'Il provider ha accettato il documento, ma Fatturino non ha completato il salvataggio. Non reinviare: è necessaria la riconciliazione.',
                'local_persist_failed' => true,
            ];
        }

        return [
            'success' => true,
            'submission' => $completedSubmission,
            'sent_message' => $completedSubmission->support_message ?? $sentMessage,
        ];
    }

    /**
     * Release a dispatching lock when the provider was never contacted.
     */
    public function abandonBeforeProvider(SdiOutboundSubmission $submission, string $message): void
    {
        $submission->refresh();

        if ($submission->status !== SdiSubmissionStatus::Dispatching) {
            return;
        }

        $submission->update([
            'status' => SdiSubmissionStatus::ProviderRejected,
            'active_document_lock' => null,
            'support_message' => $this->supportMessage($message),
        ]);
    }

    /**
     * @return array{success: bool, submission: SdiOutboundSubmission, error_message?: string, sent_message?: string, outcome_unknown?: bool, local_persist_failed?: bool}
     */
    public function send(FiscalDocument $document, string $xml, string $fileName, string $sentMessage): array
    {
        $submission = $this->acquireForSend($document, $xml);

        if ($submission instanceof SdiOutboundSubmission === false) {
            return $submission;
        }

        return $this->complete($submission, $xml, $fileName, $sentMessage);
    }

    private function acquire(FiscalDocument $document, string $xml, string $fingerprint, string $driverKey): SdiOutboundSubmission|array
    {
        try {
            return DB::transaction(function () use ($document, $xml, $fingerprint, $driverKey) {
                $lockedDocument = FiscalDocument::withoutGlobalScopes()->lockForUpdate()->findOrFail($document->id);

                if (! $lockedDocument->isSdiEditable() || $lockedDocument->status !== InvoiceStatus::XmlValidated) {
                    return [
                        'success' => false,
                        'submission' => new SdiOutboundSubmission,
                        'error_message' => 'Il documento non è più disponibile per l’invio SDI.',
                    ];
                }

                $active = SdiOutboundSubmission::query()
                    ->where('active_document_lock', (string) $lockedDocument->id)
                    ->first();

                if ($active) {
                    return [
                        'success' => false,
                        'submission' => $active,
                        'error_message' => 'Esiste già un invio SDI in corso o da riconciliare per questo documento.',
                    ];
                }

                return SdiOutboundSubmission::create([
                    'fiscal_document_id' => $lockedDocument->id,
                    'idempotency_key' => (string) Str::ulid(),
                    'provider' => $driverKey,
                    'status' => SdiSubmissionStatus::Dispatching,
                    'active_document_lock' => (string) $lockedDocument->id,
                    'xml_sha256' => hash('sha256', $xml),
                    'business_fingerprint' => $fingerprint,
                    'dispatch_started_at' => now(),
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            $active = SdiOutboundSubmission::query()
                ->where('active_document_lock', (string) $document->id)
                ->latest('id')
                ->first();

            return [
                'success' => false,
                'submission' => $active ?? new SdiOutboundSubmission,
                'error_message' => 'Esiste già un invio SDI in corso o da riconciliare per questo documento.',
            ];
        }
    }

    private function recordProviderFailure(SdiOutboundSubmission $submission, SdiSubmissionResult $providerResult): array
    {
        $isUnknown = $providerResult->outcomeIsUnknown();
        $message = $providerResult->message ?? 'Invio allo SDI fallito.';

        $submission->update([
            'status' => $isUnknown ? SdiSubmissionStatus::OutcomeUnknown : SdiSubmissionStatus::ProviderRejected,
            'active_document_lock' => $isUnknown ? $submission->fiscal_document_id : null,
            'provider_http_status' => $providerResult->httpStatus,
            'provider_error_code' => $providerResult->errorCode,
            'support_message' => $this->supportMessage($message),
        ]);

        return [
            'success' => false,
            'submission' => $submission->fresh(),
            'error_message' => $isUnknown
                ? 'Non è stato possibile determinare l’esito dell’invio. Fatturino non reinvierà il documento prima della riconciliazione.'
                : $message,
            'outcome_unknown' => $isUnknown,
        ];
    }

    private function minimalProviderPayload(SdiSubmissionResult $providerResult): array
    {
        return array_filter([
            'uuid' => $providerResult->remoteReference,
            'file_id' => $providerResult->remoteFileId,
            'http_status' => $providerResult->httpStatus,
            'error_code' => $providerResult->errorCode,
        ], static fn (mixed $value): bool => $value !== null);
    }

    private function supportMessage(string $message): string
    {
        return Str::limit(trim($message), 1000, '');
    }
}
