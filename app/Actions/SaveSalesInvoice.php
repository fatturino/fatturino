<?php

namespace App\Actions;

use App\Enums\InvoiceStatus;
use App\Models\FiscalDocument;
use App\Models\SalesInvoice;
use App\Services\DocumentSequenceResolver;
use App\Services\Domain\FiscalDocumentMutationService;
use App\Settings\CompanySettings;
use App\Support\FiscalRegimePolicy;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveSalesInvoice
{
    public function __construct(
        private readonly FiscalDocumentMutationService $mutationService,
        private readonly CompanySettings $companySettings,
        private readonly DocumentSequenceResolver $sequenceResolver,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function create(array $payload): FiscalDocument
    {
        [$header, $lines] = $this->prepare($payload, $this->sequenceResolver->resolve('sales')->id);

        $invoice = $this->mutationService->create([
            ...$header,
            'type' => 'sales',
            'status' => InvoiceStatus::Draft,
        ], $lines);

        return $invoice;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function update(SalesInvoice $invoice, array $payload): FiscalDocument
    {
        return DB::transaction(function () use ($invoice, $payload) {
            $lockedRecord = FiscalDocument::query()->lockForUpdate()->findOrFail($invoice->id);
            /** @var SalesInvoice $lockedInvoice */
            $lockedInvoice = (new SalesInvoice)->newFromBuilder($lockedRecord->getAttributes());
            $this->ensureEditable($lockedInvoice);
            [$header, $lines] = $this->prepare($payload, $lockedInvoice->sequence_id);

            $header['status'] = in_array($lockedInvoice->status, [InvoiceStatus::XmlValidated, InvoiceStatus::Sent], true)
                ? InvoiceStatus::Draft
                : $lockedInvoice->status;

            return $this->mutationService->update($lockedInvoice, $header, $lines);
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{0: array<string, mixed>, 1: array<int, array<string, mixed>>}
     */
    private function prepare(array $payload, int $sequenceId): array
    {
        $normalized = FiscalRegimePolicy::normalizeDocumentPayload(
            $payload,
            $this->companySettings->company_fiscal_regime,
        );
        $normalizedLines = FiscalRegimePolicy::normalizeLinesForForfettario(
            $payload['lines'],
            $this->companySettings->company_fiscal_regime,
        );
        $normalized = FiscalRegimePolicy::normalizeStampDutyPayload(
            $normalized,
            $normalizedLines,
            $this->companySettings->company_fiscal_regime,
        );

        return [[
            'date' => $normalized['date'],
            'due_date' => $normalized['due_date'] ?? null,
            'contact_id' => $normalized['contact_id'],
            'sequence_id' => $sequenceId,
            'document_type' => $normalized['document_type'],
            'notes' => $normalized['notes'] ?? null,
            'withholding_tax_enabled' => $normalized['withholding_tax_enabled'] ?? false,
            'withholding_tax_percent' => ($normalized['withholding_tax_enabled'] ?? false) ? ($normalized['withholding_tax_percent'] ?? null) : null,
            'fund_enabled' => $normalized['fund_enabled'] ?? false,
            'fund_type' => ($normalized['fund_enabled'] ?? false) ? ($normalized['fund_type'] ?? null) : null,
            'fund_percent' => ($normalized['fund_enabled'] ?? false) ? ($normalized['fund_percent'] ?? null) : null,
            'fund_vat_rate' => ($normalized['fund_enabled'] ?? false) ? ($normalized['fund_vat_rate'] ?? null) : null,
            'fund_has_deduction' => ($normalized['fund_enabled'] ?? false) && ($normalized['fund_has_deduction'] ?? false),
            'stamp_duty_applied' => $normalized['stamp_duty_applied'] ?? false,
            'stamp_duty_charged_to_customer' => $normalized['stamp_duty_charged_to_customer'] ?? false,
            'stamp_duty_amount' => ($normalized['stamp_duty_applied'] ?? false) ? FiscalRegimePolicy::STAMP_DUTY_AMOUNT_CENTS : 0,
            'payment_method' => $normalized['payment_method'] ?? null,
            'payment_terms' => $normalized['payment_terms'] ?? null,
            'bank_name' => $normalized['bank_name'] ?? null,
            'bank_iban' => $normalized['bank_iban'] ?? null,
            'vat_payability' => ($normalized['split_payment'] ?? false) ? 'S' : $normalized['vat_payability'],
            'split_payment' => $normalized['split_payment'] ?? false,
        ], array_map($this->buildLinePayload(...), $normalizedLines)];
    }

    private function ensureEditable(SalesInvoice $invoice): void
    {
        if (! $invoice->isSdiEditable()) {
            throw ValidationException::withMessages(['invoice' => 'Questa fattura non è più modificabile.']);
        }

        if ($invoice->date?->year < now()->year) {
            throw ValidationException::withMessages(['invoice' => 'Le fatture degli anni precedenti non sono modificabili.']);
        }
    }

    /** @param array<string, mixed> $line */
    private function buildLinePayload(array $line): array
    {
        $quantity = (float) $line['quantity'];
        $unitPrice = (float) $line['unit_price'];
        $gross = $quantity * $unitPrice;
        $discountPercent = filled($line['discount_percent'] ?? null) ? (float) $line['discount_percent'] : null;
        $total = $discountPercent !== null && $discountPercent > 0
            ? $gross * (1 - $discountPercent / 100)
            : $gross;

        return [
            'description' => $line['description'],
            'quantity' => $quantity,
            'unit_of_measure' => ($line['unit_of_measure'] ?? null) ?: null,
            'unit_price' => (int) round($unitPrice * 100),
            'discount_percent' => $discountPercent,
            'discount_amount' => $discountPercent !== null && $discountPercent > 0 ? (int) round(($gross - $total) * 100) : null,
            'vat_rate' => $line['vat_rate'],
            'total' => (int) round($total * 100),
        ];
    }
}
