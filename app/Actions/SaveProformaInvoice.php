<?php

namespace App\Actions;

use App\Enums\ProformaStatus;
use App\Models\FiscalDocument;
use App\Models\ProformaInvoice;
use App\Services\DocumentSequenceResolver;
use App\Services\Domain\FiscalDocumentMutationService;
use App\Settings\CompanySettings;
use App\Support\FiscalRegimePolicy;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveProformaInvoice
{
    public function __construct(
        private readonly FiscalDocumentMutationService $mutationService,
        private readonly CompanySettings $companySettings,
        private readonly DocumentSequenceResolver $sequenceResolver,
    ) {}

    /** @param array<string, mixed> $payload */
    public function create(array $payload): FiscalDocument
    {
        $sequenceId = $this->sequenceResolver->resolve('proforma')->id;
        [$header, $lines] = $this->prepare($payload, $sequenceId);

        return $this->mutationService->create([
            ...$header,
            'type' => 'proforma',
            'status' => ProformaStatus::Draft,
        ], $lines);
    }

    /** @param array<string, mixed> $payload */
    public function update(ProformaInvoice $invoice, array $payload): FiscalDocument
    {
        return DB::transaction(function () use ($invoice, $payload) {
            $lockedRecord = FiscalDocument::query()->lockForUpdate()->findOrFail($invoice->id);
            /** @var ProformaInvoice $lockedInvoice */
            $lockedInvoice = (new ProformaInvoice)->newFromBuilder($lockedRecord->getAttributes());
            $this->ensureEditable($lockedInvoice);
            [$header, $lines] = $this->prepare($payload, $lockedInvoice->sequence_id);

            return $this->mutationService->update($lockedInvoice, $header, $lines);
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{0: array<string, mixed>, 1: array<int, array<string, mixed>>}
     */
    private function prepare(array $payload, int $sequenceId): array
    {
        $normalized = FiscalRegimePolicy::normalizeDocumentPayload($payload, $this->companySettings->company_fiscal_regime);
        $lines = FiscalRegimePolicy::normalizeLinesForForfettario($payload['lines'], $this->companySettings->company_fiscal_regime);
        $normalized = FiscalRegimePolicy::normalizeStampDutyPayload(
            $normalized,
            $lines,
            $this->companySettings->company_fiscal_regime,
        );
        return [[
            'date' => $normalized['date'],
            'due_date' => $normalized['due_date'] ?? null,
            'contact_id' => $normalized['contact_id'],
            'sequence_id' => $sequenceId,
            'notes' => $this->nullIfBlank($normalized['notes'] ?? null),
            'withholding_tax_enabled' => $normalized['withholding_tax_enabled'] ?? false,
            'withholding_tax_percent' => ($normalized['withholding_tax_enabled'] ?? false) ? $this->nullIfBlank($normalized['withholding_tax_percent'] ?? null) : null,
            'fund_enabled' => $normalized['fund_enabled'] ?? false,
            'fund_percent' => ($normalized['fund_enabled'] ?? false) ? $this->nullIfBlank($normalized['fund_percent'] ?? null) : null,
            'fund_vat_rate' => ($normalized['fund_enabled'] ?? false) ? $this->nullIfBlank($normalized['fund_vat_rate'] ?? null) : null,
            'stamp_duty_applied' => $normalized['stamp_duty_applied'] ?? false,
            'stamp_duty_charged_to_customer' => $normalized['stamp_duty_charged_to_customer'] ?? false,
            'stamp_duty_amount' => ($normalized['stamp_duty_applied'] ?? false) ? FiscalRegimePolicy::STAMP_DUTY_AMOUNT_CENTS : 0,
            'payment_method' => $this->nullIfBlank($normalized['payment_method'] ?? null),
            'payment_terms' => $this->nullIfBlank($normalized['payment_terms'] ?? null),
            'bank_name' => $this->nullIfBlank($normalized['bank_name'] ?? null),
            'bank_iban' => $this->nullIfBlank($normalized['bank_iban'] ?? null),
        ], array_map($this->buildLinePayload(...), $lines)];
    }

    private function ensureEditable(ProformaInvoice $invoice): void
    {
        if (! in_array($invoice->status, [ProformaStatus::Draft, ProformaStatus::Sent], true) || $invoice->date?->year < now()->year) {
            throw ValidationException::withMessages(['invoice' => 'Questa proforma non è più modificabile.']);
        }
    }

    private function nullIfBlank(mixed $value): mixed
    {
        return $value === '' ? null : $value;
    }

    /** @param array<string, mixed> $line */
    private function buildLinePayload(array $line): array
    {
        $gross = (float) $line['quantity'] * (float) $line['unit_price'];
        $discount = filled($line['discount_percent'] ?? null) ? (float) $line['discount_percent'] : null;
        $total = $discount !== null && $discount > 0 ? $gross * (1 - $discount / 100) : $gross;

        return [
            'description' => $line['description'],
            'quantity' => (float) $line['quantity'],
            'unit_of_measure' => ($line['unit_of_measure'] ?? null) ?: null,
            'unit_price' => (int) round((float) $line['unit_price'] * 100),
            'discount_percent' => $discount,
            'discount_amount' => $discount !== null && $discount > 0 ? (int) round(($gross - $total) * 100) : null,
            'vat_rate' => $line['vat_rate'],
            'total' => (int) round($total * 100),
        ];
    }
}
