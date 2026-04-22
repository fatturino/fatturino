<div>
    <!-- HEADER -->
    <x-header :title="__('app.purchase_invoices.title')" separator progress-indicator>
        <x-slot:actions>
        </x-slot:actions>
    </x-header>

    {{-- Info banner: purchases are import-only --}}
    <x-alert
        :title="__('app.purchase_invoices.import_only_alert')"
        icon="o-information-circle"
        class="mb-4 alert-info"
    />

    {{-- Read-only banner for concluded fiscal years --}}
    @if($isReadOnly)
        <x-alert
            :title="__('app.dashboard.readonly_year_title', ['year' => $fiscalYear])"
            icon="o-lock-closed"
            class="mb-4 alert-warning"
        />
    @endif

    {{-- Summary stats --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        {{-- Total purchases --}}
        <div class="bg-base-100 rounded-xl border border-base-200 p-4">
            <div class="text-xs text-base-content/50 uppercase tracking-wide">{{ __('app.purchase_invoices.stat_total_invoices') }}</div>
            <div class="text-2xl font-bold mt-1">{{ $this->stats['total_count'] }}</div>
        </div>

        {{-- Total expenses --}}
        <div class="bg-base-100 rounded-xl border border-base-200 p-4">
            <div class="text-xs text-base-content/50 uppercase tracking-wide">{{ __('app.purchase_invoices.stat_total_amount') }}</div>
            <div class="text-2xl font-bold mt-1">€ {{ number_format($this->stats['total_gross'] / 100, 2, ',', '.') }}</div>
        </div>

        {{-- Unpaid --}}
        <div class="bg-base-100 rounded-xl border border-base-200 p-4">
            <div class="text-xs text-base-content/50 uppercase tracking-wide">{{ __('app.purchase_invoices.stat_unpaid') }}</div>
            <div class="text-2xl font-bold mt-1 {{ $this->stats['unpaid_count'] > 0 ? 'text-warning' : '' }}">
                {{ $this->stats['unpaid_count'] }}
                @if($this->stats['unpaid_count'] > 0)
                    <span class="text-sm font-normal text-base-content/50">/ € {{ number_format($this->stats['unpaid_amount'] / 100, 0, ',', '.') }}</span>
                @endif
            </div>
        </div>

        {{-- Overdue --}}
        <div class="bg-base-100 rounded-xl border border-base-200 p-4">
            <div class="text-xs text-base-content/50 uppercase tracking-wide">{{ __('app.purchase_invoices.stat_overdue') }}</div>
            <div class="text-2xl font-bold mt-1 {{ $this->stats['overdue_count'] > 0 ? 'text-error' : 'text-success' }}">
                {{ $this->stats['overdue_count'] }}
            </div>
        </div>
    </div>

    <!-- TABLE -->
    <x-card>
    <x-table :headers="$headers" :rows="$invoices" :sort-by="$sortBy" with-pagination link="/purchase-invoices/{id}/edit">
        <x-slot:empty>
            <div class="py-8 flex flex-col items-center gap-2">
                <x-icon name="o-inbox" class="w-8 h-8" />
                <p class="text-sm">{{ __('app.common.empty_table') }}</p>
            </div>
        </x-slot:empty>

        @scope('cell_number', $invoice)
            <span class="font-semibold whitespace-nowrap">{{ $invoice->number }}</span>
        @endscope

        @scope('cell_date', $invoice)
            <span class="text-sm">{{ $invoice->date->format('d/m/Y') }}</span>
        @endscope

        @scope('cell_contact.name', $invoice)
            <span class="font-medium">{{ $invoice->contact?->name }}</span>
        @endscope

        @scope('cell_total_gross', $invoice)
            <div class="text-right font-semibold">
                € {{ number_format($invoice->total_gross / 100, 2, ',', '.') }}
            </div>
        @endscope

        @scope('cell_status', $invoice)
            @if($invoice->sdi_status)
                <x-badge :value="$invoice->sdi_status->label()" :class="$invoice->sdi_status->color() . ' whitespace-nowrap'" />
            @else
                <x-badge :value="$invoice->status->label()" :class="$invoice->status->color() . ' whitespace-nowrap'" />
            @endif
        @endscope

        @scope('cell_payment_status', $invoice)
            <div class="flex items-center gap-2">
                <x-badge :value="$invoice->payment_status->label()" :class="$invoice->payment_status->color() . ' whitespace-nowrap'" />
                @if($invoice->due_date)
                    <span class="text-xs text-base-content/40">{{ $invoice->due_date->format('d/m') }}</span>
                @endif
            </div>
        @endscope

        @scope('actions', $invoice)
            @if(!$this->isReadOnly && $invoice->isSdiEditable())
                <x-dropdown>
                    <x-slot:trigger>
                        <x-button icon="o-ellipsis-vertical" class="btn-ghost btn-sm btn-square" />
                    </x-slot:trigger>

                    <x-menu-item
                        :title="__('app.common.delete')"
                        icon="o-trash"
                        wire:click="delete({{ $invoice->id }})"
                        wire:confirm="{{ __('app.common.confirm_delete') }}"
                        class="text-error"
                        spinner
                    />
                </x-dropdown>
            @endif
        @endscope
    </x-table>
    </x-card>

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
            <x-button :label="__('app.common.reset')" icon="o-x-mark" wire:click="clear" spinner />
            <x-button :label="__('app.common.done')" icon="o-check" class="btn-primary" @click="$wire.drawer = false" />
        </x-slot:actions>
    </x-drawer>
</div>
