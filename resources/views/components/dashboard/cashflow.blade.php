@props(['buckets'])

@php
    // Check if there's any meaningful data to display
    $hasData = collect($buckets)->some(fn($b) => $b['inflows'] > 0 || $b['outflows'] > 0);

    // Compute the max absolute value for bar scaling
    $maxValue = collect($buckets)
        ->flatMap(fn($b) => [$b['inflows'], $b['outflows']])
        ->max() ?: 1;
@endphp

<x-card class="h-full">
    <x-card-header icon="o-chart-bar" :title="__('app.dashboard.cashflow_title')" class="mb-4" />

    @if(! $hasData)
        <p class="text-sm text-base-content/50 text-center py-4">
            {{ __('app.dashboard.no_cashflow_data') }}
        </p>
    @else
        <div class="space-y-2">
            @foreach($buckets as $bucket)
                @php
                    $isOverdue   = $bucket['key'] === 'overdue';
                    $net         = $bucket['net'];
                    $inflowsPct  = $maxValue > 0 ? min(100, ($bucket['inflows'] / $maxValue) * 100) : 0;
                    $outflowsPct = $maxValue > 0 ? min(100, ($bucket['outflows'] / $maxValue) * 100) : 0;
                @endphp

                <div @class([
                    'rounded-lg px-2 py-1.5',
                    'bg-error/5 border border-error/20' => $isOverdue && ($bucket['inflows'] > 0 || $bucket['outflows'] > 0),
                ])>
                    {{-- Row label + net --}}
                    <div class="flex items-center justify-between mb-1">
                        <span @class([
                            'text-xs font-semibold',
                            'text-error' => $isOverdue,
                            'text-base-content/70' => ! $isOverdue,
                        ])>
                            {{ $bucket['label'] }}
                        </span>
                        @if($bucket['inflows'] > 0 || $bucket['outflows'] > 0)
                            <span @class([
                                'text-xs font-bold tabular-nums',
                                'text-success' => $net >= 0,
                                'text-error'   => $net < 0,
                            ])>
                                {{ $net >= 0 ? '+' : '' }}€ {{ number_format(abs($net) / 100, 0, ',', '.') }}
                            </span>
                        @else
                            <span class="text-xs text-base-content/30">—</span>
                        @endif
                    </div>

                    @if($bucket['inflows'] > 0 || $bucket['outflows'] > 0)
                        {{-- Mini bar: inflows (green) --}}
                        @if($bucket['inflows'] > 0)
                            <div class="flex items-center gap-1.5 mb-0.5">
                                <span class="w-1.5 h-1.5 rounded-full bg-success shrink-0"></span>
                                <div class="flex-1 h-1.5 bg-base-200 rounded-full overflow-hidden">
                                    <div class="h-full bg-success rounded-full" style="width: {{ $inflowsPct }}%"></div>
                                </div>
                                <span class="text-xs text-success/80 tabular-nums w-14 text-right shrink-0">
                                    € {{ number_format($bucket['inflows'] / 100, 0, ',', '.') }}
                                </span>
                            </div>
                        @endif

                        {{-- Mini bar: outflows (red) --}}
                        @if($bucket['outflows'] > 0)
                            <div class="flex items-center gap-1.5">
                                <span class="w-1.5 h-1.5 rounded-full bg-error shrink-0"></span>
                                <div class="flex-1 h-1.5 bg-base-200 rounded-full overflow-hidden">
                                    <div class="h-full bg-error rounded-full" style="width: {{ $outflowsPct }}%"></div>
                                </div>
                                <span class="text-xs text-error/80 tabular-nums w-14 text-right shrink-0">
                                    € {{ number_format($bucket['outflows'] / 100, 0, ',', '.') }}
                                </span>
                            </div>
                        @endif
                    @endif
                </div>
            @endforeach
        </div>

        {{-- Legend --}}
        <div class="flex items-center gap-3 mt-3 pt-2 border-t border-base-200">
            <div class="flex items-center gap-1 text-xs text-base-content/50">
                <span class="w-2 h-2 rounded-full bg-success inline-block"></span>
                {{ __('app.dashboard.cashflow_inflows') }}
            </div>
            <div class="flex items-center gap-1 text-xs text-base-content/50">
                <span class="w-2 h-2 rounded-full bg-error inline-block"></span>
                {{ __('app.dashboard.cashflow_outflows') }}
            </div>
        </div>
    @endif
</x-card>
