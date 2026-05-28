<?php

namespace App\Http\Controllers;

use App\Enums\FundType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentTerms;
use App\Enums\VatPayability;
use App\Enums\VatRate;
use App\Models\Sequence;
use App\Settings\CompanySettings;
use App\Settings\InvoiceSettings;
use App\Support\FiscalRegimePolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class InvoiceSettingsController extends Controller
{
    public function index(InvoiceSettings $settings): Response
    {
        $companySettings = app(CompanySettings::class);
        $isRf19 = $companySettings->company_fiscal_regime === 'RF19';

        return Inertia::render('Settings/Invoice', [
            'settings' => $settings->toArray(),
            'sequences' => Sequence::where('type', 'sales')->orderBy('name')->get(['id', 'name']),
            'vatRates' => $isRf19
                ? array_values(array_filter(VatRate::options(), fn (array $rate): bool => $rate['id'] === FiscalRegimePolicy::FORFETTARIO_VAT_RATE))
                : VatRate::options(),
            'paymentMethods' => PaymentMethod::options(),
            'paymentTerms' => PaymentTerms::options(),
            'fundTypes' => FundType::options(),
            'vatPayabilityOptions' => VatPayability::options(),
            'fiscalRegime' => $companySettings->company_fiscal_regime,
        ]);
    }

    public function update(Request $request, InvoiceSettings $settings): RedirectResponse
    {
        $validated = $request->validate([
            'default_sequence_sales' => 'nullable|exists:sequences,id',
            'default_vat_rate' => 'nullable|string',
            'withholding_tax_enabled' => 'boolean',
            'withholding_tax_percent' => 'nullable|string',
            'fund_enabled' => 'boolean',
            'fund_type' => 'nullable|string',
            'fund_percent' => 'nullable|string',
            'fund_vat_rate' => 'nullable|string',
            'fund_has_deduction' => 'boolean',
            'auto_stamp_duty' => 'boolean',
            'stamp_duty_threshold' => 'nullable|string',
            'default_payment_method' => 'nullable|string',
            'default_payment_terms' => 'nullable|string',
            'default_bank_name' => 'nullable|string',
            'default_bank_iban' => 'nullable|string',
            'default_vat_payability' => ['nullable', 'string', Rule::in(array_column(VatPayability::options(), 'id'))],
            'default_split_payment' => 'boolean',
            'default_notes' => 'nullable|string',
        ]);

        $companySettings = app(CompanySettings::class);
        $normalized = FiscalRegimePolicy::normalizeInvoiceSettingsPayload($validated, $companySettings->company_fiscal_regime);
        $settings->fill($normalized);
        $settings->save();

        return redirect()->route('settings.invoice');
    }
}
