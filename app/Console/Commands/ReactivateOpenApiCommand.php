<?php

namespace App\Console\Commands;

use App\Services\OpenApiSdiService;
use App\Settings\CompanySettings;
use App\Settings\OpenApiSettings;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ReactivateOpenApiCommand extends Command
{
    protected $signature = 'openapi:reactivate
        {fiscal_id? : Partita IVA da usare (default: quella configurata in Impostazioni Aziendali)}
        {--webhook-url= : Base URL pubblico per callback webhook (default: OPENAPI_SDI_WEBHOOK_URL o APP_URL)}';

    protected $description = 'Forza la riattivazione OpenAPI riconfigurando i webhook anche se il servizio risulta già attivo';

    public function handle(OpenApiSettings $settings, CompanySettings $companySettings): int
    {
        $fiscalId = (string) ($this->argument('fiscal_id') ?: $companySettings->company_vat_number);
        if ($fiscalId === '') {
            $this->error('Partita IVA mancante. Specifica fiscal_id o configura company_vat_number.');

            return self::FAILURE;
        }

        $service = new OpenApiSdiService($settings);
        if (! $service->isConfigured()) {
            $this->error('OpenAPI SDI non configurato. Verifica token e ambiente.');

            return self::FAILURE;
        }

        $status = $service->checkActivationStatus($fiscalId);
        if (! ($status['activated'] ?? false)) {
            $this->error('OpenAPI SDI non risulta attivo lato provider. Esegui prima l\'attivazione standard.');

            return self::FAILURE;
        }

        $webhookBaseUrl = trim((string) ($this->option('webhook-url') ?: $settings->webhook_url));
        $baseUrl = $webhookBaseUrl !== '' ? rtrim($webhookBaseUrl, '/') : rtrim((string) config('app.url'), '/');
        $callbackUrl = $baseUrl.'/api/v1/openapi/webhook';
        $secret = Str::random(64);
        $authHeader = "Bearer {$secret}";

        $result = $service->configureApiCallbacks($fiscalId, $callbackUrl, $authHeader);
        if (! ($result['success'] ?? false)) {
            $message = (string) ($result['message'] ?? 'Errore sconosciuto');
            $this->error("Riconfigurazione webhook fallita: {$message}");

            return self::FAILURE;
        }

        $settings->activated = true;
        $settings->webhook_secret = $secret;
        $settings->webhook_url = $webhookBaseUrl;
        $settings->save();

        $this->info('OpenAPI riattivato con successo e webhook riconfigurati.');
        $this->line("Callback URL: {$callbackUrl}");

        return self::SUCCESS;
    }
}
