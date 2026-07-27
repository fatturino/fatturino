<?php

namespace App\Actions;

use App\Services\OpenApiSdiService;
use App\Settings\CompanySettings;
use App\Settings\OpenApiSettings;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ManageOpenApiSettings
{
    public function save(OpenApiSettings $settings, string $apiToken, bool $sandbox, string $companySdiCode, string $webhookUrl): array
    {
        if ($settings->activated) {
            return $this->failure(__('fe-openapi::settings.deactivate_first'));
        }

        if (! config('fe-openapi.managed_by_env')) {
            if (filled($apiToken)) {
                $settings->api_token = $apiToken;
            }

            $settings->sandbox = $sandbox;
            $settings->company_sdi_code = $companySdiCode;
            $settings->webhook_url = $webhookUrl;
            $settings->save();
        }

        return $this->success(__('fe-openapi::settings.saved'));
    }

    public function checkConnection(OpenApiSettings $settings, CompanySettings $companySettings, string $apiToken, bool $sandbox): array
    {
        $settings = $this->settingsForEnvironment($settings);
        $vat = $companySettings->company_vat_number;
        if (blank($vat)) {
            return $this->failure(__('fe-openapi::settings.vat_missing'));
        }

        $result = $this->serviceFor($settings, $apiToken, $sandbox)->checkActivationStatus($vat);

        if ($result['activated'] ?? false) {
            return $this->success(__('fe-openapi::settings.connection_ok'));
        }

        if ($result['registration_required'] ?? false) {
            return $this->success(__('fe-openapi::settings.connection_ok_inactive'));
        }

        Log::channel('fe-openapi')->warning('OpenAPI connection check failed', [
            'error' => $result['error'] ?? null,
            'message' => $result['message'] ?? null,
        ]);

        return $this->failure('Impossibile verificare la connessione OpenAPI. Controlla la configurazione e riprova.');
    }

    public function activate(OpenApiSettings $settings, CompanySettings $companySettings, string $apiToken, bool $sandbox, string $companySdiCode, string $webhookUrl): array
    {
        if (config('demo.enabled')) {
            return $this->failure('In modalità demo il servizio rimane sempre attivo.', true);
        }

        $settings = $this->settingsForEnvironment($settings);
        $vat = $companySettings->company_vat_number;
        if (blank($vat)) {
            return $this->failure(__('fe-openapi::settings.vat_missing'));
        }

        if (! config('fe-openapi.managed_by_env')) {
            if (blank($apiToken)) {
                return $this->failure('Inserisci un API token valido prima di attivare il servizio.');
            }

            $settings->api_token = $apiToken;
            $settings->sandbox = $sandbox;
            $settings->company_sdi_code = $companySdiCode;
            $settings->webhook_url = $webhookUrl;
            $settings->save();
        } else {
            $webhookUrl = $settings->webhook_url;
        }

        $service = new OpenApiSdiService($settings);
        $status = $service->checkActivationStatus($vat);

        if ($status['activated'] ?? false) {
            return $this->finalizeActivation($service, $settings, $vat, $webhookUrl);
        }

        if (! ($status['registration_required'] ?? false)) {
            Log::channel('fe-openapi')->warning('OpenAPI activation status check failed', $status);

            return $this->failure('Impossibile verificare lo stato del servizio OpenAPI. Riprova più tardi.');
        }

        if (blank($companySettings->company_email)) {
            return $this->failure(__('fe-openapi::settings.email_missing'));
        }

        $registration = $service->registerBusinessConfiguration($vat, $companySettings->company_email);
        if (! ($registration['success'] ?? false) && ! str_contains(strtolower((string) ($registration['message'] ?? '')), 'already')) {
            Log::channel('fe-openapi')->warning('OpenAPI registration failed', $registration);

            return $this->failure('Impossibile avviare la registrazione OpenAPI. Riprova più tardi.');
        }

        $recheck = $service->checkActivationStatus($vat);
        if ($recheck['activated'] ?? false) {
            return $this->finalizeActivation($service, $settings, $vat, $webhookUrl);
        }

        $settings->activated = false;
        $settings->save();

        return $this->success(__('fe-openapi::settings.registration_sent'), false, filled($settings->webhook_secret));
    }

    public function deactivate(OpenApiSettings $settings): array
    {
        if (config('demo.enabled')) {
            return $this->failure('In modalità demo il servizio rimane sempre attivo.', true);
        }

        $settings->activated = false;
        $settings->save();

        return $this->success(__('fe-openapi::settings.deactivated'), false);
    }

    public function simulateWebhook(OpenApiSettings $settings, string $type, string $invoiceUuid): array
    {
        $settings = $this->settingsForEnvironment($settings);
        if (! $settings->sandbox) {
            return $this->failure(__('fe-openapi::settings.simulate_sandbox_only'));
        }

        $invoiceUuid = trim($invoiceUuid) ?: 'new-uuid-to-import-5678';
        $payload = match ($type) {
            'customer-notification' => ['uuid' => $invoiceUuid, 'notification' => 'NS'],
            'customer-invoice' => ['invoice' => ['uuid' => $invoiceUuid]],
            'supplier-invoice' => ['invoice' => ['uuid' => $invoiceUuid]],
            default => null,
        };

        if ($payload === null) {
            return $this->failure('Tipo di simulazione non valido.');
        }

        $result = (new OpenApiSdiService($settings))->simulateWebhookEvent($type, $payload);
        if (! ($result['success'] ?? false)) {
            Log::channel('fe-openapi')->warning('OpenAPI webhook simulation failed', [
                'type' => $type,
                'error' => $result['error'] ?? null,
                'message' => $result['message'] ?? null,
            ]);

            return $this->failure('Impossibile inviare la simulazione webhook. Riprova più tardi.');
        }

        return $this->success('Webhook simulato inviato.');
    }

    private function finalizeActivation(OpenApiSdiService $service, OpenApiSettings $settings, string $vat, string $webhookUrl): array
    {
        $settings->activated = true;
        $settings->save();

        $secret = Str::random(64);
        $baseUrl = filled($webhookUrl) ? rtrim($webhookUrl, '/') : rtrim((string) config('app.url'), '/');
        $callbackUrl = $baseUrl.'/api/v1/openapi/webhook';
        $result = $service->configureApiCallbacks($vat, $callbackUrl, "Bearer {$secret}");

        if ($result['success'] ?? false) {
            $settings->webhook_secret = $secret;
            $settings->webhook_url = $webhookUrl;
            $settings->save();
        } else {
            Log::channel('fe-openapi')->warning('OpenAPI callback configuration failed during activation', $result);
        }

        return $this->success(__('fe-openapi::settings.activated'), true, filled($settings->webhook_secret));
    }

    private function serviceFor(OpenApiSettings $settings, string $apiToken, bool $sandbox): OpenApiSdiService
    {
        if (config('fe-openapi.managed_by_env')) {
            return new OpenApiSdiService($settings);
        }

        $temporarySettings = clone $settings;
        $temporarySettings->api_token = filled($apiToken) ? $apiToken : $settings->api_token;
        $temporarySettings->sandbox = $sandbox;

        return new OpenApiSdiService($temporarySettings);
    }

    private function settingsForEnvironment(OpenApiSettings $settings): OpenApiSettings
    {
        if (! config('fe-openapi.managed_by_env')) {
            return $settings;
        }

        $settings = clone $settings;
        $settings->api_token = (string) config('fe-openapi.api_token');
        $settings->sandbox = (bool) config('fe-openapi.sandbox');
        $settings->company_sdi_code = (string) config('fe-openapi.company_sdi_code');
        $settings->webhook_url = (string) config('fe-openapi.webhook_url');

        return $settings;
    }

    private function success(string $message, ?bool $activated = null, ?bool $hasWebhookSecret = null): array
    {
        return array_filter([
            'success' => true,
            'message' => $message,
            'activated' => $activated,
            'hasWebhookSecret' => $hasWebhookSecret,
        ], static fn (mixed $value): bool => $value !== null);
    }

    private function failure(string $message, ?bool $activated = null): array
    {
        return array_filter([
            'success' => false,
            'message' => $message,
            'activated' => $activated,
        ], static fn (mixed $value): bool => $value !== null);
    }
}
