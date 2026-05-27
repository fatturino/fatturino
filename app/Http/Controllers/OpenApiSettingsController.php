<?php

namespace App\Http\Controllers;

use App\Services\OpenApiSdiService;
use App\Settings\CompanySettings;
use App\Settings\OpenApiSettings;
use Inertia\Inertia;
use Inertia\Response;

class OpenApiSettingsController extends Controller
{
    public function index(OpenApiSettings $settings, CompanySettings $companySettings): Response
    {
        $webhookUrl = $settings->webhook_url;
        $baseUrl = ! empty($webhookUrl) ? rtrim($webhookUrl, '/') : rtrim(config('app.url'), '/');

        return Inertia::render('ElectronicInvoice/Settings', [
            'apiToken' => '',
            'sandbox' => $settings->sandbox,
            'companySdiCode' => $settings->company_sdi_code,
            'webhookUrl' => $settings->webhook_url,
            'openApiManagedByEnv' => (bool) config('fe-openapi.managed_by_env'),
            'activated' => $settings->activated,
            'hasWebhookSecret' => ! empty($settings->webhook_secret),
            'webhookCallbackUrl' => $baseUrl.'/api/openapi/webhook',
            'codiceDestinatario' => OpenApiSdiService::CODICE_DESTINATARIO,
            'conservationAcknowledged' => $companySettings->conservation_acknowledged ?? false,
        ]);
    }
}
