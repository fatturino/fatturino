<?php

namespace App\Http\Controllers\Api;

use App\Enums\InvoiceStatus;
use App\Enums\VatPayability;
use App\Http\Controllers\Controller;
use App\Services\Domain\FiscalDocumentMutationService;
use App\Settings\CompanySettings;
use App\Support\FiscalRegimePolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SalesInvoiceCreateController extends Controller
{
    public function store(Request $request, FiscalDocumentMutationService $mutationService): JsonResponse
    {
        $validated = $request->validate([
            'contact_id' => 'required|exists:contacts,id',
            'sequence_id' => 'required|exists:sequences,id',
            'date' => 'required|date',
            'due_date' => 'nullable|date',
            'document_type' => 'required|string',
            'notes' => 'nullable|string',
            'withholding_tax_enabled' => 'boolean',
            'withholding_tax_percent' => [
                'nullable',
                'numeric',
                'min:0',
                'max:100',
                'required_if:withholding_tax_enabled,true',
            ],
            'fund_enabled' => 'boolean',
            'fund_type' => 'nullable|string',
            'fund_percent' => [
                'nullable',
                'numeric',
                'min:0',
                'max:100',
                'required_if:fund_enabled,true',
            ],
            'fund_vat_rate' => [
                'nullable',
                'string',
                'required_if:fund_enabled,true',
            ],
            'fund_has_deduction' => 'boolean',
            'stamp_duty_applied' => 'boolean',
            'payment_method' => 'nullable|string',
            'payment_terms' => 'nullable|string',
            'bank_name' => 'nullable|string',
            'bank_iban' => 'nullable|string',
            'vat_payability' => ['required', 'string', Rule::in(array_column(VatPayability::options(), 'id'))],
            'split_payment' => 'boolean',
            'lines' => 'required|array|min:1',
            'lines.*.description' => 'required|string',
            'lines.*.quantity' => 'required|numeric|min:0.01',
            'lines.*.unit_of_measure' => 'nullable|string',
            'lines.*.unit_price' => 'required|numeric|min:0',
            'lines.*.discount_percent' => 'nullable|numeric|min:0|max:100',
            'lines.*.vat_rate' => 'required|string',
        ]);
        $companySettings = app(CompanySettings::class);
        $normalized = FiscalRegimePolicy::normalizeDocumentPayload($validated, $companySettings->company_fiscal_regime);
        $normalizedLines = FiscalRegimePolicy::normalizeLinesForForfettario($validated['lines'], $companySettings->company_fiscal_regime);

        $invoice = $mutationService->create([
            'date' => $normalized['date'],
            'due_date' => $normalized['due_date'] ?? null,
            'contact_id' => $normalized['contact_id'],
            'sequence_id' => $normalized['sequence_id'],
            'status' => InvoiceStatus::Draft,
            'type' => 'sales',
            'document_type' => $normalized['document_type'],
            'notes' => $normalized['notes'] ?? null,
            'withholding_tax_enabled' => $normalized['withholding_tax_enabled'] ?? false,
            'withholding_tax_percent' => ($normalized['withholding_tax_enabled'] ?? false)
                ? ($normalized['withholding_tax_percent'] ?? null)
                : null,
            'fund_enabled' => $normalized['fund_enabled'] ?? false,
            'fund_type' => ($normalized['fund_enabled'] ?? false) ? ($normalized['fund_type'] ?? null) : null,
            'fund_percent' => ($normalized['fund_enabled'] ?? false) ? ($normalized['fund_percent'] ?? null) : null,
            'fund_vat_rate' => ($normalized['fund_enabled'] ?? false) ? ($normalized['fund_vat_rate'] ?? null) : null,
            'fund_has_deduction' => ($normalized['fund_enabled'] ?? false) && ($normalized['fund_has_deduction'] ?? false),
            'stamp_duty_applied' => $normalized['stamp_duty_applied'] ?? false,
            'stamp_duty_amount' => ($normalized['stamp_duty_applied'] ?? false) ? 200 : 0,
            'payment_method' => $normalized['payment_method'] ?? null,
            'payment_terms' => $normalized['payment_terms'] ?? null,
            'bank_name' => $normalized['bank_name'] ?? null,
            'bank_iban' => $normalized['bank_iban'] ?? null,
            'vat_payability' => ($normalized['split_payment'] ?? false) ? 'S' : $normalized['vat_payability'],
            'split_payment' => $normalized['split_payment'] ?? false,
        ], array_map(fn (array $line): array => $this->buildLinePayload($line), $normalizedLines));

        return response()->json([
            'message' => 'Fattura creata.',
            'redirect' => route('sell-invoices.index'),
            'invoice_id' => $invoice->id,
        ]);
    }

    private function buildLinePayload(array $line): array
    {
        $qty = (float) $line['quantity'];
        $price = (float) $line['unit_price'];
        $gross = $qty * $price;

        $discountPercent = isset($line['discount_percent']) && $line['discount_percent'] !== null && $line['discount_percent'] !== ''
            ? (float) $line['discount_percent']
            : null;

        $discountedTotal = $discountPercent !== null && $discountPercent > 0
            ? $gross * (1 - $discountPercent / 100)
            : $gross;

        $discountAmount = ($discountPercent !== null && $discountPercent > 0)
            ? (int) round(($gross - $discountedTotal) * 100)
            : null;

        return [
            'description' => $line['description'],
            'quantity' => $qty,
            'unit_of_measure' => ($line['unit_of_measure'] ?? null) ?: null,
            'unit_price' => (int) round($price * 100),
            'discount_percent' => $discountPercent,
            'discount_amount' => $discountAmount,
            'vat_rate' => $line['vat_rate'],
            'total' => (int) round($discountedTotal * 100),
        ];
    }
}
