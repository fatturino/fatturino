<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('monitoring.enabled', false);
        $this->migrator->add('monitoring.dsn', null);
        $this->migrator->add('monitoring.environment', 'production');
        $this->migrator->add('monitoring.traces_sample_rate', 0.0);
    }
};
