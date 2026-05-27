<?php

namespace App\Services;

use App\Models\EiInboundLog;
use App\Models\FiscalDocument;
use App\Models\SdiUuidLink;

class SdiCostGuardService
{
    public function shouldFetchInvoice(string $uuid, string $eventName): bool
    {
        if ($uuid === '') {
            return false;
        }

        if ($eventName !== 'supplier-invoice') {
            return true;
        }

        $hasProcessedInbound = EiInboundLog::query()
            ->where('event_name', 'supplier-invoice')
            ->where('source_uuid', $uuid)
            ->whereIn('processing_status', ['processed', 'duplicate'])
            ->exists();

        if ($hasProcessedInbound) {
            return false;
        }

        if (FiscalDocument::withoutGlobalScopes()->where('sdi_uuid', $uuid)->exists()) {
            return false;
        }

        if (SdiUuidLink::query()->where('inbound_uuid', $uuid)->orWhere('outbound_uuid', $uuid)->exists()) {
            return false;
        }

        return true;
    }
}
