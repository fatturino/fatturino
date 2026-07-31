@props(['revenueProjection', 'fiscalYear'])

@php
    $labels = $revenueProjection['labels'] ?? [];
    $actualValues = $revenueProjection['actual'] ?? [];
    $projectedValues = $revenueProjection['projected'] ?? [];
    $average = $revenueProjection['average'] ?? null;
    $consolidated = $revenueProjection['consolidated'] ?? null;
    $future = $revenueProjection['future'] ?? null;
    $total = $revenueProjection['total'] ?? null;
    $hasProjection = $average !== null;

    // Convert cents to euros
    $actualEur = array_map(function ($value) {
        return $value !== null ? round($value / 100, 2) : null;
    }, $actualValues);
    $projectedEur = array_map(fn ($value) => round($value / 100, 2), $projectedValues);

    // Build series: actual bars + projected bars (only future months differ)
    $actualData = [];
    $futureData = [];

    foreach ($actualEur as $i => $value) {
        if ($value !== null) {
            $actualData[] = $value;
            $futureData[] = null;
        } else {
            $actualData[] = null;
            $futureData[] = $projectedEur[$i] ?? null;
        }
    }

    $hasData = collect($actualValues)->contains(fn ($v) => $v !== null && $v > 0);

    $series = [
        [
            'name' => 'Effettivo',
            'type' => 'bar',
            'data' => $actualData,
            'barWidth' => '40%',
            'itemStyle' => ['color' => '#178b7a', 'borderRadius' => [4, 4, 0, 0]],
        ],
    ];

    if ($hasProjection) {
        $series[] = [
            'name' => 'Proiezione',
            'type' => 'bar',
            'data' => $futureData,
            'barWidth' => '40%',
            'itemStyle' => [
                'color' => '#178b7a',
                'opacity' => 0.35,
                'borderRadius' => [4, 4, 0, 0],
            ],
        ];
    }

    $formatEur = fn ($cents) => '€ ' . number_format((int) $cents / 100, 2, ',', '.');
    $formattedConsolidated = $consolidated !== null ? $formatEur($consolidated) : null;
    $formattedFuture = $future !== null ? $formatEur($future) : null;
    $formattedTotal = $total !== null ? $formatEur($total) : null;
@endphp

<article class="rounded-xl border border-border-light bg-white p-5 shadow-[var(--shadow-card)]">
    <div class="flex items-center justify-between gap-3">
        <div>
            <h2 class="font-bold">Proiezione fatturato</h2>
            <p class="mt-1 text-sm text-content-muted">Stima al 31/12 basata sulla media mensile</p>
        </div>
        <span class="text-sm font-semibold text-content-muted">{{ $fiscalYear }}</span>
    </div>

    @if($hasProjection && $hasData)
        <div class="mt-4 grid grid-cols-3 gap-3">
            <div class="rounded-lg bg-surface-muted p-3 text-center">
                <p class="text-xs font-bold uppercase tracking-[.08em] text-content-muted">Consolidato YTD</p>
                <p class="mt-1 text-lg font-bold tabular-nums text-content">{{ $formattedConsolidated }}</p>
            </div>
            <div class="rounded-lg bg-surface-muted p-3 text-center">
                <p class="text-xs font-bold uppercase tracking-[.08em] text-content-muted">Previsione residua</p>
                <p class="mt-1 text-lg font-bold tabular-nums text-content">{{ $formattedFuture }}</p>
            </div>
            <div class="rounded-lg bg-primary/5 p-3 text-center">
                <p class="text-xs font-bold uppercase tracking-[.08em] text-content-muted">Fatturato probabile</p>
                <p class="mt-1 text-lg font-bold tabular-nums text-primary">{{ $formattedTotal }}</p>
            </div>
        </div>
    @endif

    @if($hasData)
        <chart:bar :series="$series" :categories="$labels" height="260" class="mt-2" />
    @else
        <p class="py-12 text-center text-sm text-content-muted">Nessun fatturato disponibile per la proiezione.</p>
    @endif

    @if(!$hasProjection && $hasData)
        <p class="mt-3 text-center text-xs text-content-muted">La proiezione è disponibile solo per l'anno fiscale in corso.</p>
    @endif
</article>
