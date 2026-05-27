<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        // Idempotent: skip if settings already exist (e.g. restored from volume backup).
        // Using add() throws SettingAlreadyExists on existing data.
        $this->addIfMissing('openapi.api_token', '');
        $this->addIfMissing('openapi.sandbox', true);
        $this->addIfMissing('openapi.company_sdi_code', '');
        $this->addIfMissing('openapi.activated', false);
        $this->addIfMissing('openapi.webhook_secret', '');
        $this->addIfMissing('openapi.webhook_url', '');
    }

    private function addIfMissing(string $key, mixed $value): void
    {
        if (! $this->migrator->exists($key)) {
            $this->migrator->add($key, $value);
        }
    }
};
