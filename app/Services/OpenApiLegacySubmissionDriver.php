<?php

namespace App\Services;

use App\Contracts\SdiProvider;
use App\Contracts\SdiSubmissionDriver;
use App\Sdi\SdiSubmissionCommand;
use App\Sdi\SdiSubmissionResult;
use Throwable;

class OpenApiLegacySubmissionDriver implements SdiSubmissionDriver
{
    public function __construct(private readonly SdiProvider $provider) {}

    public function key(): string
    {
        return 'openapi_legacy';
    }

    public function submit(SdiSubmissionCommand $command): SdiSubmissionResult
    {
        try {
            $result = $this->provider->sendInvoice($command->xml, $command->fileName);
        } catch (Throwable) {
            return SdiSubmissionResult::unknown('La connessione al provider è stata interrotta prima di ricevere un esito.');
        }

        $remoteReference = $this->remoteReference($result['uuid'] ?? null);

        if (($result['success'] ?? false) === true && $remoteReference !== null) {
            return SdiSubmissionResult::accepted(
                $remoteReference,
                $result['file_id'] ?? null,
                $result['message'] ?? null,
            );
        }

        if (($result['success'] ?? false) === true) {
            return SdiSubmissionResult::unknown('Il provider non ha restituito un riferimento remoto valido.');
        }

        if (($result['outcome_unknown'] ?? false) === true) {
            return SdiSubmissionResult::unknown($result['error_message'] ?? null, $result['http_status'] ?? null);
        }

        return SdiSubmissionResult::rejected(
            $result['error_message'] ?? null,
            $result['error_code'] ?? null,
            $result['http_status'] ?? null,
        );
    }

    private function remoteReference(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value !== '' ? $value : null;
    }
}
