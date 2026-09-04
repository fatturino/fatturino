@props(['items', 'firstDueDate' => null])

<article class="dashboard-module p-2">
    <div class="dashboard-module-inner p-5">
    <div class="flex items-start justify-between gap-4">
        <div><h2 class="font-semibold text-content">Richiede attenzione</h2><p class="mt-1 text-sm text-content-muted">Prima le scadenze e le azioni che possono bloccare l’incasso.</p></div>
        <x-badge :value="count($items) . ' priorità'" variant="primary" />
    </div>

    <div class="mt-5 divide-y divide-border-light">
        @forelse($items as $item)
            <x-app-link :href="$item['href']" class="dashboard-list-link -mx-2 flex items-center gap-3 border-0 px-2"><span @class(['size-2 shrink-0 rounded-full', 'bg-danger' => $item['tone'] === 'danger', 'bg-warning' => $item['tone'] === 'warning', 'bg-info' => $item['tone'] === 'info']) aria-hidden="true"></span><span class="min-w-0 flex-1"><span class="block text-sm font-medium text-content">{{ $item['title'] }}</span><span class="mt-0.5 block text-xs text-content-muted">{{ $item['detail'] }}</span></span><span class="shrink-0 text-right"><span @class(['block text-sm font-semibold tabular-nums', 'text-danger' => $item['tone'] === 'danger', 'text-content' => $item['tone'] !== 'danger'])>{{ $item['value'] }}</span><span class="mt-0.5 block text-xs font-medium text-primary">{{ $item['action'] }}</span></span></x-app-link>
        @empty
            <div class="py-8 text-center"><p class="font-semibold text-content">Nessuna urgenza per ora</p><p class="mx-auto mt-1 max-w-sm text-sm leading-6 text-content-muted">Gli incassi aperti e i documenti da inviare compariranno qui.</p></div>
        @endforelse

        @if($firstDueDate)
            <x-app-link :href="route('sell-invoices.edit', $firstDueDate['id'])" class="dashboard-list-link -mx-2 flex items-center gap-3 border-0 px-2"><span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-surface-muted text-xs font-bold text-content-muted">{{ $firstDueDate['days_until_due'] === 0 ? 'Oggi' : $firstDueDate['days_until_due'].'g' }}</span><span class="min-w-0 flex-1"><span class="block text-sm font-medium text-content">Prossima scadenza</span><span class="mt-0.5 block truncate text-xs text-content-muted">{{ $firstDueDate['contact'] ?? 'Cliente non associato' }} · {{ $firstDueDate['due_date'] }}</span></span><span class="shrink-0 text-sm font-semibold tabular-nums text-content">{{ '€ '.number_format($firstDueDate['remaining_balance'] / 100, 2, ',', '.') }}</span></x-app-link>
        @endif
    </div>
    </div>
</article>
