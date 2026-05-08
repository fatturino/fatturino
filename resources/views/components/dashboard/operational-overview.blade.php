@props([
    'recentInvoices',
    'isCurrentYear',
    'revenueTrend',
    'draftCount' => 0,
    'readyForSdiCount' => 0,
])

@php
    $currentMonth = $revenueTrend['current'][now()->month - 1] ?? 0;
    $prevYearMonth = $revenueTrend['previous'][now()->month - 1] ?? 0;
    $monthChange = $prevYearMonth > 0 ? round(($currentMonth - $prevYearMonth) / $prevYearMonth * 100) : null;
@endphp

<div class="grid grid-cols-1 md:grid-cols-3 gap-4">
    {{-- Bozze --}}
    <x-card class="h-full">
        <div class="flex items-center gap-2 mb-3">
            <div class="bg-warning/10 rounded-xl p-2">
                <x-icon name="o-pencil" class="w-4 h-4 text-warning" />
            </div>
            <span class="text-sm font-medium">{{ __('app.dashboard.drafts') }}</span>
        </div>
        <p class="text-2xl font-bold">{{ $draftCount }}</p>
        <p class="text-xs text-base-content/40 mt-1">{{ __('app.dashboard.drafts_desc') }}</p>
        @if($draftCount > 0 && $isCurrentYear)
            <a href="/sell-invoices" wire:navigate class="text-xs text-primary hover:underline mt-2 inline-block">
                {{ __('app.dashboard.drafts_action') }}
            </a>
        @endif
    </x-card>

    {{-- Pronte per SDI --}}
    <x-card class="h-full">
        <div class="flex items-center gap-2 mb-3">
            <div class="bg-info/10 rounded-xl p-2">
                <x-icon name="o-paper-airplane" class="w-4 h-4 text-info" />
            </div>
            <span class="text-sm font-medium">{{ __('app.dashboard.ready_for_sdi') }}</span>
        </div>
        <p class="text-2xl font-bold">{{ $readyForSdiCount }}</p>
        <p class="text-xs text-base-content/40 mt-1">{{ __('app.dashboard.ready_for_sdi_desc') }}</p>
        @if($readyForSdiCount > 0 && $isCurrentYear)
            <a href="/sell-invoices" wire:navigate class="text-xs text-primary hover:underline mt-2 inline-block">
                {{ __('app.dashboard.ready_for_sdi_action') }}
            </a>
        @endif
    </x-card>

    {{-- Ultime fatture inviate --}}
    <x-card class="h-full">
        <div class="flex items-center gap-2 mb-3">
            <div class="bg-success/10 rounded-xl p-2">
                <x-icon name="o-paper-airplane" class="w-4 h-4 text-success" />
            </div>
            <span class="text-sm font-medium">{{ __('app.dashboard.latest_sent') }}</span>
        </div>
        @if($recentInvoices->isNotEmpty())
            <div class="space-y-1.5">
                @foreach($recentInvoices->take(4) as $invoice)
                    <a href="/sell-invoices/{{ $invoice->id }}/edit"
                       class="flex justify-between text-xs hover:bg-base-200/50 rounded px-1.5 py-0.5 -mx-1.5 transition-colors">
                        <span class="truncate text-base-content/70">{{ $invoice->contact?->name ?? $invoice->number }}</span>
                        <span class="font-medium shrink-0 ml-2">€ {{ number_format($invoice->total_gross / 100, 0, ',', '.') }}</span>
                    </a>
                @endforeach
            </div>
        @else
            <p class="text-xs text-base-content/40">—</p>
        @endif
    </x-card>
</div>
