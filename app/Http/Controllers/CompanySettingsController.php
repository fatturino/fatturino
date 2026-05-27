<?php

namespace App\Http\Controllers;

use App\Enums\AtecoCode;
use App\Enums\FiscalRegime;
use App\Settings\CompanySettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CompanySettingsController extends Controller
{
    public function index(CompanySettings $settings): Response
    {
        $atecoCodes = array_map(fn ($c) => [
            'code' => $c,
            'label' => AtecoCode::label($c),
        ], $settings->company_ateco_codes ?? []);

        return Inertia::render('Settings/Company', [
            'company' => $settings->toArray(),
            'atecoCodes' => $atecoCodes,
            'fiscalRegimes' => FiscalRegime::options(),
            'countries' => $this->countries(),
        ]);
    }

    public function update(Request $request, CompanySettings $settings): RedirectResponse
    {
        $validated = $request->validate([
            'company_name' => 'required|string',
            'company_vat_number' => 'nullable|string',
            'company_tax_code' => 'nullable|string',
            'company_address' => 'nullable|string',
            'company_postal_code' => 'nullable|string',
            'company_city' => 'nullable|string',
            'company_province' => 'nullable|string',
            'company_country' => 'required|size:2',
            'company_email' => 'nullable|email',
            'company_pec' => 'nullable|string',
            'company_sdi_code' => 'nullable|string',
            'company_fiscal_regime' => ['required', Rule::in(array_column(FiscalRegime::options(), 'value'))],
            'rf19_self_invoices_enabled' => 'boolean',
            'company_ateco_codes' => 'nullable|array',
            'company_ateco_codes.*' => 'string',
            'company_logo' => 'nullable|image|max:1024',
            'remove_logo' => 'boolean',
        ]);

        if ($request->boolean('remove_logo') && $settings->company_logo_path) {
            \Storage::disk('public')->delete($settings->company_logo_path);
            $settings->company_logo_path = null;
        }

        if ($request->hasFile('company_logo')) {
            if ($settings->company_logo_path) {
                \Storage::disk('public')->delete($settings->company_logo_path);
            }
            $ext = $request->file('company_logo')->getClientOriginalExtension();
            $path = $request->file('company_logo')->storeAs('logos', 'company-logo.'.$ext, 'public');
            $settings->company_logo_path = $path;
        }

        $oldRegime = $settings->company_fiscal_regime;
        $oldRf19SelfInvoicesEnabled = $settings->rf19_self_invoices_enabled;

        $settings->fill($validated);
        $settings->save();

        if ($oldRegime !== $settings->company_fiscal_regime || $oldRf19SelfInvoicesEnabled !== $settings->rf19_self_invoices_enabled) {
            Log::info('Fiscal regime settings updated', [
                'user_id' => $request->user()?->id,
                'old_regime' => $oldRegime,
                'new_regime' => $settings->company_fiscal_regime,
                'old_rf19_self_invoices_enabled' => $oldRf19SelfInvoicesEnabled,
                'new_rf19_self_invoices_enabled' => $settings->rf19_self_invoices_enabled,
            ]);
        }

        return redirect()->route('settings.company');
    }

    public function atecoSearch(Request $request): JsonResponse
    {
        $q = $request->query('q', '');
        $results = AtecoCode::options($q);

        return response()->json(array_slice($results, 0, 50));
    }

    private function countries(): array
    {
        return [
            ['value' => 'IT', 'label' => 'Italia'],
            ['value' => 'AT', 'label' => 'Austria'],
            ['value' => 'BE', 'label' => 'Belgio'],
            ['value' => 'BG', 'label' => 'Bulgaria'],
            ['value' => 'CY', 'label' => 'Cipro'],
            ['value' => 'HR', 'label' => 'Croazia'],
            ['value' => 'DK', 'label' => 'Danimarca'],
            ['value' => 'EE', 'label' => 'Estonia'],
            ['value' => 'FI', 'label' => 'Finlandia'],
            ['value' => 'FR', 'label' => 'Francia'],
            ['value' => 'DE', 'label' => 'Germania'],
            ['value' => 'GR', 'label' => 'Grecia'],
            ['value' => 'IE', 'label' => 'Irlanda'],
            ['value' => 'LV', 'label' => 'Lettonia'],
            ['value' => 'LT', 'label' => 'Lituania'],
            ['value' => 'LU', 'label' => 'Lussemburgo'],
            ['value' => 'MT', 'label' => 'Malta'],
            ['value' => 'NL', 'label' => 'Paesi Bassi'],
            ['value' => 'PL', 'label' => 'Polonia'],
            ['value' => 'PT', 'label' => 'Portogallo'],
            ['value' => 'CZ', 'label' => 'Repubblica Ceca'],
            ['value' => 'RO', 'label' => 'Romania'],
            ['value' => 'SK', 'label' => 'Slovacchia'],
            ['value' => 'SI', 'label' => 'Slovenia'],
            ['value' => 'ES', 'label' => 'Spagna'],
            ['value' => 'SE', 'label' => 'Svezia'],
            ['value' => 'HU', 'label' => 'Ungheria'],
            ['value' => 'CH', 'label' => 'Svizzera'],
            ['value' => 'GB', 'label' => 'Regno Unito'],
            ['value' => 'US', 'label' => 'Stati Uniti'],
            ['value' => 'CN', 'label' => 'Cina'],
        ];
    }
}
