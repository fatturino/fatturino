<?php

namespace App\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PostHog\PostHog;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class PostHogTelemetryService
{
    public function isEnabled(): bool
    {
        return (string) config('services.posthog.api_key', '') !== '' && class_exists(PostHog::class);
    }

    public function instanceKey(): string
    {
        $rawKey = (string) (config('app.instance_id') ?: config('app.name', 'fatturino'));
        $normalized = Str::of($rawKey)
            ->trim()
            ->lower()
            ->replaceMatches('/[^a-z0-9._:-]+/', '-')
            ->trim('-')
            ->value();

        return $normalized !== '' ? $normalized : 'fatturino';
    }

    public function distinctIdFor(Authenticatable|int|string|null $user = null): ?string
    {
        $userId = match (true) {
            $user instanceof Authenticatable => $user->getAuthIdentifier(),
            $user !== null => $user,
            default => null,
        };

        if ($userId === null || $userId === '') {
            return null;
        }

        return sprintf('%s:user:%s', $this->instanceKey(), $userId);
    }

    public function sharedContext(): array
    {
        return [
            'instanceKey' => $this->instanceKey(),
            'appName' => (string) config('app.name'),
            'appEnv' => (string) config('app.env'),
            'appVersion' => (string) config('app.version'),
        ];
    }

    public function documentProperties(Model $document): array
    {
        $properties = [
            'document_kind' => $this->documentKind($document),
            'document_status' => method_exists($document, 'statusValue') ? $document->statusValue() : null,
            'fiscal_year' => $document->getAttribute('fiscal_year'),
        ];

        $sdiStatus = $document->getAttribute('sdi_status');
        if ($sdiStatus instanceof \BackedEnum) {
            $properties['sdi_status'] = $sdiStatus->value;
        } elseif ($sdiStatus !== null && $sdiStatus !== '') {
            $properties['sdi_status'] = (string) $sdiStatus;
        }

        return array_filter($properties, static fn (mixed $value): bool => $value !== null && $value !== '');
    }

    public function capture(string $event, array $properties = [], Authenticatable|int|string|null $user = null): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        try {
            $message = [
                'event' => $event,
                'properties' => $this->baseProperties($properties),
            ];

            $distinctId = $this->distinctIdFor($user);
            if ($distinctId !== null) {
                $message['distinctId'] = $distinctId;
            }

            return PostHog::capture($message);
        } catch (Throwable) {
            return false;
        }
    }

    public function captureException(Throwable|string $exception, array $properties = [], Authenticatable|int|string|null $user = null): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        try {
            return PostHog::captureException(
                $exception,
                $this->distinctIdFor($user),
                $this->baseProperties($properties)
            );
        } catch (Throwable) {
            return false;
        }
    }

    public function shouldReportException(Throwable $throwable): bool
    {
        if ($throwable instanceof TokenMismatchException || $throwable instanceof ValidationException) {
            return false;
        }

        $statusCode = $throwable instanceof HttpExceptionInterface ? $throwable->getStatusCode() : null;

        if (in_array($statusCode, [401, 403, 404, 419, 422], true)) {
            return false;
        }

        return true;
    }

    public function exceptionContext(Throwable $throwable, ?Request $request = null): array
    {
        return array_filter([
            'exception_class' => $throwable::class,
            'request_method' => $request?->method(),
            'request_path' => $request?->path(),
            'route_name' => $request?->route()?->getName(),
            'route_uri' => $request?->route()?->uri(),
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
    }

    private function baseProperties(array $properties = []): array
    {
        return array_filter(array_merge($this->sharedContext(), $properties), static fn (mixed $value): bool => $value !== null && $value !== '');
    }

    private function documentKind(Model $document): string
    {
        $type = $document->getAttribute('type');
        if (is_string($type) && $type !== '') {
            return $type;
        }

        return Str::snake(class_basename($document));
    }
}
