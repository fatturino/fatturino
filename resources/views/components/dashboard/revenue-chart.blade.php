@props(['revenueTrend'])

@php
    $current = $revenueTrend['current'] ?? [];
    $previous = $revenueTrend['previous'] ?? [];
    $labels = $revenueTrend['labels'] ?? [];

    $maxVal = max(array_merge($current, $previous)) ?: 1;

    // Generate SVG polyline points
    $chartW = 280;
    $chartH = 110;
    $padX = 12;
    $padY = 16;

    $pointsCurrent = '';
    $pointsPrevious = '';
    $stepX = count($current) > 1 ? ($chartW - $padX * 2) / (count($current) - 1) : 0;

    foreach ($current as $i => $val) {
        $x = $padX + $i * $stepX;
        $y = $chartH - $padY - ($val / $maxVal) * ($chartH - $padY * 2);
        $pointsCurrent .= number_format($x, 1) . ',' . number_format($y, 1) . ' ';
    }
    foreach ($previous as $i => $val) {
        $x = $padX + $i * $stepX;
        $y = $chartH - $padY - ($val / $maxVal) * ($chartH - $padY * 2);
        $pointsPrevious .= number_format($x, 1) . ',' . number_format($y, 1) . ' ';
    }
@endphp

<x-card class="h-full">
    <x-card-header icon="o-chart-line-up" :title="__('app.dashboard.revenue_trend')" />

    {{-- Legend --}}
    <div class="flex items-center gap-4 mb-3 text-xs">
        <div class="flex items-center gap-1.5">
            <span class="w-2.5 h-0.5 rounded-full bg-primary inline-block"></span>
            <span class="text-base-content/60">{{ $fiscalYear ?? now()->year }}</span>
        </div>
        <div class="flex items-center gap-1.5">
            <span class="w-2.5 h-0.5 rounded-full bg-base-300 inline-block"></span>
            <span class="text-base-content/60">{{ ($fiscalYear ?? now()->year) - 1 }}</span>
        </div>
    </div>

    {{-- SVG Chart --}}
    <svg viewBox="0 0 {{ $chartW }} {{ $chartH }}" class="w-full h-auto">
        {{-- Grid lines --}}
        @for($i = 0; $i <= 4; $i++)
            @php $gy = $padY + ($chartH - $padY * 2) * ($i / 4); @endphp
            <line x1="{{ $padX }}" y1="{{ $gy }}" x2="{{ $chartW - $padX }}" y2="{{ $gy }}"
                  stroke="var(--color-base-300)" stroke-width="1" />
        @endfor

        {{-- Previous year line --}}
        <polyline points="{{ trim($pointsPrevious) }}"
                  fill="none" stroke="var(--color-base-300)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" stroke-dasharray="4,3" />

        {{-- Current year line --}}
        <polyline points="{{ trim($pointsCurrent) }}"
                  fill="none" stroke="var(--color-primary)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />

        {{-- Current year dots --}}
        @foreach($current as $i => $val)
            @php
                $x = $padX + $i * $stepX;
                $y = $chartH - $padY - ($val / $maxVal) * ($chartH - $padY * 2);
                $isLast = $i === count($current) - 1;
            @endphp
            @if($isLast && $val > 0)
                <circle cx="{{ number_format($x, 1) }}" cy="{{ number_format($y, 1) }}" r="3"
                        fill="var(--color-primary)" stroke="white" stroke-width="1.5" />
            @endif
        @endforeach
    </svg>

    {{-- Month labels --}}
    <div class="flex justify-between mt-1.5 text-[10px] text-base-content/40">
        @foreach($labels as $label)
            <span class="w-5 text-center">{{ $label }}</span>
        @endforeach
    </div>
</x-card>
