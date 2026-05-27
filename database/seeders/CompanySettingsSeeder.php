<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CompanySettingsSeeder extends Seeder
{
    /**
     * Seed company settings for testing purposes
     */
    public function run(): void
    {
        // Settings data - Using valid test data for Italian e-invoicing
        $settings = [
            'company_name' => 'Test Company SRL',
            'company_vat_number' => 'IT12345678903',
            'company_tax_code' => '12345678903',
            'company_address' => 'Via Roma 123',
            'company_city' => 'Milano',
            'company_postal_code' => '20100',
            'company_province' => 'MI',
            'company_country' => 'Italia',
            'company_pec' => 'testcompany@pec.it',
            'company_sdi_code' => 'ABCDEFG',
            'company_fiscal_regime' => 'RF01', // Regime ordinario
        ];

        $now = now();

        // Insert each setting as a separate row in the settings table
        // This matches how Spatie Laravel Settings stores data
        foreach ($settings as $name => $value) {
            DB::table('settings')->updateOrInsert(
                [
                    'group' => 'company',
                    'name' => $name,
                ],
                [
                    'locked' => false,
                    'payload' => json_encode($value),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }
}
