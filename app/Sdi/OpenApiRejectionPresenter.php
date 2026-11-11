<?php

namespace App\Sdi;

/**
 * Builds a safe, Italian user-facing rejection message from a documented OpenAPI
 * rejection envelope without propagating arbitrary remote content.
 */
final class OpenApiRejectionPresenter
{
    private const DETAIL_MAX_LENGTH = 280;

    private const UNSAFE_DETAIL_PATTERN = '/wallet|credit|billing|bearer|token|api[_-]?key|password|secret|SENSITIVE_/i';

    /**
     * @param  array<string, mixed>  $body
     * @return array{message: string, error_code: ?string}
     */
    public function present(int $httpStatus, array $body): array
    {
        $errorCode = $this->numericErrorCode($body['error'] ?? null);
        $remoteMessage = is_string($body['message'] ?? null) ? trim($body['message']) : '';

        $message = match (true) {
            $httpStatus === 400 && $errorCode === '230' => $this->validationMessage($remoteMessage),
            $httpStatus === 402 && $errorCode === '611' => 'Credito insufficiente sul servizio SDI. Verifica il piano o il wallet del provider, poi riprova.',
            $httpStatus === 422 && $errorCode === '802' => 'XML non valido o malformato. Rigenera il documento e riprova l’invio.',
            default => $this->genericMessage($errorCode),
        };

        return [
            'message' => $message,
            'error_code' => $errorCode,
        ];
    }

    private function validationMessage(string $remoteMessage): string
    {
        $base = 'Il documento non supera i controlli del provider. Controlla i dati obbligatori e correggi il documento.';
        $detail = $this->safeValidationDetail($remoteMessage);

        return $detail === null ? $base : $base.' Dettaglio: '.$detail;
    }

    private function genericMessage(?string $errorCode): string
    {
        if ($errorCode === null) {
            return 'Il provider ha rifiutato il documento prima dell’accettazione.';
        }

        return 'Il provider ha rifiutato il documento prima dell’accettazione (codice '.$errorCode.').';
    }

    private function safeValidationDetail(string $remoteMessage): ?string
    {
        if ($remoteMessage === '' || preg_match(self::UNSAFE_DETAIL_PATTERN, $remoteMessage) === 1) {
            return null;
        }

        $detail = preg_replace('/^An error occurred:\s*/i', '', $remoteMessage) ?? $remoteMessage;
        $detail = trim(preg_replace('/\s+/', ' ', $detail) ?? $detail);

        if ($detail === '' || mb_strlen($detail) > self::DETAIL_MAX_LENGTH) {
            return null;
        }

        // Keep only details that look like field/validation hints, not free-form blobs.
        if (! preg_match('/[._:]/', $detail) && ! preg_match('/\b(blank|required|invalid|missing|obbligatori[oa]?|mancante|non valido)\b/i', $detail)) {
            return null;
        }

        return $detail;
    }

    private function numericErrorCode(mixed $value): ?string
    {
        if (! is_string($value) && ! is_int($value) && ! is_float($value)) {
            return null;
        }

        $value = trim((string) $value);

        return preg_match('/^\d{1,10}$/', $value) === 1 ? $value : null;
    }
}
