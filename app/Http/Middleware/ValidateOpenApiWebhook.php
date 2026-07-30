<?php

namespace App\Http\Middleware;

use App\Settings\OpenApiSettings;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use JsonException;
use Symfony\Component\HttpFoundation\Response;

class ValidateOpenApiWebhook
{
    public function __construct(private readonly OpenApiSettings $settings) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->hasValidToken($request)) {
            return $this->reject('unauthorized', Response::HTTP_UNAUTHORIZED);
        }

        if (! $request->isJson()) {
            return $this->reject('unsupported_content_type', Response::HTTP_UNSUPPORTED_MEDIA_TYPE, $request);
        }

        $maxBodyBytes = (int) config('fe-openapi.webhook_max_body_bytes');
        $declaredLength = $request->header('Content-Length');
        if (is_numeric($declaredLength) && (int) $declaredLength > $maxBodyBytes) {
            return $this->reject('payload_too_large', Response::HTTP_REQUEST_ENTITY_TOO_LARGE, $request);
        }

        if ($this->tooManyRequests($request)) {
            return $this->reject('rate_limited', Response::HTTP_TOO_MANY_REQUESTS, $request);
        }

        $rawBody = $request->getContent();
        if (strlen($rawBody) > $maxBodyBytes) {
            return $this->reject('payload_too_large', Response::HTTP_REQUEST_ENTITY_TOO_LARGE, $request);
        }

        try {
            $payload = json_decode($rawBody, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return $this->reject('invalid_json', Response::HTTP_BAD_REQUEST, $request);
        }

        if (! is_array($payload) || ! is_string($payload['event'] ?? null) || $payload['event'] === '' || ! is_array($payload['data'] ?? null)) {
            return $this->reject('invalid_payload_shape', Response::HTTP_UNPROCESSABLE_ENTITY, $request);
        }

        $request->attributes->set('openapi_webhook_payload', $payload);

        return $next($request);
    }

    private function hasValidToken(Request $request): bool
    {
        $token = $request->bearerToken();

        return filled($token) && filled($this->settings->webhook_secret) && hash_equals($this->settings->webhook_secret, $token);
    }

    private function tooManyRequests(Request $request): bool
    {
        $key = 'openapi-webhook:'.hash('sha256', $request->ip());
        $maxAttempts = (int) config('fe-openapi.webhook_max_requests_per_minute');

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            return true;
        }

        RateLimiter::hit($key, 60);

        return false;
    }

    private function reject(string $reason, int $status, ?Request $request = null): JsonResponse
    {
        Log::channel('fe-openapi')->warning('OpenAPI webhook rejected', array_filter([
            'reason' => $reason,
            'content_type' => $request?->header('Content-Type'),
            'content_length' => $request?->header('Content-Length'),
        ], static fn (mixed $value): bool => $value !== null));

        return response()->json(['error' => Response::$statusTexts[$status] ?? 'Request rejected'], $status);
    }
}
