<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('company.company_name', 'My Company');
        $this->migrator->add('company.company_vat_number', '');
        $this->migrator->add('company.company_tax_code', '');
        $this->migrator->add('company.company_address', '');
        $this->migrator->add('company.company_city', '');
        $this->migrator->add('company.company_postal_code', '');
        $this->migrator->add('company.company_province', '');
        $this->migrator->add('company.company_country', 'IT');
        $this->migrator->add('company.company_pec', '');
        $this->migrator->add('company.company_sdi_code', '');
        $this->migrator->add('company.company_fiscal_regime', 'RF01');

        // Professional fund (Cassa Previdenziale): TC01-TC22, null if not enrolled
        $this->migrator->add('company.company_fund_type', null);
        $this->migrator->add('company.company_fund_percent', '4.00');

        $this->migrator->add('company.company_logo_path', null);
    }
};
