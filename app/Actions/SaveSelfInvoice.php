<?php

namespace App\Actions;

use App\Enums\InvoiceStatus;
use App\Models\FiscalDocument;
use App\Models\SelfInvoice;
use App\Services\DocumentSequenceResolver;
use App\Services\Domain\DocumentNumberingService;
use App\Services\Domain\FiscalDocumentMutationService;
use App\Services\Domain\SelfInvoiceDuplicateGuard;
use App\Settings\CompanySettings;
use App\Support\FiscalRegimePolicy;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveSelfInvoice
{
    public function __construct(
        private readonly FiscalDocumentMutationService $mutationService,
        private readonly DocumentNumberingService $numbering,
        private readonly CompanySettings $companySettings,
        private readonly DocumentSequenceResolver $sequenceResolver,
        private readonly SelfInvoiceDuplicateGuard $duplicates,
    ) {}

    /** @param array<string, mixed> $payload */
    public function create(array $payload): FiscalDocument
    {
        $this->ensureAllowed();
        $this->duplicates->ensureUnique($payload);

        try {
            $sequence = $this->sequenceResolver->resolve('self_invoice');
            $sequenceId = $sequence->id;
            $numbering = $this->numbering->reserve($sequence, $payload['date']);
            [$header, $lines] = $this->prepare($payload, $sequenceId);

            $invoice = $this->mutationService->create([
                ...$header,
                'type' => 'self_invoice',
                'status' => InvoiceStatus::Draft,
            ], $lines, $numbering);
            $selfInvoice = (new SelfInvoice)->newFromBuilder($invoice->getAttributes());
            $selfInvoice->setRelation('payments', $invoice->payments);
            $selfInvoice->markAsPaidOnIssueDate();

            return $selfInvoice->fresh(['lines', 'payments']);
        } catch (UniqueConstraintViolationException $exception) {
            if ($this->duplicates->isDuplicateKeyViolation($exception)) {
                throw $this->duplicates->duplicateException(
                    (string) $payload['related_invoice_number'],
                    $this->duplicates->normalizeDate($payload['related_invoice_date']),
                );
            }

            throw $exception;
        }
    }

    /** @param array<string, mixed> $payload */
    public function update(SelfInvoice $invoice, array $payload): FiscalDocument
    {
        $this->ensureAllowed();

        try {
            return DB::transaction(function () use ($invoice, $payload) {
                $lockedRecord = FiscalDocument::query()->lockForUpdate()->findOrFail($invoice->id);
                /** @var SelfInvoice $lockedInvoice */
                $lockedInvoice = (new SelfInvoice)->newFromBuilder($lockedRecord->getAttributes());
                if (! $lockedInvoice->isSdiEditable() || $lockedInvoice->date?->year < now()->year) {
                    throw ValidationException::withMessages(['invoice' => 'Questa autofattura non è più modificabile.']);
                }

                if ($lockedInvoice->reversal_of_id !== null) {
                    throw ValidationException::withMessages(['invoice' => 'La bozza di storno è generata dall’autofattura originale e non può essere modificata.']);
                }

                [$header, $lines] = $this->prepare($payload, $lockedInvoice->sequence_id);
                $this->duplicates->ensureUnique($header, $lockedInvoice->id);
                $header['status'] = in_array($lockedInvoice->status, [InvoiceStatus::XmlValidated, InvoiceStatus::Sent], true)
                    ? InvoiceStatus::Draft
                    : $lockedInvoice->status;

                return $this->mutationService->update($lockedInvoice, $header, $lines);
            });
        } catch (UniqueConstraintViolationException $exception) {
            if ($this->duplicates->isDuplicateKeyViolation($exception)) {
                throw $this->duplicates->duplicateException(
                    (string) $payload['related_invoice_number'],
                    $this->duplicates->normalizeDate($payload['related_invoice_date']),
                );
            }

            throw $exception;
        }
    }

    /** @return array{0: array<string, mixed>, 1: array<int, array<string, mixed>>} */
    private function prepare(array $payload, int $sequenceId): array
    {
        return [[
            'date' => $payload['date'],
            'due_date' => $payload['due_date'] ?? null,
            'contact_id' => $payload['contact_id'],
            'sequence_id' => $sequenceId,
            'document_type' => $payload['document_type'],
            'related_invoice_number' => $this->nullIfBlank($payload['related_invoice_number'] ?? null),
            'related_invoice_date' => $payload['related_invoice_date'] ?? null,
            'notes' => $this->nullIfBlank($payload['notes'] ?? null),
        ], array_map($this->buildLinePayload(...), $payload['lines'])];
    }

    private function ensureAllowed(): void
    {
        if (! FiscalRegimePolicy::supportsSelfInvoices($this->companySettings->company_fiscal_regime, $this->companySettings->rf19_self_invoices_enabled)) {
            throw ValidationException::withMessages(['invoice' => 'Le autofatture sono disabilitate per il regime fiscale corrente.']);
        }
    }

    private function nullIfBlank(mixed $value): mixed
    {
        return $value === '' ? null : $value;
    }

    /** @param array<string, mixed> $line */
    private function buildLinePayload(array $line): array
    {
        $total = (float) $line['quantity'] * (float) $line['unit_price'];

        return ['description' => $line['description'], 'quantity' => (float) $line['quantity'], 'unit_of_measure' => ($line['unit_of_measure'] ?? null) ?: null, 'unit_price' => (int) round((float) $line['unit_price'] * 100), 'discount_percent' => null, 'discount_amount' => null, 'vat_rate' => $line['vat_rate'], 'total' => (int) round($total * 100)];
    }
}
