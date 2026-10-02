<?php

namespace App\Services;

use Carbon\CarbonInterface;

readonly class InAppNotificationMessage
{
    /**
     * @param  array<string, scalar|null>  $metadata
     */
    public function __construct(
        public int $recipientId,
        public string $type,
        public string $category,
        public string $severity,
        public string $title,
        public ?string $body,
        public string $sourceType,
        public string $sourceId,
        public string $dedupeKey,
        public CarbonInterface $occurredAt,
        public ?string $resourceType = null,
        public ?int $resourceId = null,
        public ?string $action = null,
        public array $metadata = [],
        public ?CarbonInterface $expiresAt = null,
    ) {}
}
