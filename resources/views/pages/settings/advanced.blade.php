<?php

use App\Models\EiInboundLog;
use App\Models\EiOutboundLog;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::app')] class extends Component {
    public function render()
    {
        return view('pages::settings.advanced', [
            'inboundLogs' => EiInboundLog::query()->latest()->limit(100)->get(['id', 'event_name', 'source_uuid', 'notification_type', 'processing_status', 'attempts', 'error_message', 'linked_fiscal_document_id', 'processed_at', 'created_at']),
            'outboundLogs' => EiOutboundLog::query()->latest()->limit(100)->get(['id', 'fiscal_document_id', 'source_uuid', 'event_type', 'status', 'message', 'created_at']),
        ]);
    }
}; ?>
<x-slot:header><div><p class="text-xs font-bold uppercase tracking-[.12em] text-content-muted">Diagnostica</p><h1 class="text-lg font-bold text-content">Avanzate</h1></div></x-slot:header>
<section class="space-y-6"><article class="overflow-hidden rounded-xl border border-border-light bg-white shadow-[var(--shadow-card)]"><div class="border-b border-border-light p-5"><h2 class="font-bold">Eventi in ingresso</h2></div><div class="overflow-x-auto"><table class="min-w-full text-left text-sm"><thead class="bg-surface-muted text-xs uppercase text-content-muted"><tr><th class="px-4 py-3">Evento</th><th class="px-4 py-3">Stato</th><th class="px-4 py-3">Tentativi</th><th class="px-4 py-3">Data</th><th class="px-4 py-3">Errore</th></tr></thead><tbody class="divide-y divide-border-light">@forelse($inboundLogs as $log)<tr><td class="px-4 py-3"><p class="font-semibold">{{ $log->event_name }}</p><p class="font-mono text-xs text-content-muted">{{ $log->source_uuid }}</p></td><td class="px-4 py-3">{{ $log->processing_status }}</td><td class="px-4 py-3">{{ $log->attempts }}</td><td class="px-4 py-3">{{ $log->created_at?->format('d/m/Y H:i') }}</td><td class="px-4 py-3 text-danger">{{ $log->error_message }}</td></tr>@empty<tr><td colspan="5" class="px-4 py-10 text-center text-content-muted">Nessun evento registrato.</td></tr>@endforelse</tbody></table></div></article><article class="overflow-hidden rounded-xl border border-border-light bg-white shadow-[var(--shadow-card)]"><div class="border-b border-border-light p-5"><h2 class="font-bold">Eventi in uscita</h2></div><div class="overflow-x-auto"><table class="min-w-full text-left text-sm"><thead class="bg-surface-muted text-xs uppercase text-content-muted"><tr><th class="px-4 py-3">Evento</th><th class="px-4 py-3">Documento</th><th class="px-4 py-3">Stato</th><th class="px-4 py-3">Data</th><th class="px-4 py-3">Messaggio</th></tr></thead><tbody class="divide-y divide-border-light">@forelse($outboundLogs as $log)<tr><td class="px-4 py-3">{{ $log->event_type }}</td><td class="px-4 py-3">{{ $log->fiscal_document_id }}</td><td class="px-4 py-3">{{ $log->status }}</td><td class="px-4 py-3">{{ $log->created_at?->format('d/m/Y H:i') }}</td><td class="px-4 py-3">{{ $log->message }}</td></tr>@empty<tr><td colspan="5" class="px-4 py-10 text-center text-content-muted">Nessun evento registrato.</td></tr>@endforelse</tbody></table></div></article></section>
