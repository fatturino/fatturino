<?php

use App\Models\EiInboundLog;
use App\Models\EiOutboundLog;
use Illuminate\Support\Facades\Artisan;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::app')] class extends Component {
    public ?string $reconciliationOutput = null;

    public ?string $reconciliationError = null;

    public function reconcileOutboundSends(): void
    {
        $this->reconciliationOutput = null;
        $this->reconciliationError = null;
        try {
            $exitCode = Artisan::call('openapi:reconcile', [
                '--recover-sends-only' => true,
                '--details' => true,
            ]);
            $this->reconciliationOutput = trim(Artisan::output()) ?: 'Riconciliazione completata senza invii da recuperare.';
            if ($exitCode !== 0) {
                $this->reconciliationError = 'La riconciliazione non è stata completata. Verifica il dettaglio tecnico qui sotto.';
            }
        } catch (Throwable $exception) {
            report($exception);
            $this->reconciliationError = 'Non è stato possibile avviare la riconciliazione. Verifica la configurazione OpenAPI e riprova.';
        }
    }

    public function render()
    {
        return view('pages::settings.advanced', [
            'inboundLogs' => EiInboundLog::query()->latest()->limit(100)->get(['id', 'event_name', 'source_uuid', 'notification_type', 'processing_status', 'attempts', 'error_message', 'linked_fiscal_document_id', 'processed_at', 'created_at']),
            'outboundLogs' => EiOutboundLog::query()->latest()->limit(100)->get(['id', 'fiscal_document_id', 'source_uuid', 'event_type', 'status', 'message', 'created_at']),
        ]);
    }
};
?>
<x-slot:header>
    <div>
        <p class="text-xs font-bold uppercase tracking-[.12em] text-content-muted">Diagnostica</p>
        <h1 class="text-lg font-bold text-content">Avanzate</h1>
    </div>
</x-slot:header>

<section class="space-y-6">
    <x-settings.panel title="Riconcilia invii SDI" subtitle="Cerca su OpenAPI gli invii già accettati ma non finalizzati localmente. Non invia nuovi documenti allo SDI." tone="warning">
        <x-button label="Avvia riconciliazione" wire:click="reconcileOutboundSends" size="cta" spinner="reconcileOutboundSends" />
        @if($reconciliationError)
            <x-alert variant="danger" class="mt-4">{{ $reconciliationError }}</x-alert>
        @endif
        @if($reconciliationOutput)
            <pre class="mt-4 max-h-72 overflow-auto rounded-lg bg-surface-muted p-3 text-xs text-content">{{ $reconciliationOutput }}</pre>
        @endif
    </x-settings.panel>

    <x-settings.panel flush>
        <div class="border-b border-border-light p-5">
            <h2 class="text-base font-bold text-content">Eventi in ingresso</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="border-b border-border bg-surface-muted text-content-muted">
                    <tr>
                        <th class="px-4 py-3 text-xs font-medium">Evento</th>
                        <th class="px-4 py-3 text-xs font-medium">Stato</th>
                        <th class="px-4 py-3 text-xs font-medium">Tentativi</th>
                        <th class="px-4 py-3 text-xs font-medium">Data</th>
                        <th class="px-4 py-3 text-xs font-medium">Errore</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @forelse($inboundLogs as $log)
                        <tr>
                            <td class="px-4 py-3">
                                <p class="font-semibold text-content">{{ $log->event_name }}</p>
                                <p class="font-mono text-xs text-content-muted">{{ $log->source_uuid }}</p>
                            </td>
                            <td class="px-4 py-3">{{ $log->processing_status }}</td>
                            <td class="px-4 py-3">{{ $log->attempts }}</td>
                            <td class="px-4 py-3">{{ $log->created_at?->format('d/m/Y H:i') }}</td>
                            <td class="px-4 py-3 text-danger">{{ $log->error_message }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-10 text-center text-content-muted">Nessun evento registrato.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-settings.panel>

    <x-settings.panel flush>
        <div class="border-b border-border-light p-5">
            <h2 class="text-base font-bold text-content">Eventi in uscita</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="border-b border-border bg-surface-muted text-content-muted">
                    <tr>
                        <th class="px-4 py-3 text-xs font-medium">Evento</th>
                        <th class="px-4 py-3 text-xs font-medium">Documento</th>
                        <th class="px-4 py-3 text-xs font-medium">Stato</th>
                        <th class="px-4 py-3 text-xs font-medium">Data</th>
                        <th class="px-4 py-3 text-xs font-medium">Messaggio</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @forelse($outboundLogs as $log)
                        <tr>
                            <td class="px-4 py-3">{{ $log->event_type }}</td>
                            <td class="px-4 py-3">{{ $log->fiscal_document_id }}</td>
                            <td class="px-4 py-3">{{ $log->status }}</td>
                            <td class="px-4 py-3">{{ $log->created_at?->format('d/m/Y H:i') }}</td>
                            <td class="px-4 py-3">{{ $log->message }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-10 text-center text-content-muted">Nessun evento registrato.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-settings.panel>
</section>
