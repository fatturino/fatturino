<?php

namespace App\Services;

class InboundEventFingerprintService
{
    public function build(string $eventName, array $data, ?string $businessFingerprint): string
    {
        $sourceUuid = $this->extractSourceUuid($eventName, $data) ?? '-';
        $notificationType = $data['notification']['type'] ?? '-';

        $parts = [
            strtoupper(trim($eventName)) ?: '-',
            strtoupper(trim((string) $sourceUuid)) ?: '-',
            strtoupper(trim((string) $notificationType)) ?: '-',
            $businessFingerprint ?: '-',
        ];

        return hash('sha256', implode('|', $parts));
    }

    public function extractSourceUuid(string $eventName, array $data): ?string
    {
        return match ($eventName) {
            'supplier-invoice', 'customer-invoice' => $data['invoice']['uuid'] ?? null,
            'customer-notification' => $data['notification']['invoice_uuid'] ?? null,
            default => null,
        };
    }
}
