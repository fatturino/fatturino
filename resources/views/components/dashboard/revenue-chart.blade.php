@props(['revenueTrend', 'fiscalYear'])

@php
    // ReportService exposes monetary amounts in cents; Wirecharts should receive
    // euro values so axes and tooltips remain immediately readable.
    $current = array_map(fn ($value) => round($value / 100, 2), $revenueTrend['current'] ?? []);
    $previous = array_map(fn ($value) => round($value / 100, 2), $revenueTrend['previous'] ?? []);
    $labels = $revenueTrend['labels'] ?? [];
    $hasData = collect($current)->contains(fn ($value) => $value > 0)
        || collect($previous)->contains(fn ($value) => $value > 0);

    $series = [
        [
            'name' => (string) $fiscalYear,
            'data' => $current,
            'showSymbol' => false,
            'lineStyle' => ['width' => 3, 'color' => '#178b7a'],
            'itemStyle' => ['color' => '#178b7a'],
        ],
        [
            'name' => (string) ($fiscalYear - 1),
            'data' => $previous,
            'showSymbol' => false,
            'lineStyle' => ['width' => 2, 'type' => 'dashed', 'color' => '#a8b2bf'],
            'itemStyle' => ['color' => '#a8b2bf'],
        ],
    ];
@endphp

<article class="rounded-xl border border-border-light bg-white p-5 shadow-[var(--shadow-card)]">
    <div class="flex items-center justify-between gap-3">
        <div>
            <h2 class="font-bold">Andamento fatturato</h2>
            <p class="mt-1 text-sm text-content-muted">Confronto mensile al netto dell'IVA</p>
        </div>
        <span class="text-sm font-semibold text-content-muted">{{ $fiscalYear }}</span>
    </div>

    @if($hasData)
        <chart:line :series="$series" :categories="$labels" height="280" smooth />
    @else
        <p class="py-12 text-center text-sm text-content-muted">Nessun fatturato disponibile per il confronto.</p>
    @endif
</article>
