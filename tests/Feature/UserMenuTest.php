<?php

use App\Settings\CompanySettings;
use Illuminate\Support\Facades\Blade;

it('renders the company identity, fiscal regime, settings link, and logout action', function () {
    $company = app(CompanySettings::class);
    $company->company_name = 'Studio Bianchi';
    $company->company_fiscal_regime = 'RF19';
    $company->save();

    $html = Blade::render('<x-shell.user-menu />');

    expect($html)
        ->toContain('Studio Bianchi')
        ->toContain('RF19 - Forfettario')
        ->toContain(route('settings.company'))
        ->toContain('Impostazioni')
        ->toContain('action="' . route('logout') . '"')
        ->toContain('>Esci</span>');
});

it('uses the fiscal regime enum label', function () {
    $company = app(CompanySettings::class);
    $company->company_fiscal_regime = 'RF01';
    $company->save();

    expect(Blade::render('<x-shell.user-menu />'))->toContain('RF01 - Ordinario');
});
