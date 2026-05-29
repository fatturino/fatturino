<?php

namespace App\Http\Controllers;

use App\Models\EiInboundLog;
use App\Models\EiOutboundLog;
use Inertia\Inertia;
use Inertia\Response;

class AdvancedLogsController extends Controller
{
    public function index(): Response
    {
        $inboundLogs = EiInboundLog::query()
            ->latest()
            ->limit(100)
            ->get([
                'id',
                'event_name',
                'source_uuid',
                'notification_type',
                'processing_status',
                'attempts',
                'error_message',
                'linked_fiscal_document_id',
                'processed_at',
                'created_at',
            ]);

        $outboundLogs = EiOutboundLog::query()
            ->latest()
            ->limit(100)
            ->get([
                'id',
                'fiscal_document_id',
                'source_uuid',
                'event_type',
                'status',
                'message',
                'created_at',
            ]);

        return Inertia::render('Settings/Advanced', [
            'inboundLogs' => $inboundLogs,
            'outboundLogs' => $outboundLogs,
        ]);
    }
}
