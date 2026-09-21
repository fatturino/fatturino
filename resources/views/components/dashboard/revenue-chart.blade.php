@props(['revenueTrend', 'revenueProjection' => [], 'revenueYtd' => null, 'fiscalYear'])

@php
    // ReportService exposes monetary amounts in cents; Wirecharts should receive
    // euro values so axes and tooltips remain immediately readable.
    $current = array_map(fn ($value) => round($value / 100, 2), $revenueTrend['current'] ?? []);
    $previous = array_map(fn ($value) => round($value / 100, 2), $revenueTrend['previous'] ?? []);
    $labels = $revenueTrend['labels'] ?? [];
    $isCurrentYear = $fiscalYear === now()->year;
    $actual = array_map(
        fn ($value) => $value === null ? null : round($value / 100, 2),
        $revenueProjection['actual'] ?? $current,
    );
    $projectionTotal = $revenueProjection['total'] ?? null;
    $forecast = array_fill(0, count($labels), null);

    if ($isCurrentYear && $projectionTotal !== null) {
        $currentMonthIndex = now()->month - 1;
        $forecast[$currentMonthIndex] = $actual[$currentMonthIndex] ?? null;

        for ($index = $currentMonthIndex + 1; $index < count($forecast); $index++) {
            $forecast[$index] = isset($revenueProjection['projected'][$index])
                ? round($revenueProjection['projected'][$index] / 100, 2)
                : null;
        }
    }

    $hasData = collect($actual)->contains(fn ($value) => $value !== null && $value > 0)
        || collect($previous)->contains(fn ($value) => $value > 0);

    $series = [
        [
            'name' => (string) $fiscalYear,
            'data' => $isCurrentYear ? $actual : $current,
            'showSymbol' => false,
            'lineStyle' => ['width' => 3, 'color' => '#5951BA'],
            'itemStyle' => ['color' => '#5951BA'],
            'markPoint' => $isCurrentYear ? [
                'symbolSize' => 9,
                'itemStyle' => ['color' => '#5951BA'],
                'label' => ['show' => false],
                'data' => [['coord' => [$labels[now()->month - 1] ?? '', $actual[now()->month - 1] ?? 0]]],
            ] : [],
        ],
    ];

    if ($isCurrentYear && $projectionTotal !== null) {
        $series[] = [
            'name' => 'Previsione',
            'data' => $forecast,
            'showSymbol' => false,
            'connectNulls' => true,
            'lineStyle' => ['width' => 2, 'type' => 'dashed', 'color' => '#5951BA'],
            'itemStyle' => ['color' => '#5951BA'],
        ];
    }

    $series[] =
        [
            'name' => (string) ($fiscalYear - 1),
            'data' => $previous,
            'showSymbol' => false,
            'lineStyle' => ['width' => 2, 'color' => '#C7CED9'],
            'itemStyle' => ['color' => '#C7CED9'],
        ];

    $formatCurrency = fn ($cents) => '€ '.number_format(((int) $cents) / 100, 2, ',', '.');
@endphp

<article class="dashboard-module p-2">
    <div class="dashboard-module-inner p-5">
    <div class="flex items-center justify-between gap-3">
        <div>
            <h2 class="font-semibold text-content">Andamento fatturato</h2>
            <p class="mt-1 text-sm text-content-muted">Confronto mensile al netto dell'IVA</p>
        </div>
        <span class="text-sm font-semibold text-content-muted">{{ $fiscalYear }}</span>
    </div>

    @if($isCurrentYear && $projectionTotal !== null)
        <div class="mt-4 flex flex-wrap gap-x-5 gap-y-1 text-sm">
            <p class="text-content"><span class="text-content-muted">Fatturati</span> <span class="font-semibold tabular-nums">{{ $formatCurrency($revenueYtd ?? $revenueProjection['consolidated']) }}</span></p>
            <p class="text-content"><span class="text-content-muted">Proiezione anno</span> <span class="font-semibold tabular-nums text-primary">{{ $formatCurrency($projectionTotal) }}</span></p>
        </div>
    @endif

    @if($hasData)
        <chart:line :series="$series" :categories="$labels" height="280" smooth />
        <p id="revenue-chart-description" class="sr-only">
            Il grafico confronta il fatturato mensile al netto dell'IVA per {{ $fiscalYear }} e {{ $fiscalYear - 1 }}.
            @if($isCurrentYear && $projectionTotal !== null)
                Include la previsione per i mesi restanti.
            @endif
        </p>
        <div class="sr-only">
            <table aria-describedby="revenue-chart-description">
                <caption>Valori mensili del fatturato al netto dell'IVA</caption>
                <thead>
                    <tr>
                        <th scope="col">Mese</th>
                        <th scope="col">{{ $fiscalYear }}</th>
                        <th scope="col">{{ $fiscalYear - 1 }}</th>
                        @if($isCurrentYear && $projectionTotal !== null)
                            <th scope="col">Previsione</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @foreach($labels as $index => $label)
                        <tr>
                            <th scope="row">{{ $label }}</th>
                            <td>{{ ($actual[$index] ?? null) === null ? 'Non disponibile' : '€ '.number_format($actual[$index], 2, ',', '.') }}</td>
                            <td>{{ ($previous[$index] ?? null) === null ? 'Non disponibile' : '€ '.number_format($previous[$index], 2, ',', '.') }}</td>
                            @if($isCurrentYear && $projectionTotal !== null)
                                <td>{{ ($forecast[$index] ?? null) === null ? 'Non disponibile' : '€ '.number_format($forecast[$index], 2, ',', '.') }}</td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <p class="py-12 text-center text-sm text-content-muted">Nessun fatturato disponibile per il confronto.</p>
    @endif
    </div>
</article>
