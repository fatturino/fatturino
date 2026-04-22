<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        // Null means no ATECO codes have been selected yet
        $this->migrator->add('company.company_ateco_codes', null);
    }
};
