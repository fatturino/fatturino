<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\SdiStatus;
use App\Enums\SdiSubmissionStatus;
use App\Models\EiOutboundLog;
use App\Models\FiscalDocument;
use App\Models\SdiOutboundSubmission;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class SdiSubmissionService
{
    public function __construct(
        private readonly BusinessFingerprintService $fingerprints,
        private readonly SdiUuidLinkService $uuidLinks,
    ) {}

    /**
     * @return array{success: bool, submission: SdiOutboundSubmission, error_message?: string, sent_message?: string, outcome_unknown?: bool, local_persist_failed?: bool}
     */
    public function send(FiscalDocument $document, string $xml, string $fileName, XmlWorkflowService $workflow, string $sentMessage): array
    {
        $fingerprint = $this->fingerprints->buildFromXml($xml);
        $submission = $this->acquire($document, $xml, $fingerprint);

        if ($submission instanceof SdiOutboundSubmission === false) {
            return $submission;
        }

        try {
            $providerResult = $workflow->send($xml, $fileName);
        } catch (Throwable $exception) {
            $providerResult = [
                'success' => false,
                'outcome_unknown' => true,
                'error_message' => 'La connessione al provider è stata interrotta prima di ricevere un esito.',
            ];
        }

        if (! ($providerResult['success'] ?? false)) {
            return $this->recordProviderFailure($submission, $providerResult);
        }

        $submission->update([
            'status' => SdiSubmissionStatus::ProviderAccepted,
            'provider_uuid' => $providerResult['uuid'] ?? null,
            'provider_file_id' => $providerResult['file_id'] ?? null,
            'support_message' => $this->supportMessage($providerResult['message'] ?? $sentMessage),
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
        } catch (Throwable $exception) {
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

    private function acquire(FiscalDocument $document, string $xml, string $fingerprint): SdiOutboundSubmission|array
    {
        try {
            return DB::transaction(function () use ($document, $xml, $fingerprint) {
                $lockedDocument = FiscalDocument::withoutGlobalScopes()->lockForUpdate()->findOrFail($document->id);

                if (! $lockedDocument->isSdiEditable() || $lockedDocument->status !== InvoiceStatus::XmlValidated) {
                    return [
                        'success' => false,
                        'submission' => new SdiOutboundSubmission(),
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
                    'provider' => 'pending',
                    'status' => SdiSubmissionStatus::Pending,
                    'active_document_lock' => (string) $lockedDocument->id,
                    'xml_sha256' => hash('sha256', $xml),
                    'business_fingerprint' => $fingerprint,
                ]);
            });
        } catch (QueryException) {
            $active = SdiOutboundSubmission::query()
                ->where('active_document_lock', (string) $document->id)
                ->latest('id')
                ->first();

            return [
                'success' => false,
                'submission' => $active ?? new SdiOutboundSubmission(),
                'error_message' => 'Esiste già un invio SDI in corso o da riconciliare per questo documento.',
            ];
        }
    }

    /** @param array<string, mixed> $providerResult */
    private function recordProviderFailure(SdiOutboundSubmission $submission, array $providerResult): array
    {
        $isUnknown = (bool) ($providerResult['outcome_unknown'] ?? false);
        $message = $providerResult['error_message'] ?? 'Invio allo SDI fallito.';

        $submission->update([
            'status' => $isUnknown ? SdiSubmissionStatus::OutcomeUnknown : SdiSubmissionStatus::ProviderRejected,
            'active_document_lock' => $isUnknown ? $submission->fiscal_document_id : null,
            'provider_http_status' => $providerResult['http_status'] ?? null,
            'provider_error_code' => $providerResult['error_code'] ?? null,
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

    /** @param array<string, mixed> $providerResult */
    private function minimalProviderPayload(array $providerResult): array
    {
        return array_filter([
            'uuid' => $providerResult['uuid'] ?? null,
            'file_id' => $providerResult['file_id'] ?? null,
            'http_status' => $providerResult['http_status'] ?? null,
            'error_code' => $providerResult['error_code'] ?? null,
        ], static fn(mixed $value): bool => $value !== null);
    }

    private function supportMessage(string $message): string
    {
        return Str::limit(trim($message), 1000, '');
    }
}
