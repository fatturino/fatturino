<?php

namespace App\Actions;

use App\Enums\InvoiceStatus;
use App\Models\CreditNote;
use App\Models\FiscalDocument;
use App\Services\DocumentSequenceResolver;
use App\Services\Domain\FiscalDocumentMutationService;
use App\Settings\CompanySettings;
use App\Support\FiscalRegimePolicy;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveCreditNote
{
    public function __construct(
        private readonly FiscalDocumentMutationService $mutationService,
        private readonly CompanySettings $companySettings,
        private readonly DocumentSequenceResolver $sequenceResolver,
    ) {}

    /** @param array<string, mixed> $payload */
    public function create(array $payload): FiscalDocument
    {
        $sequenceId = $this->sequenceResolver->resolve('credit_note')->id;
        [$header, $lines] = $this->prepare($payload, $sequenceId);

        return $this->mutationService->create([...$header, 'type' => 'credit_note', 'status' => InvoiceStatus::Draft], $lines);
    }

    /** @param array<string, mixed> $payload */
    public function update(CreditNote $creditNote, array $payload): FiscalDocument
    {
        return DB::transaction(function () use ($creditNote, $payload) {
            $record = FiscalDocument::query()->lockForUpdate()->findOrFail($creditNote->id);
            /** @var CreditNote $locked */
            $locked = (new CreditNote)->newFromBuilder($record->getAttributes());
            if (! $locked->isSdiEditable() || $locked->date?->year < now()->year) {
                throw ValidationException::withMessages(['creditNote' => 'Questa nota di credito non è più modificabile.']);
            }

            [$header, $lines] = $this->prepare($payload, $locked->sequence_id);
            $header['status'] = in_array($locked->status, [InvoiceStatus::XmlValidated, InvoiceStatus::Sent], true) ? InvoiceStatus::Draft : $locked->status;

            return $this->mutationService->update($locked, $header, $lines);
        });
    }

    /** @return array{0: array<string, mixed>, 1: array<int, array<string, mixed>>} */
    private function prepare(array $payload, int $sequenceId): array
    {
        $regime = $this->companySettings->company_fiscal_regime;
        $notes = FiscalRegimePolicy::requiresForfettarioLegalNotice($regime)
            ? FiscalRegimePolicy::appendForfettarioLegalNotices($payload['notes'] ?? null)
            : $this->nullIfBlank($payload['notes'] ?? null);
        $lines = FiscalRegimePolicy::normalizeLinesForForfettario($payload['lines'], $regime);

        return [[
            'date' => $payload['date'],
            'contact_id' => $payload['contact_id'],
            'sequence_id' => $sequenceId,
            'document_type' => 'TD04',
            'related_invoice_number' => $this->nullIfBlank($payload['related_invoice_number'] ?? null),
            'related_invoice_date' => $payload['related_invoice_date'] ?? null,
            'notes' => $notes,
        ], array_map($this->buildLinePayload(...), $lines)];
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
