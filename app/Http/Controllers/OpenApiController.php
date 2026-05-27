<?php

namespace App\Http\Controllers;

use App\Services\OpenApiSdiService;
use App\Settings\CompanySettings;
use App\Settings\OpenApiSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class OpenApiController extends Controller
{
    public function save(Request $request, OpenApiSettings $settings): JsonResponse
    {
        if ($settings->activated) {
            return response()->json(['success' => false, 'error' => __('fe-openapi::settings.deactivate_first')]);
        }

        if (! config('fe-openapi.managed_by_env')) {
            $settings->api_token = $request->input('api_token', $settings->api_token);
            $settings->sandbox = $request->boolean('sandbox');
            $settings->company_sdi_code = $request->input('company_sdi_code', '');
            $settings->webhook_url = $request->input('webhook_url', $settings->webhook_url);
        }
        $settings->save();

        return response()->json(['success' => true, 'message' => __('fe-openapi::settings.saved')]);
    }

    public function activate(Request $request, OpenApiSettings $settings, CompanySettings $companySettings): JsonResponse
    {
        $vat = $companySettings->company_vat_number;
        if (empty($vat)) {
            return response()->json(['success' => false, 'error' => __('fe-openapi::settings.vat_missing')]);
        }

        if (! config('fe-openapi.managed_by_env')) {
            $request->validate(['api_token' => 'required|string']);
            $settings->api_token = $request->input('api_token');
            $settings->sandbox = $request->boolean('sandbox');
            $settings->company_sdi_code = $request->input('company_sdi_code', '');
            $settings->webhook_url = $request->input('webhook_url', '');
        }
        $settings->save();

        Log::channel('fe-openapi')->info('OpenAPI activate start', [
            'vat' => $vat,
            'sandbox' => (bool) $settings->sandbox,
            'has_token' => ! empty($settings->api_token),
            'webhook_url' => $request->input('webhook_url', ''),
        ]);

        $service = new OpenApiSdiService($settings);
        $statusResult = $service->checkActivationStatus($vat);
        Log::channel('fe-openapi')->info('OpenAPI activate initial status', $statusResult);

        if ($statusResult['activated']) {
            return $this->finalizeActivation($service, $settings, $vat, $request->input('webhook_url', ''));
        }

        if (isset($statusResult['registration_required']) && $statusResult['registration_required']) {
            $email = $companySettings->company_email;
            if (empty($email)) {
                return response()->json(['success' => false, 'error' => __('fe-openapi::settings.email_missing')]);
            }

            $registrationResult = $service->registerBusinessConfiguration($vat, $email);
            Log::channel('fe-openapi')->info('OpenAPI activate registration result', $registrationResult);
            if ($registrationResult['success']) {
                $recheckResult = $service->checkActivationStatus($vat);
                Log::channel('fe-openapi')->info('OpenAPI activate status recheck', $recheckResult);
                if ($recheckResult['activated']) {
                    return $this->finalizeActivation($service, $settings, $vat, $request->input('webhook_url', ''));
                }

                $settings->activated = false;
                $settings->save();

                return response()->json(['success' => true, 'message' => __('fe-openapi::settings.registration_sent'), 'activated' => false, 'hasWebhookSecret' => ! empty($settings->webhook_secret)]);
            }

            $isAlreadyConfigured = str_contains(strtolower($registrationResult['message'] ?? ''), 'already');
            if ($isAlreadyConfigured) {
                $recheckResult = $service->checkActivationStatus($vat);
                if ($recheckResult['activated']) {
                    return $this->finalizeActivation($service, $settings, $vat, $request->input('webhook_url', ''));
                }

                $settings->activated = false;
                $settings->save();

                return response()->json(['success' => true, 'message' => __('fe-openapi::settings.registration_sent'), 'activated' => false, 'hasWebhookSecret' => ! empty($settings->webhook_secret)]);
            }

            return response()->json(['success' => false, 'error' => __('fe-openapi::settings.registration_failed', ['error' => $registrationResult['message'] ?? 'Unknown'])]);
        }

        return response()->json(['success' => false, 'error' => __('fe-openapi::settings.status_check_failed', ['error' => $statusResult['message'] ?? 'Unknown'])]);
    }

    private function finalizeActivation(OpenApiSdiService $service, OpenApiSettings $settings, string $vat, string $webhookUrl): JsonResponse
    {
        $settings->activated = true;
        $settings->save();

        $this->configureWebhookCallbacks($service, $settings, $vat, $webhookUrl);
        Log::channel('fe-openapi')->info('OpenAPI finalize activation', [
            'vat' => $vat,
            'activated' => (bool) $settings->activated,
            'has_webhook_secret' => ! empty($settings->webhook_secret),
        ]);

        return response()->json([
            'success' => true,
            'message' => __('fe-openapi::settings.activated'),
            'activated' => true,
            'hasWebhookSecret' => ! empty($settings->webhook_secret),
        ]);
    }

    /**
     * Deactivate OpenAPI SDI service.
     */
    public function deactivate(OpenApiSettings $settings): JsonResponse
    {
        if (config('demo.enabled')) {
            return response()->json([
                'success' => false,
                'error' => 'In modalita demo il servizio rimane sempre attivo.',
                'activated' => true,
            ]);
        }

        $settings->activated = false;
        $settings->save();

        return response()->json(['success' => true, 'message' => __('fe-openapi::settings.deactivated'), 'activated' => false]);
    }

    /**
     * Check connection to OpenAPI.
     */
    public function checkConnection(OpenApiSettings $settings, CompanySettings $companySettings): JsonResponse
    {
        $vat = $companySettings->company_vat_number;
        if (empty($vat)) {
            return response()->json(['success' => false, 'error' => __('fe-openapi::settings.vat_missing')]);
        }

        $service = new OpenApiSdiService($settings);
        $result = $service->checkActivationStatus($vat);

        if ($result['activated']) {
            return response()->json(['success' => true, 'message' => __('fe-openapi::settings.connection_ok')]);
        } elseif (isset($result['registration_required']) && $result['registration_required']) {
            return response()->json(['success' => true, 'message' => __('fe-openapi::settings.connection_ok_inactive')]);
        }

        return response()->json(['success' => false, 'error' => __('fe-openapi::settings.connection_failed', ['error' => $result['message'] ?? 'Unknown'])]);
    }

    /**
     * Acknowledge conservation obligation.
     */
    public function acknowledgeConservation(CompanySettings $companySettings): JsonResponse
    {
        $companySettings->conservation_acknowledged = true;
        $companySettings->save();

        return response()->json(['success' => true, 'message' => __('app.conservation.acknowledged_toast'), 'conservationAcknowledged' => true]);
    }

    public function simulateWebhook(Request $request, OpenApiSettings $settings): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|string|in:supplier-invoice,customer-notification,customer-invoice',
            'notification_type' => 'nullable|string|in:NS,RC,MC,DT,NE,AT,EC',
            'invoice_uuid' => 'nullable|string',
        ]);

        if (! $settings->sandbox) {
            return response()->json(['success' => false, 'error' => 'Simulation available only in sandbox mode.']);
        }

        $type = $validated['type'];
        $invoiceUuid = trim((string) ($validated['invoice_uuid'] ?? '')) ?: 'new-uuid-to-import-5678';
        $notificationType = $validated['notification_type'] ?? 'NS';

        $payload = match ($type) {
            'customer-notification' => [
                'uuid' => $invoiceUuid,
                'notification' => $notificationType,
            ],
            'customer-invoice' => [
                'invoice' => [
                    'uuid' => $invoiceUuid,
                ],
            ],
            default => [
                'invoice' => [
                    'uuid' => $invoiceUuid,
                ],
            ],
        };

        $service = new OpenApiSdiService($settings);
        $result = $service->simulateWebhookEvent($type, $payload);
        Log::channel('fe-openapi')->info('OpenAPI simulate webhook result', [
            'type' => $type,
            'payload' => $payload,
            'result' => $result,
        ]);

        if (! ($result['success'] ?? false)) {
            return response()->json([
                'success' => false,
                'error' => $result['message'] ?? 'Simulation failed',
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => $result['message'] ?? 'Simulation sent',
        ]);
    }

    private function configureWebhookCallbacks(OpenApiSdiService $service, OpenApiSettings $settings, string $fiscalId, string $webhookUrl): void
    {
        $secret = Str::random(64);
        $baseUrl = ! empty($webhookUrl) ? rtrim($webhookUrl, '/') : rtrim(config('app.url'), '/');
        $callbackUrl = $baseUrl.'/api/openapi/webhook';
        $authHeader = "Bearer {$secret}";

        $result = $service->configureApiCallbacks($fiscalId, $callbackUrl, $authHeader);

        if ($result['success']) {
            $settings->webhook_secret = $secret;
            $settings->webhook_url = $webhookUrl;
            $settings->save();
        }
    }
}
