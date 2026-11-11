@props(['events'])

@php
    $historyEvents = collect($events)->map(static function ($event): array {
        return [
            'id' => $event->id,
            'title' => $event->title,
            'message' => $event->message,
            'error_message' => $event->error_message,
            'status' => $event->status,
            'channel' => $event->channel,
            'event_type' => $event->event_type,
            'occurred_at' => $event->occurred_at?->format('d/m/Y H:i'),
        ];
    })->values();
@endphp

<div
    x-data="{
        open: false,
        selected: null,
        openEvent(event) {
            this.selected = event;
            this.open = true;
            this.$nextTick(() => this.$refs.historyClose?.focus());
        },
        close() {
            this.open = false;
            this.selected = null;
        },
        statusLabel(status) {
            return {
                success: 'Completato',
                failed: 'Fallito',
                queued: 'In coda',
                received: 'Ricevuto',
            }[status] ?? (status || 'Registrato');
        },
        isSdiFailure(event) {
            return event?.channel === 'sdi' && event?.status === 'failed';
        },
        errorLabel(event) {
            return this.isSdiFailure(event) ? 'Codice errore' : 'Errore';
        },
    }"
    x-effect="document.documentElement.classList.toggle('modal-open', open)"
    @keydown.escape.window="if (open) close()"
    class="space-y-3"
>
    @forelse($historyEvents as $event)
        <button
            type="button"
            class="block w-full border-l-2 border-primary pl-3 text-left transition-colors hover:bg-surface-muted focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40"
            @click="openEvent({{ \Illuminate\Support\Js::from($event) }})"
        >
            <p class="text-sm font-semibold text-content">{{ $event['title'] }}</p>
            <p class="text-xs text-content-muted">{{ $event['occurred_at'] }}</p>
            @if(filled($event['message']))
                <p class="mt-1 line-clamp-2 text-xs text-content-muted">{{ $event['message'] }}</p>
            @endif
        </button>
    @empty
        <p class="text-sm text-content-muted">Nessun evento registrato.</p>
    @endforelse

    <template x-teleport="body">
        <div
            x-show="open"
            x-cloak
            x-trap.inert.noscroll="open"
            class="modal-viewport"
            role="dialog"
            aria-modal="true"
            aria-labelledby="document-history-title"
        >
            <div class="modal-backdrop bg-black/30" @click="close()"></div>
            <div class="modal-panel modal-panel--md rounded-xl bg-white p-6 shadow-[var(--shadow-modal)]">
                <div class="mb-5 flex items-start justify-between gap-4">
                    <div>
                        <h2 id="document-history-title" class="text-lg font-bold text-content" x-text="selected?.title ?? 'Dettaglio evento'"></h2>
                        <p class="mt-1 text-sm text-content-muted" x-text="selected?.occurred_at ?? ''"></p>
                    </div>
                    <button
                        type="button"
                        x-ref="historyClose"
                        class="rounded-md p-1 text-content-muted hover:bg-surface-muted"
                        @click="close()"
                        aria-label="Chiudi"
                    >
                        <x-icon name="o-x-mark" class="size-5" />
                    </button>
                </div>

                <dl class="space-y-4 text-sm">
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-content-muted">Stato</dt>
                        <dd class="mt-1 font-medium text-content" x-text="statusLabel(selected?.status)"></dd>
                    </div>
                    <div x-show="selected?.message">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-content-muted">Dettaglio</dt>
                        <dd class="mt-1 whitespace-pre-line leading-6 text-content" x-text="selected?.message"></dd>
                    </div>
                    <div x-show="selected?.error_message">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-content-muted" x-text="errorLabel(selected)"></dt>
                        <dd class="mt-1 font-medium text-content" x-text="selected?.error_message"></dd>
                    </div>
                </dl>

                <p
                    x-show="isSdiFailure(selected)"
                    class="mt-5 rounded-lg border border-warning/30 bg-warning-bg p-3 text-sm text-warning"
                >
                    Correggi i dati segnalati, poi reinvia il documento allo SDI. Se il messaggio parla di credito, verifica prima il servizio del provider.
                </p>

                <div class="mt-6 flex justify-end">
                    <button type="button" class="btn-ghost" @click="close()">Chiudi</button>
                </div>
            </div>
        </div>
    </template>
</div>
