<?php

namespace App\Services;

use App\Contracts\SdiSubmissionDriver;
use App\Sdi\SdiSubmissionCommand;
use App\Sdi\SdiSubmissionResult;
use App\Settings\OpenApiSettings;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

class OpenApiV1SubmissionDriver implements SdiSubmissionDriver
{
    private const CONNECT_TIMEOUT_SECONDS = 5;

    private const REQUEST_TIMEOUT_SECONDS = 20;

    public function __construct(private readonly OpenApiSettings $settings) {}

    public function key(): string
    {
        return 'openapi_v1';
    }

    public function submit(SdiSubmissionCommand $command): SdiSubmissionResult
    {
        if ($this->settings->api_token === '') {
            return SdiSubmissionResult::unknown('Il token OpenAPI SDI non è configurato.');
        }

        $baseUrl = $this->settings->sandbox
            ? config('fe-openapi.sandbox_url')
            : config('fe-openapi.production_url');

        try {
            /** @var Response $response */
            $response = Http::withToken($this->settings->api_token)
                ->connectTimeout(self::CONNECT_TIMEOUT_SECONDS)
                ->timeout(self::REQUEST_TIMEOUT_SECONDS)
                ->withHeaders([
                    'Content-Type' => 'application/xml',
                    'Accept' => 'application/json',
                ])
                ->withBody($command->xml, 'application/xml')
                ->post("{$baseUrl}/invoices");
        } catch (Throwable) {
            return SdiSubmissionResult::unknown('La connessione al provider è stata interrotta prima di ricevere un esito.');
        }

        $body = $response->json();

        if ($response->status() === 200 && $this->isSuccessEnvelope($body)) {
            return SdiSubmissionResult::accepted(
                trim($body['data']['uuid']),
                $this->optionalString($body['data']['file_id'] ?? null),
                'Il provider ha accettato il documento.',
            );
        }

        if (in_array($response->status(), [400, 402, 422], true) && $this->isDocumentedRejectionEnvelope($body)) {
            return SdiSubmissionResult::rejected(
                'Il provider ha rifiutato il documento prima dell’accettazione.',
                null,
                $response->status(),
            );
        }

        return SdiSubmissionResult::unknown('Non è stato possibile determinare l’esito dell’invio.', $response->status());
    }

    private function isSuccessEnvelope(mixed $body): bool
    {
        return is_array($body)
            && ($body['success'] ?? null) === true
            && isset($body['data'])
            && is_array($body['data'])
            && $this->remoteReference($body['data']['uuid'] ?? null) !== null;
    }

    private function isDocumentedRejectionEnvelope(mixed $body): bool
    {
        return is_array($body)
            && ($body['success'] ?? null) === false
            && isset($body['message'])
            && is_string($body['message'])
            && trim($body['message']) !== ''
            && array_key_exists('error', $body)
            && $this->errorCode($body['error']) !== null
            && array_key_exists('data', $body)
            && $body['data'] === null;
    }

    private function remoteReference(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value !== '' ? $value : null;
    }

    private function optionalString(mixed $value): ?string
    {
        if (! is_string($value) && ! is_int($value) && ! is_float($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    private function errorCode(mixed $value): ?string
    {
        if (! is_string($value) && ! is_int($value) && ! is_float($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value !== '' && mb_strlen($value) <= 100 ? $value : null;
    }
}
