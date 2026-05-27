<?php

namespace Database\Seeders;

use App\Models\User;
use App\Settings\CompanySettings;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DevSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedUser();
        $this->seedCompanySettings();
    }

    private function seedUser(): void
    {
        User::updateOrCreate(
            ['email' => 'dev@fatturino.it'],
            [
                'name' => 'Developer Fatturino',
                'password' => Hash::make('password'),
                'is_admin' => true,
            ]
        );
    }

    private function seedCompanySettings(): void
    {
        $company = app(CompanySettings::class);
        $company->company_name = 'Studio Dev Fatturino S.r.l.';
        $company->company_vat_number = 'IT12345678903';
        $company->company_tax_code = '12345678903';
        $company->company_address = 'Via Roma 42';
        $company->company_city = 'Milano';
        $company->company_postal_code = '20121';
        $company->company_province = 'MI';
        $company->company_country = 'IT';
        $company->company_email = 'amministrazione@dev-fatturino.it';
        $company->company_pec = 'dev-fatturino@pec.it';
        $company->company_sdi_code = 'M5UXCR1';
        $company->company_fiscal_regime = 'RF01';
        $company->save();
    }
}
