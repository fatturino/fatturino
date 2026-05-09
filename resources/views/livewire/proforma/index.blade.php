<div>
    <!-- HEADER -->
    <x-header :title="__('app.proforma.title')" separator progress-indicator>
        <x-slot:actions>
            @unless($isReadOnly)
                <x-button :label="__('app.common.create')" link="/proforma/create" icon="o-plus" variant="primary" responsive />
            @endunless
        </x-slot:actions>
    </x-header>

    {{-- Read-only banner for concluded fiscal years --}}
    @if($isReadOnly)
        <x-alert
            :title="__('app.proforma.readonly_banner', ['year' => $fiscalYear])"
            icon="o-lock-closed"
            variant="warning" class="mb-4"
        />
    @endif

    {{-- Summary stats --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <x-stat :title="__('app.proforma.stat_total')" icon="o-clipboard-document-list" :value="$this->stats['total_count']" />
        <x-stat :title="__('app.proforma.stat_total_amount')" icon="o-banknotes" value="€ {{ number_format($this->stats['total_gross'] / 100, 2, ',', '.') }}" />
        <x-stat :title="__('app.proforma.stat_converted')" icon="o-arrow-right-end-on-rectangle" :value="$this->stats['converted_count']" />
        <x-stat :title="__('app.proforma.stat_unpaid')" icon="o-exclamation-triangle" :value="$this->stats['unpaid_count']" :color="$this->stats['unpaid_count'] > 0 ? 'text-warning' : ''" />
    </div>

        <!-- TABLE -->
    
    <x-table :headers="$headers" :rows="$proformas" :sort-by="$sortBy" with-pagination link="/proforma/{id}/edit">
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
                :label="__('app.proforma.filter_status')"
                :options="$this->statusOptions"
                wire:model.live="filterStatus"
                :placeholder="__('app.common.all')"
                option-value="id"
                option-label="name"
            />

            <x-select
                :label="__('app.proforma.filter_payment')"
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
