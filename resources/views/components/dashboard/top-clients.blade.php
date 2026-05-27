@props(['topClients'])

@php
    $rankStyles = [
        ['bg' => 'bg-primary/10 text-primary', 'border' => 'border-primary/30'],
        ['bg' => 'bg-secondary/10 text-secondary', 'border' => 'border-secondary/30'],
        ['bg' => 'bg-accent/10 text-accent', 'border' => 'border-accent/30'],
        ['bg' => 'bg-base-200 text-base-content/60', 'border' => 'border-base-300'],
        ['bg' => 'bg-base-200 text-base-content/60', 'border' => 'border-base-300'],
    ];
@endphp

<x-card class="h-full">
    <x-card-header icon="o-chart-bar" :title="__('app.dashboard.top_clients_title')" class="mb-4" />

    @if($topClients->isEmpty())
        <div class="flex flex-col items-center justify-center py-10 text-base-content/40">
            <x-icon name="o-chart-bar" class="w-12 h-12 mb-3" />
            <p>{{ __('app.dashboard.no_data') }}</p>
        </div>
    @else
        <div class="space-y-3">
            @foreach($topClients as $index => $row)
                @php
                    // Progress bar width relative to the top client
                    $maxRevenue = $topClients->first()->revenue_total;
                    $barWidth   = $maxRevenue > 0 ? ($row->revenue_total / $maxRevenue) * 100 : 0;
                    $style      = $rankStyles[$index] ?? $rankStyles[4];
                @endphp
                <div class="flex items-center gap-3">
                    <span class="inline-flex items-center justify-center w-6 h-6 rounded-full text-xs font-semibold border {{ $style['bg'] }} {{ $style['border'] }}">{{ $index + 1 }}</span>
                    <div class="flex-1 min-w-0">
                        <div class="flex justify-between items-baseline mb-1">
                            <span class="text-sm font-medium truncate">{{ $row->contact?->name ?? __('app.common.unknown') }}</span>
                            <span class="text-sm font-semibold shrink-0 ml-2">
                                € {{ number_format($row->revenue_total / 100, 2, ',', '.') }}
                            </span>
                        </div>
                        <div class="w-full bg-base-300 rounded-full h-1.5">
                            <div
                                class="bg-primary h-1.5 rounded-full transition-all"
                                style="width: {{ $barWidth }}%"
                            ></div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</x-card>
