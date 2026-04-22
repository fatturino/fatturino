<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class CompanySettings extends Settings
{
    // Company info
    public string $company_name;

    public string $company_vat_number;

    public string $company_tax_code;

    // Address
    public string $company_address;

    public string $company_city;

    public string $company_postal_code;

    public string $company_province;

    public string $company_country;

    // Electronic invoicing
    public string $company_email;

    public string $company_pec;

    public string $company_sdi_code;

    public string $company_fiscal_regime;

    // Logo for courtesy PDF documents
    public ?string $company_logo_path;

    // Professional fund (Cassa Previdenziale)
    public ?string $company_fund_type;

    public string $company_fund_percent;

    // ATECO 2007 division codes associated with the company (stored as array of string values)
    public ?array $company_ateco_codes;

    /**
     * Define the settings group name
     */
    public static function group(): string
    {
        return 'company';
    }
}
