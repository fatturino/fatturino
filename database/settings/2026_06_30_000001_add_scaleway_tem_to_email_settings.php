<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('email.mail_provider', 'smtp');
        $this->migrator->add('email.scaleway_tem_region', 'fr-par');
        $this->migrator->add('email.scaleway_tem_project_id', null);
        $this->migrator->add('email.scaleway_tem_secret_key', null);
    }
};
