<?php

namespace App\Http\Controllers\Api;

use App\Enums\FiscalRegime;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Rules\ItalianVatNumber;
use App\Settings\CompanySettings;
use App\Settings\InvoiceSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class SetupWizardController extends Controller
{
    public function store(Request $request, CompanySettings $companySettings, InvoiceSettings $invoiceSettings): JsonResponse
    {
        if (User::count() > 0) {
            return response()->json(['message' => 'Setup già completato.'], 403);
        }

        $step = $request->integer('step');

        $maxAllowed = session('setup_step', 1);
        if ($step > $maxAllowed) {
            throw ValidationException::withMessages([
                'step' => 'Completa prima lo step corrente.',
            ]);
        }

        $this->validateStep($request, $step);
        $this->persistStepData($request, $step);

        if ($step < 3) {
            $nextStep = $step + 1;
            session(['setup_step' => $nextStep]);

            return response()->json(['step' => $nextStep]);
        }

        $user = User::create([
            'name' => $request->string('name'),
            'email' => $request->string('email'),
            'password' => Hash::make((string) $request->input('password')),
            'is_admin' => true,
        ]);

        $companySettings->company_name = $request->string('company_name');
        $companySettings->company_vat_number = ItalianVatNumber::normalize((string) $request->input('company_vat_number')) ?? '';
        $companySettings->company_tax_code = $request->string('company_tax_code');
        $companySettings->company_fiscal_regime = $request->string('company_fiscal_regime');
        $companySettings->rf19_self_invoices_enabled = false;
        $companySettings->company_address = $request->string('company_address');
        $companySettings->company_city = $request->string('company_city');
        $companySettings->company_postal_code = $request->string('company_postal_code');
        $companySettings->company_province = $request->string('company_province');
        $companySettings->company_country = $request->string('company_country');
        $companySettings->company_pec = $request->string('company_pec');
        $companySettings->company_sdi_code = $request->string('company_sdi_code');
        $companySettings->save();

        $invoiceSettings->withholding_tax_enabled = $request->boolean('withholding_tax_enabled');
        $invoiceSettings->auto_stamp_duty = $request->boolean('auto_stamp_duty');
        $invoiceSettings->save();

        Auth::login($user);
        session()->regenerate();
        session()->forget(['setup_step', 'setup_data']);

        return response()->json(['redirect' => route('dashboard')]);
    }

    private function validateStep(Request $request, int $step): void
    {
        $rules = match ($step) {
            1 => [
                'name' => 'required|min:2',
                'email' => 'required|email|unique:users,email',
                'password' => 'required|min:8|confirmed',
                'password_confirmation' => 'required',
            ],
            2 => [
                'company_name' => 'required|min:2',
                'company_vat_number' => ['required', new ItalianVatNumber],
                'company_tax_code' => 'required',
                'company_fiscal_regime' => 'required|in:'.FiscalRegime::RF01->value.','.FiscalRegime::RF19->value,
            ],
            3 => [
                'company_address' => 'required',
                'company_postal_code' => 'required',
                'company_city' => 'required',
                'company_province' => 'required|size:2',
                'company_country' => 'required|size:2',
                'company_pec' => 'required|email',
                'company_sdi_code' => 'required|size:7',
            ],
            default => [],
        };

        if (! empty($rules)) {
            $request->validate($rules);
        }
    }

    private function persistStepData(Request $request, int $step): void
    {
        $data = session('setup_data', []);
        $fieldsByStep = [
            1 => ['name', 'email'],
            2 => ['company_name', 'company_vat_number', 'company_tax_code', 'company_fiscal_regime', 'withholding_tax_enabled', 'auto_stamp_duty'],
            3 => ['company_address', 'company_city', 'company_postal_code', 'company_province', 'company_country', 'company_pec', 'company_sdi_code'],
        ];

        foreach ($fieldsByStep[$step] ?? [] as $field) {
            $data[$field] = in_array($field, ['withholding_tax_enabled', 'auto_stamp_duty'], true)
                ? $request->boolean($field)
                : $request->input($field);

            if ($field === 'company_vat_number') {
                $data[$field] = ItalianVatNumber::normalize($data[$field]);
            }
        }

        session(['setup_data' => $data]);
    }
}
