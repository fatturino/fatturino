<?php

namespace App\Http\Controllers;

use App\Models\EiInboundLog;
use App\Services\SdiInboundProcessor;
use App\Settings\OpenApiSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class OpenApiWebhookController
{
    public function handle(Request $request, OpenApiSettings $openApiSettings, SdiInboundProcessor $processor): JsonResponse
    {
        $event = $request->input('event');

        $this->saveWebhookDump($request->getContent(), $event);

        if (! $this->verifyToken($request, $openApiSettings)) {
            Log::channel('fe-openapi')->warning('OpenAPI webhook: invalid or missing auth token', [
                'ip' => $request->ip(),
            ]);

            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $data = $request->input('data', []);

        $inboundLog = EiInboundLog::create([
            'event_name' => (string) $event,
            'event_fingerprint' => hash('sha256', uniqid('tmp_', true)),
            'raw_payload' => $request->all(),
            'processing_status' => 'received',
        ]);

        $result = $processor->process((string) $event, is_array($data) ? $data : [], $inboundLog, $openApiSettings);

        return response()->json([
            'status' => $result['status'] ?? 'ok',
            'message' => $result['error'] ?? null,
        ], 200);
    }

    private function verifyToken(Request $request, OpenApiSettings $settings): bool
    {
        $token = $request->bearerToken();

        if (empty($token) || empty($settings->webhook_secret)) {
            return false;
        }

        return hash_equals($settings->webhook_secret, $token);
    }

    private function saveWebhookDump(string $rawBody, ?string $event): void
    {
        $directory = base_path('../webhooks');

        if (! is_dir($directory)) {
            mkdir($directory, 0755, recursive: true);
        }

        $timestamp = now()->format('Y-m-d_His');
        $eventSlug = $event ? str_replace(['.', '-'], '_', $event) : 'unknown';
        $filename = "{$timestamp}_openapi_{$eventSlug}.json";

        file_put_contents("{$directory}/{$filename}", $rawBody);
    }
}
