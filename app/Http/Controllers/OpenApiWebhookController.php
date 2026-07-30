<?php

namespace App\Http\Controllers;

use App\Models\EiInboundLog;
use App\Services\SdiInboundProcessor;
use App\Settings\OpenApiSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OpenApiWebhookController
{
    public function handle(Request $request, OpenApiSettings $openApiSettings, SdiInboundProcessor $processor): JsonResponse
    {
        /** @var array{event: string, data: array<string, mixed>} $payload */
        $payload = $request->attributes->get('openapi_webhook_payload');
        $event = $payload['event'];
        $data = $payload['data'];

        $inboundLog = EiInboundLog::create([
            'event_name' => (string) $event,
            'event_fingerprint' => hash('sha256', uniqid('tmp_', true)),
            'raw_payload' => $payload,
            'processing_status' => 'received',
        ]);

        $result = $processor->process((string) $event, is_array($data) ? $data : [], $inboundLog, $openApiSettings);

        return response()->json([
            'status' => $result['status'] ?? 'ok',
            'message' => $result['error'] ?? null,
        ], 200);
    }
}
