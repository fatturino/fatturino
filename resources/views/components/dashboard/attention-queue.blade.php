@props(['items', 'firstDueDate' => null, 'urgencyCount' => null])

<article class="dashboard-module p-2">
    <div class="dashboard-module-inner p-5">
    <div class="flex items-start justify-between gap-4">
        <div><h2 class="font-semibold text-content">Richiede attenzione</h2><p class="mt-1 text-sm text-content-muted">Prima le scadenze e le azioni che possono bloccare l’incasso.</p></div>
        <x-badge :value="($urgencyCount ?? count($items)) . ' urgenze'" variant="primary" />
    </div>

    <div class="mt-5 divide-y divide-border-light">
        @foreach($items as $item)
            <x-app-link :href="$item['href']" class="dashboard-list-link dashboard-attention-row -mx-2 grid gap-x-3 gap-y-1 border-0 px-2"><span class="min-w-0"><span class="block text-sm font-medium text-content">{{ $item['title'] }}</span><span class="mt-0.5 block text-xs leading-5 text-content-muted">{{ $item['detail'] }}</span></span><span class="self-start text-right"><span @class(['block text-sm font-semibold tabular-nums', 'text-danger' => $item['tone'] === 'danger', 'text-content' => $item['tone'] !== 'danger'])>{{ $item['value'] }}</span><span class="mt-0.5 block text-xs font-medium text-primary">{{ $item['action'] }}</span></span></x-app-link>
        @endforeach

        @if($items === [] && ! ($firstDueDate['is_urgent'] ?? false))
            <div class="py-6 text-center">
                <p class="font-semibold text-content">Nessuna priorità urgente</p>
                <p class="mx-auto mt-1 max-w-sm text-sm leading-6 text-content-muted">
                    {{ $firstDueDate ? 'La prossima scadenza è riportata qui sotto.' : 'Gli incassi aperti e i documenti da inviare compariranno qui.' }}
                </p>
            </div>
        @endif

        @if($firstDueDate)
            <div class="pt-4">
                <p class="mb-2 text-xs font-semibold text-content-secondary">{{ $firstDueDate['is_urgent'] ? 'Scadenza urgente' : 'Prossima scadenza' }}</p>
                <x-dashboard.due-date-row :invoice="$firstDueDate" />
            </div>
        @endif
    </div>
    </div>
</article>
