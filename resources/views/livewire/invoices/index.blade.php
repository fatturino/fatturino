<div>
    <!-- HEADER -->
    <x-header :title="__('app.invoices.title')" separator progress-indicator>
    </x-header>

    {{-- Read-only banner for concluded fiscal years --}}
    @if($isReadOnly)
        <x-alert
            :title="__('app.dashboard.readonly_year_title', ['year' => $fiscalYear])"
            icon="o-lock-closed"
            variant="warning" class="mb-4"
        />
    @endif

    {{-- Summary stats --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        {{-- Total invoices --}}
        <div class="bg-base-100 rounded-xl border border-base-200 p-4">
            <div class="text-xs text-base-content/50 uppercase tracking-wide">{{ __('app.invoices.stat_total_invoices') }}</div>
            <div class="text-2xl font-bold mt-1">{{ $this->stats['total_count'] }}</div>
        </div>

        {{-- Total revenue --}}
        <div class="bg-base-100 rounded-xl border border-base-200 p-4">
            <div class="text-xs text-base-content/50 uppercase tracking-wide">{{ __('app.invoices.stat_total_amount') }}</div>
            <div class="text-2xl font-bold mt-1">€ {{ number_format($this->stats['total_gross'] / 100, 2, ',', '.') }}</div>
        </div>

        {{-- Unpaid --}}
        <div class="bg-base-100 rounded-xl border border-base-200 p-4">
            <div class="text-xs text-base-content/50 uppercase tracking-wide">{{ __('app.invoices.stat_unpaid') }}</div>
            <div class="text-2xl font-bold mt-1 {{ $this->stats['unpaid_count'] > 0 ? 'text-warning' : '' }}">
                {{ $this->stats['unpaid_count'] }}
                @if($this->stats['unpaid_count'] > 0)
                    <span class="text-sm font-normal text-base-content/50">/ € {{ number_format($this->stats['unpaid_amount'] / 100, 0, ',', '.') }}</span>
                @endif
            </div>
        </div>

        {{-- Overdue --}}
        <div class="bg-base-100 rounded-xl border border-base-200 p-4">
            <div class="text-xs text-base-content/50 uppercase tracking-wide">{{ __('app.invoices.stat_overdue') }}</div>
            <div class="text-2xl font-bold mt-1 {{ $this->stats['overdue_count'] > 0 ? 'text-error' : 'text-success' }}">
                {{ $this->stats['overdue_count'] }}
            </div>
        </div>
    </div>

    <!-- TABLE -->
    <x-card>
    <x-table :headers="$headers" :rows="$invoices" :sort-by="$sortBy" with-pagination link="/sell-invoices/{id}/edit" containerClass="overflow-visible">
        <x-slot:empty>
            <div class="py-8 flex flex-col items-center gap-2">
                <x-icon name="o-inbox" class="w-8 h-8" />
                <p class="text-sm">{{ __('app.common.empty_table') }}</p>
            </div>
        </x-slot:empty>

</x-table>
    </x-card>

    <!-- FILTER DRAWER -->
    <x-drawer wire:model="drawer" :title="__('app.common.filters')" right separator with-close-button class="lg:w-1/3">
        <div class="space-y-4">
            <x-input :placeholder="__('app.common.search')" wire:model.live.debounce="search" icon="o-magnifying-glass" @keydown.enter="$wire.drawer = false" />

            <x-select
                :label="__('app.invoices.filter_status')"
                :options="$this->statusOptions"
                wire:model.live="filterStatus"
                :placeholder="__('app.common.all')"
                option-value="id"
                option-label="name"
            />

            <x-select
                :label="__('app.invoices.filter_payment')"
                :options="$this->paymentOptions"
                wire:model.live="filterPayment"
                :placeholder="__('app.common.all')"
                option-value="id"
                option-label="name"
            />
        </div>

        <x-slot:actions>
            <x-button :label="__('app.common.reset')" icon="o-x-mark" wire:click="clear" spinner="clear" />
            <x-button :label="__('app.common.done')" icon="o-check" variant="primary" @click="$wire.drawer = false" />
        </x-slot:actions>
    </x-drawer>
</div>
