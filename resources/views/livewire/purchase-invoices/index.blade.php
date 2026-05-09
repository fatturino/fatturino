<div>
    <!-- HEADER -->
    <x-header :title="__('app.purchase_invoices.title')" separator progress-indicator>
    </x-header>

    {{-- Info banner: purchases are import-only --}}
    <x-alert
        :title="__('app.purchase_invoices.import_only_alert')"
        icon="o-information-circle"
        variant="info" class="mb-4"
    />

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
        <x-stat :title="__('app.purchase_invoices.stat_total_invoices')" icon="o-shopping-cart" :value="$this->stats['total_count']" />
        <x-stat :title="__('app.purchase_invoices.stat_total_amount')" icon="o-banknotes" value="€ {{ number_format($this->stats['total_gross'] / 100, 2, ',', '.') }}" />
        <x-stat :title="__('app.purchase_invoices.stat_unpaid')" icon="o-exclamation-triangle" :value="$this->stats['unpaid_count']" :color="$this->stats['unpaid_count'] > 0 ? 'text-warning' : ''" />
        <x-stat :title="__('app.purchase_invoices.stat_overdue')" icon="o-clock" :value="$this->stats['overdue_count']" :color="$this->stats['overdue_count'] > 0 ? 'text-error' : 'text-success'" />
    </div>

        <!-- TABLE -->    <!-- TABLE -->
    
    <x-table :headers="$headers" :rows="$invoices" :sort-by="$sortBy" with-pagination link="/purchase-invoices/{id}/edit">
        <x-slot:empty>
            <div class="py-8 flex flex-col items-center gap-2">
                <x-icon name="o-inbox" class="w-8 h-8" />
                <p class="text-sm">{{ __('app.common.empty_table') }}</p>
            </div>
        </x-slot:empty>

</x-table>
    

    <!-- FILTER DRAWER -->
    <x-drawer wire:model="drawer" :title="__('app.common.filters')" right separator with-close-button class="lg:w-1/3">
        <div class="space-y-4">
            <x-input :placeholder="__('app.common.search')" wire:model.live.debounce="search" icon="o-magnifying-glass" @keydown.enter="$wire.drawer = false" />

            <x-select
                :label="__('app.purchase_invoices.filter_status')"
                :options="$this->statusOptions"
                wire:model.live="filterStatus"
                :placeholder="__('app.common.all')"
                option-value="id"
                option-label="name"
            />

            <x-select
                :label="__('app.purchase_invoices.filter_payment')"
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
