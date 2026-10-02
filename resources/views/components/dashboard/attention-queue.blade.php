@props(['items', 'firstDueDate' => null, 'urgencyCount' => null])

<article class="dashboard-module p-2">
    <div class="dashboard-module-inner p-5">
    <div class="flex items-start justify-between gap-4">
        <div><h2 class="font-semibold text-content">Richiede attenzione</h2><p class="mt-1 text-sm text-content-muted">Prima le scadenze e le azioni che possono bloccare l’incasso.</p></div>
        <x-badge :value="($urgencyCount ?? count($items)) . ' urgenze'" variant="primary" />
    </div>

    <div class="mt-5 divide-y divide-border-light">
        @foreach($items as $item)
            @if(($item['type'] ?? null) === 'proforma_issuance')
                <div @class(['dashboard-attention-row -mx-2 grid gap-x-3 gap-y-1 px-2 py-3 sm:grid-cols-[minmax(0,1fr)_auto]', 'bg-danger-bg/60' => $item['tone'] === 'danger'])>
                    <div class="min-w-0"><p class="text-sm font-medium text-content">{{ $item['title'] }}</p><p class="mt-0.5 text-xs leading-5 text-content-muted">{{ $item['detail'] }}</p><p @class(['mt-1 text-xs font-medium leading-5', 'text-danger' => $item['tone'] === 'danger', 'text-warning' => $item['tone'] === 'warning'])>{{ $item['meta'] }}</p></div>
                    <div class="flex items-start justify-between gap-3 sm:block sm:text-right"><span @class(['block text-sm font-semibold tabular-nums', 'text-danger' => $item['tone'] === 'danger', 'text-warning' => $item['tone'] === 'warning'])>{{ $item['value'] }}</span>@if($item['convert_action'])<form method="POST" action="{{ $item['convert_action'] }}" class="mt-2">@csrf<button type="submit" class="inline-flex min-h-9 items-center rounded-md bg-primary px-3 text-xs font-semibold text-white transition-colors hover:bg-primary-hover">{{ $item['action'] }}</button></form>@else<x-app-link :href="$item['href']" class="mt-2 inline-flex min-h-9 items-center text-xs font-semibold text-primary underline-offset-4 hover:underline">{{ $item['action'] }}</x-app-link>@endif</div>
                </div>
            @else
                <x-app-link :href="$item['href']" class="dashboard-list-link dashboard-attention-row -mx-2 grid gap-x-3 gap-y-1 border-0 px-2"><span class="min-w-0"><span class="block text-sm font-medium text-content">{{ $item['title'] }}</span><span class="mt-0.5 block text-xs leading-5 text-content-muted">{{ $item['detail'] }}</span></span><span class="self-start text-right"><span @class(['block text-sm font-semibold tabular-nums', 'text-danger' => $item['tone'] === 'danger', 'text-content' => $item['tone'] !== 'danger'])>{{ $item['value'] }}</span><span class="mt-0.5 block text-xs font-medium text-primary">{{ $item['action'] }}</span></span></x-app-link>
            @endif
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
