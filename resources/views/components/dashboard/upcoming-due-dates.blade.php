@props(['invoices'])

<article class="dashboard-module p-2">
    <div class="dashboard-module-inner p-5">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h2 class="font-semibold text-content">Prossime scadenze</h2>
                <p class="mt-1 text-sm text-content-muted">Per data di pagamento prevista</p>
            </div>
            <x-app-link href="/sell-invoices?payment=open" class="shrink-0 text-sm font-semibold text-primary underline-offset-4 hover:underline">Vedi aperte</x-app-link>
        </div>

        <div class="mt-5 space-y-2">
            @forelse($invoices as $invoice)
                @php
                    $days = $invoice['days_until_due'];
                    [$tone, $label, $detail] = match (true) {
                        $days === null => ['default', 'Data da verificare', $invoice['due_date'] ?? 'Nessuna data prevista'],
                        $days < 0 => ['danger', 'Scaduta', 'Scaduta da '.abs($days).' '.(abs($days) === 1 ? 'giorno' : 'giorni')],
                        $days === 0 => ['danger', 'Scade oggi', 'Pagamento previsto oggi'],
                        $days <= 7 => ['warning', 'Urgente', 'Scade tra '.$days.' '.($days === 1 ? 'giorno' : 'giorni')],
                        $days <= 30 => ['info', 'Imminente', 'Scade tra '.$days.' giorni'],
                        default => ['default', 'Futura', 'Scade tra '.$days.' giorni'],
                    };
                @endphp

                <x-app-link :href="route('sell-invoices.edit', $invoice['id'])" @class(['due-date-row group block', "due-date-row-{$tone}"])>
                    <span @class(['due-date-marker', "due-date-marker-{$tone}"] ) aria-hidden="true">
                        <span class="text-sm font-bold tabular-nums">{{ $days === null ? '—' : abs($days) }}</span>
                        <span class="text-[0.625rem] font-bold uppercase tracking-wide">{{ $days === null ? '' : 'gg' }}</span>
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="flex flex-wrap items-center gap-x-2 gap-y-1">
                            <span class="min-w-0 break-words text-sm font-semibold text-content">{{ $invoice['contact'] ?? 'Cliente non associato' }}</span>
                            <span @class(['due-date-badge', "due-date-badge-{$tone}"])>{{ $label }}</span>
                        </span>
                        <span class="mt-1 block text-xs leading-5 text-content-muted">{{ $detail }} · {{ $invoice['due_date'] ?? 'Data non disponibile' }}</span>
                    </span>
                    <span class="shrink-0 text-right">
                        <span class="block text-sm font-semibold tabular-nums text-content">{{ '€ '.number_format($invoice['remaining_balance'] / 100, 2, ',', '.') }}</span>
                        <span class="mt-1 hidden text-xs font-semibold text-primary sm:block">Apri fattura</span>
                    </span>
                </x-app-link>
            @empty
                <div class="due-date-empty">
                    <p class="font-semibold text-content">Nessuna scadenza aperta</p>
                    <p class="mt-1 text-sm leading-6 text-content-muted">Non ci sono pagamenti previsti nel periodo selezionato.</p>
                </div>
            @endforelse
        </div>
    </div>
</article>
