@props([
    'paidYtd' => 0,
    'plannedTotal' => 0,
    'overdueTotal' => 0,
    'upcoming' => [],
])

@php
    $currency = fn (int $cents) => '€ '.number_format($cents / 100, 2, ',', '.');
@endphp

<section class="rounded-xl border border-border bg-white p-4 sm:p-5" aria-labelledby="f24-dashboard-title">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h2 id="f24-dashboard-title" class="text-sm font-semibold text-content">{{ __('app.f24.dashboard_title') }}</h2>
            <p class="mt-1 text-xs text-content-muted">
                {{ __('app.f24.dashboard_paid') }}:
                <span class="font-semibold tabular-nums text-content">{{ $currency((int) $paidYtd) }}</span>
                · {{ __('app.f24.dashboard_planned') }}:
                <span class="font-semibold tabular-nums text-content">{{ $currency((int) $plannedTotal) }}</span>
                @if((int) $overdueTotal > 0)
                    · <span class="font-semibold tabular-nums text-danger">{{ $currency((int) $overdueTotal) }} {{ __('app.f24.status_overdue') }}</span>
                @endif
            </p>
        </div>
        <x-app-link :href="route('f24.index')" class="text-sm font-medium text-primary hover:underline">{{ __('app.f24.dashboard_view_all') }}</x-app-link>
    </div>

    @if(count($upcoming) === 0)
        <p class="mt-4 text-sm text-content-muted">{{ __('app.f24.dashboard_empty') }}</p>
    @else
        <ul class="mt-4 divide-y divide-border">
            @foreach($upcoming as $item)
                <li class="flex items-center justify-between gap-3 py-3 text-sm">
                    <div>
                        <p class="font-medium text-content">{{ $item['label'] }}</p>
                        <p class="text-xs text-content-muted">
                            {{ $item['due_date'] ? __('app.f24.col_due').': '.$item['due_date'] : __('app.f24.status_planned') }}
                            @if($item['is_overdue'])
                                · <span class="text-danger">{{ __('app.f24.status_overdue') }}</span>
                            @endif
                        </p>
                    </div>
                    <div class="text-right">
                        <p class="font-semibold tabular-nums text-content">{{ $currency((int) $item['amount_cents']) }}</p>
                        <x-app-link :href="$item['href']" class="text-xs font-medium text-primary hover:underline">{{ __('app.f24.edit') }}</x-app-link>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</section>
