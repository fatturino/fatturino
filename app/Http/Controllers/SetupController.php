<?php

namespace App\Http\Controllers;

use App\Enums\FiscalRegime;
use App\Models\User;
use App\Settings\CompanySettings;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SetupController extends Controller
{
    public function show(CompanySettings $settings): RedirectResponse|Response
    {
        // If at least one user exists, setup is already done
        if (User::count() > 0) {
            return redirect()->route('login');
        }

        $sessionData = session('setup_data', []);

        return Inertia::render('Guest/Setup', [
            'appName' => config('app.name'),
            'step' => session('setup_step', 1),
            'fiscalRegimes' => [
                [
                    'id' => FiscalRegime::RF01->value,
                    'name' => FiscalRegime::RF01->label(),
                ],
                [
                    'id' => FiscalRegime::RF19->value,
                    'name' => FiscalRegime::RF19->label(),
                ],
            ],
            'prefill' => array_merge([
                'name' => '',
                'email' => '',
                'company_name' => $settings->company_name,
                'company_vat_number' => $settings->company_vat_number,
                'company_tax_code' => $settings->company_tax_code,
                'company_fiscal_regime' => $settings->company_fiscal_regime ?: 'RF01',
                'withholding_tax_enabled' => false,
                'auto_stamp_duty' => false,
                'rf19_self_invoices_enabled' => false,
                'company_address' => $settings->company_address,
                'company_city' => $settings->company_city,
                'company_postal_code' => $settings->company_postal_code,
                'company_province' => $settings->company_province,
                'company_country' => $settings->company_country ?: 'IT',
                'company_pec' => $settings->company_pec,
                'company_sdi_code' => $settings->company_sdi_code ?: '0000000',
                'conservation_acknowledged' => $settings->conservation_acknowledged ?? false,
            ], $sessionData),
        ]);
    }
}
