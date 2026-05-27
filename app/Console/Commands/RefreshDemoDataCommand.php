<?php

namespace App\Console\Commands;

use App\Services\OpenApiSdiService;
use App\Settings\CompanySettings;
use App\Settings\OpenApiSettings;
use Database\Seeders\DemoModeSeeder;
use Illuminate\Console\Command;

class RefreshDemoDataCommand extends Command
{
    protected $signature = 'demo:refresh';

    protected $description = 'Reset and reseed demo account data';

    public function handle(): int
    {
        if (! config('demo.enabled')) {
            $this->warn('Demo mode is disabled (FATTURINO_DEMO=false).');

            return self::SUCCESS;
        }

        $this->call('migrate:fresh');

        $this->call('db:seed', [
            '--class' => DemoModeSeeder::class,
            '--force' => true,
        ]);

        $this->activateDemoElectronicInvoicing();

        $this->info('Demo dataset refreshed.');

        return self::SUCCESS;
    }

    private function activateDemoElectronicInvoicing(): void
    {
        $companySettings = app(CompanySettings::class);
        $settings = app(OpenApiSettings::class);

        $settings->sandbox = true;
        $settings->company_sdi_code = OpenApiSdiService::CODICE_DESTINATARIO;

        if (! config('fe-openapi.managed_by_env')) {
            $settings->api_token = (string) env('OPENAPI_SDI_API_TOKEN', '');
        }

        $settings->activated = false;
        $settings->save();

        $service = new OpenApiSdiService($settings);
        if (! $service->isConfigured()) {
            $this->warn('OpenAPI SDI non configurato - imposta OPENAPI_SDI_API_TOKEN per attivazione reale in demo:refresh.');

            return;
        }

        $vat = (string) $companySettings->company_vat_number;
        $email = (string) $companySettings->company_email;

        $status = $service->checkActivationStatus($vat);

        if ($status['activated'] ?? false) {
            $settings->activated = true;
            $settings->save();
            $this->info('OpenAPI SDI già attivo per la demo.');

            return;
        }

        if (! ($status['registration_required'] ?? false)) {
            $this->warn('OpenAPI SDI check fallito durante demo:refresh - attivazione non completata.');

            return;
        }

        $registration = $service->registerBusinessConfiguration($vat, $email);
        if (! ($registration['success'] ?? false)) {
            $this->warn('OpenAPI SDI registrazione fallita durante demo:refresh - attivazione non completata.');

            return;
        }

        $recheck = $service->checkActivationStatus($vat);
        $settings->activated = (bool) ($recheck['activated'] ?? false);
        $settings->save();

        if ($settings->activated) {
            $this->info('OpenAPI SDI attivato via API durante demo:refresh.');

            return;
        }

        $this->warn('OpenAPI SDI registrato ma non ancora attivo (attesa conferma provider).');
    }
}
