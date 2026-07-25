@props([
    'label',
    'value',
    'detail',
    'trend' => null,
    'trendClass' => 'text-content-muted',
    'series' => [],
    'progress' => null,
    'progressClass' => 'bg-primary',
])

@php
    $series = array_values(array_filter($series, fn ($value) => is_numeric($value)));
    $seriesMax = max($series ?: [1]);
    $safeSeriesMax = $seriesMax > 0 ? $seriesMax : 1;
    $pointCount = count($series);
    $points = collect($series)
        ->map(function ($value, $index) use ($pointCount, $safeSeriesMax) {
            $x = $pointCount > 1 ? $index * 100 / ($pointCount - 1) : 50;
            $y = 38 - ($value / $safeSeriesMax * 32);

            return number_format($x, 1, '.', '').','.number_format($y, 1, '.', '');
        })
        ->implode(' ');
@endphp

<article class="relative overflow-hidden rounded-xl border border-border-light bg-white p-5 shadow-[var(--shadow-card)]">
    <div class="flex items-start justify-between gap-4">
        <div class="min-w-0">
            <p class="text-xs font-bold uppercase tracking-[.08em] text-content-muted">{{ $label }}</p>
            <p class="mt-2 text-2xl font-bold tabular-nums text-content">{{ $value }}</p>
            <p @class(['mt-1 text-sm', $trendClass])>{{ $trend ?? $detail }}</p>
        </div>

        @if($pointCount > 1 && $seriesMax > 0)
            <svg viewBox="0 0 100 42" class="h-12 w-24 shrink-0" role="img" aria-label="Andamento {{ $label }}">
                <path d="M 0 42 L {{ $points }} L 100 42 Z" class="fill-primary/10" />
                <polyline points="{{ $points }}" fill="none" stroke="var(--color-primary)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
        @endif
    </div>

    @if($progress !== null)
        <div class="mt-4 h-1.5 overflow-hidden rounded-full bg-surface-muted" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ round($progress) }}">
            <div @class(['h-full rounded-full', $progressClass]) style="width: {{ min(100, max(0, $progress)) }}%"></div>
        </div>
        <p class="mt-2 text-xs text-content-muted">{{ $detail }}</p>
    @endif
</article>
