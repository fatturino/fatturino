<div>
    <!-- HEADER -->
    <x-header :title="__('app.self_invoices.title')" separator progress-indicator>
        <x-slot:actions>
            @unless($isReadOnly)
                <x-button :label="__('app.common.create')" link="{{ route('self-invoices.create') }}" responsive icon="o-plus" class="btn-primary" />
            @endunless
        </x-slot:actions>
    </x-header>

    {{-- Read-only banner for concluded fiscal years --}}
    @if($isReadOnly)
        <x-alert
            :title="__('app.dashboard.readonly_year_title', ['year' => $fiscalYear])"
            icon="o-lock-closed"
            class="mb-4 alert-warning"
        />
    @endif

    {{-- Summary stats --}}
    <div class="grid grid-cols-2 gap-4 mb-6">
        {{-- Total self-invoices --}}
        <div class="bg-base-100 rounded-xl border border-base-200 p-4">
            <div class="text-xs text-base-content/50 uppercase tracking-wide">{{ __('app.self_invoices.stat_total_invoices') }}</div>
            <div class="text-2xl font-bold mt-1">{{ $this->stats['total_count'] }}</div>
        </div>

        {{-- Total amount --}}
        <div class="bg-base-100 rounded-xl border border-base-200 p-4">
            <div class="text-xs text-base-content/50 uppercase tracking-wide">{{ __('app.self_invoices.stat_total_amount') }}</div>
            <div class="text-2xl font-bold mt-1">€ {{ number_format($this->stats['total_gross'] / 100, 2, ',', '.') }}</div>
        </div>
    </div>

    <!-- TABLE -->
    <x-card>
    <x-table :headers="$headers" :rows="$invoices" :sort-by="$sortBy" with-pagination link="/self-invoices/{id}/edit">
        <x-slot:empty>
            <div class="py-8 flex flex-col items-center gap-2">
                <x-icon name="o-inbox" class="w-8 h-8" />
                <p class="text-sm">{{ __('app.common.empty_table') }}</p>
            </div>
        </x-slot:empty>

        @scope('cell_number', $invoice)
            <span class="font-semibold whitespace-nowrap">{{ $invoice->number }}</span>
        @endscope

        @scope('cell_document_type', $invoice)
            <x-badge :value="$invoice->document_type" class="badge-ghost" />
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
                <x-badge :value="$invoice->sdi_status->label()" :class="$invoice->sdi_status->color()" />
            @else
                <x-badge :value="$invoice->status->label()" :class="$invoice->status->color()" />
            @endif
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
                :label="__('app.self_invoices.filter_status')"
                :options="$this->statusOptions"
                wire:model.live="filterStatus"
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
