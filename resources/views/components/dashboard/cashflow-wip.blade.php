<x-card class="opacity-60 cursor-not-allowed">
    <div class="flex items-start justify-between mb-3">
        <div class="flex items-center gap-2">
            <x-icon name="o-chart-bar" class="w-5 h-5 text-base-content/50" />
            <span class="font-semibold text-base-content/70">{{ __('app.dashboard.wip_cashflow') }}</span>
        </div>
        <x-badge value="WIP" variant="neutral" size="sm" />
    </div>
    <p class="text-sm text-base-content/50">{{ __('app.dashboard.wip_cashflow_desc') }}</p>
</x-card>
