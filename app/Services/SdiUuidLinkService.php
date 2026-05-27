<?php

namespace App\Services;

use App\Models\FiscalDocument;
use App\Models\SdiUuidLink;

class SdiUuidLinkService
{
    public function linkOutbound(int $docId, string $uuid, string $fingerprint, string $reason = 'manual'): void
    {
        if ($uuid === '') {
            return;
        }

        SdiUuidLink::updateOrCreate(
            ['outbound_uuid' => $uuid],
            [
                'fiscal_document_id' => $docId,
                'business_fingerprint' => $fingerprint,
                'link_reason' => $reason,
            ]
        );
    }

    public function linkInbound(int $docId, string $uuid, string $fingerprint, string $reason = 'manual'): void
    {
        if ($uuid === '') {
            return;
        }

        SdiUuidLink::updateOrCreate(
            ['inbound_uuid' => $uuid],
            [
                'fiscal_document_id' => $docId,
                'business_fingerprint' => $fingerprint,
                'link_reason' => $reason,
            ]
        );
    }

    public function resolveDocumentByUuid(string $uuid): ?FiscalDocument
    {
        if ($uuid === '') {
            return null;
        }

        $direct = FiscalDocument::withoutGlobalScopes()->where('sdi_uuid', $uuid)->first();
        if ($direct) {
            return $direct;
        }

        $link = SdiUuidLink::query()
            ->where('outbound_uuid', $uuid)
            ->orWhere('inbound_uuid', $uuid)
            ->first();

        if (! $link) {
            return null;
        }

        return FiscalDocument::withoutGlobalScopes()->find($link->fiscal_document_id);
    }
}
