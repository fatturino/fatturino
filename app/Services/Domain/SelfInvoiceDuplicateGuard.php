<?php

namespace App\Services\Domain;

use App\Models\SelfInvoice;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class SelfInvoiceDuplicateGuard
{
    public const KEY_COLUMN = 'self_invoice_duplicate_key';

    /** @param array<string, mixed> $attributes */
    public function ensureUnique(array $attributes, ?int $ignoreId = null): void
    {
        $key = $this->keyFromAttributes($attributes);
        if ($key === null) {
            return;
        }

        $query = SelfInvoice::query()
            ->where('contact_id', $attributes['contact_id'])
            ->whereDate('related_invoice_date', $this->normalizeDate($attributes['related_invoice_date']));

        if ($ignoreId !== null) {
            $query->whereKeyNot($ignoreId);
        }

        $duplicate = $query->get()->first(fn (SelfInvoice $invoice): bool => $this->keyFor($invoice) === $key);

        if ($duplicate !== null) {
            throw $this->duplicateException(
                (string) $attributes['related_invoice_number'],
                $this->normalizeDate($attributes['related_invoice_date']),
            );
        }
    }

    /** @param array<string, mixed> $attributes */
    public function keyFromAttributes(array $attributes): ?string
    {
        if (empty($attributes['contact_id']) || empty($attributes['document_type']) || empty($attributes['related_invoice_number']) || empty($attributes['related_invoice_date'])) {
            return null;
        }

        return hash('sha256', implode("\x1F", [
            (string) $attributes['contact_id'],
            $this->normalizeDocumentType((string) $attributes['document_type']),
            $this->normalizeInvoiceNumber((string) $attributes['related_invoice_number']),
            $this->normalizeDate($attributes['related_invoice_date']),
        ]));
    }

    public function keyFor(SelfInvoice $invoice): ?string
    {
        return $this->keyFromAttributes([
            'contact_id' => $invoice->contact_id,
            'document_type' => $invoice->document_type,
            'related_invoice_number' => $invoice->related_invoice_number,
            'related_invoice_date' => $invoice->related_invoice_date,
        ]);
    }

    public function duplicateException(string $number, string $date): ValidationException
    {
        return ValidationException::withMessages([
            'invoice' => "La fattura del fornitore {$number} del {$date} risulta già associata a un'altra autofattura.",
        ]);
    }

    public function isDuplicateKeyViolation(\Throwable $exception): bool
    {
        return str_contains(strtolower($exception->getMessage()), self::KEY_COLUMN);
    }

    public function normalizeDocumentType(string $value): string
    {
        return strtoupper(trim($value));
    }

    public function normalizeInvoiceNumber(string $value): string
    {
        $collapsed = preg_replace('/\s+/u', ' ', trim($value)) ?? '';

        return mb_strtolower($collapsed, 'UTF-8');
    }

    public function normalizeDate(mixed $value): string
    {
        return $value instanceof CarbonInterface
            ? $value->toDateString()
            : Carbon::parse($value)->toDateString();
    }
}
