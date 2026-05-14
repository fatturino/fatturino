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

    <!-- TABLE -->

    {{-- Toolbar with search, filters, and bulk actions --}}
    @unless($isReadOnly)
        <x-table-toolbar :selected-count="$this->selectedCount" class="mb-4">
            <x-slot:search>
                <x-input
                    :placeholder="__('app.common.search')"
                    wire:model.live.debounce="search"
                    icon="o-magnifying-glass"
                    class="w-full max-w-sm"
                />
            </x-slot:search>
            <x-slot:filters>
                <x-select
                    :options="$this->statusOptions"
                    wire:model.live="filterStatus"
                    :placeholder="__('app.invoices.filter_status')"
                    option-value="id"
                    option-label="name"
                    class="w-40"
                />
                <x-select
                    :options="$this->paymentOptions"
                    wire:model.live="filterPayment"
                    :placeholder="__('app.invoices.filter_payment')"
                    option-value="id"
                    option-label="name"
                    class="w-44"
                />
                <x-button
                    :label="__('app.common.reset')"
                    icon="o-x-mark"
                    variant="ghost"
                    size="sm"
                    wire:click="clear"
                    spinner="clear"
                />
            </x-slot:filters>
            <x-slot:bulk>
                <span class="text-sm font-medium whitespace-nowrap">{{ __('app.invoices.bulk_selected', ['count' => $this->selectedCount]) }}</span>
                <x-button
                    :label="__('app.invoices.bulk_mark_paid')"
                    icon="o-check-circle"
                    variant="success"
                    size="sm"
                    wire:click="markSelectedAsPaid"
                    wire:confirm="{{ __('app.invoices.bulk_confirm_mark_paid') }}"
                    spinner
                />
                <x-button
                    :label="__('app.invoices.bulk_mark_unpaid')"
                    icon="o-clock"
                    variant="warning"
                    size="sm"
                    wire:click="markSelectedAsUnpaid"
                    wire:confirm="{{ __('app.invoices.bulk_confirm_mark_unpaid') }}"
                    spinner
                />
                <x-button
                    :label="__('app.common.done')"
                    icon="o-x-mark"
                    variant="ghost"
                    size="sm"
                    wire:click="clearSelection"
                />
            </x-slot:bulk>
        </x-table-toolbar>
    @else
        {{-- Read-only: search only, no bulk actions --}}
        <div class="flex items-center gap-3 px-5 py-3 border border-base-300 rounded-lg bg-base-200/50 mb-4">
            <x-input
                :placeholder="__('app.common.search')"
                wire:model.live.debounce="search"
                icon="o-magnifying-glass"
                class="w-full max-w-sm"
            />
        </div>
    @endunless

    <x-table :headers="$headers" :rows="$invoices" :sort-by="$sortBy" with-pagination link="/purchase-invoices/{id}/edit" :selectable="!$isReadOnly" :selected-ids="$selectedIds">
        <x-slot:empty>
            <div class="py-8 flex flex-col items-center gap-2">
                <x-icon name="o-inbox" class="w-8 h-8" />
                <p class="text-sm">{{ __('app.common.empty_table') }}</p>
            </div>
        </x-slot:empty>

</x-table>
</div>
