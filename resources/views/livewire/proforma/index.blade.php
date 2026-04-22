<div>
    <!-- HEADER -->
    <x-header :title="__('app.proforma.title')" separator progress-indicator>
        <x-slot:actions>
            @unless($isReadOnly)
                <x-button :label="__('app.common.create')" link="{{ route('proforma.create') }}" responsive icon="o-plus" class="btn-primary" />
            @endunless
        </x-slot:actions>
    </x-header>

    {{-- Read-only banner for concluded fiscal years --}}
    @if($isReadOnly)
        <x-alert
            :title="__('app.proforma.readonly_banner', ['year' => $fiscalYear])"
            icon="o-lock-closed"
            class="mb-4 alert-warning"
        />
    @endif

    {{-- Summary stats --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        {{-- Total proforma --}}
        <div class="bg-base-100 rounded-xl border border-base-200 p-4">
            <div class="text-xs text-base-content/50 uppercase tracking-wide">{{ __('app.proforma.stat_total') }}</div>
            <div class="text-2xl font-bold mt-1">{{ $this->stats['total_count'] }}</div>
        </div>

        {{-- Total amount --}}
        <div class="bg-base-100 rounded-xl border border-base-200 p-4">
            <div class="text-xs text-base-content/50 uppercase tracking-wide">{{ __('app.proforma.stat_total_amount') }}</div>
            <div class="text-2xl font-bold mt-1">€ {{ number_format($this->stats['total_gross'] / 100, 2, ',', '.') }}</div>
        </div>

        {{-- Unpaid --}}
        <div class="bg-base-100 rounded-xl border border-base-200 p-4">
            <div class="text-xs text-base-content/50 uppercase tracking-wide">{{ __('app.proforma.stat_unpaid') }}</div>
            <div class="text-2xl font-bold mt-1 {{ $this->stats['unpaid_count'] > 0 ? 'text-warning' : '' }}">
                {{ $this->stats['unpaid_count'] }}
            </div>
        </div>

        {{-- Converted --}}
        <div class="bg-base-100 rounded-xl border border-base-200 p-4">
            <div class="text-xs text-base-content/50 uppercase tracking-wide">{{ __('app.proforma.stat_converted') }}</div>
            <div class="text-2xl font-bold mt-1 text-success">
                {{ $this->stats['converted_count'] }}
            </div>
        </div>
    </div>

    <!-- TABLE -->
    <x-card>
    <x-table :headers="$headers" :rows="$proformas" :sort-by="$sortBy" with-pagination link="/proforma/{id}/edit">
        <x-slot:empty>
            <div class="py-8 flex flex-col items-center gap-2">
                <x-icon name="o-inbox" class="w-8 h-8" />
                <p class="text-sm">{{ __('app.common.empty_table') }}</p>
            </div>
        </x-slot:empty>

        @scope('cell_number', $proforma)
            <span class="font-semibold whitespace-nowrap">{{ $proforma->number }}</span>
        @endscope

        @scope('cell_date', $proforma)
            <span class="text-sm">{{ $proforma->date->format('d/m/Y') }}</span>
        @endscope

        @scope('cell_contact.name', $proforma)
            <span class="font-medium">{{ $proforma->contact?->name }}</span>
        @endscope

        @scope('cell_total_gross', $proforma)
            <div class="text-right font-semibold">
                € {{ number_format($proforma->total_gross / 100, 2, ',', '.') }}
            </div>
        @endscope

        @scope('cell_status', $proforma)
            <x-badge :value="$proforma->status->label()" :class="$proforma->status->color()" />
        @endscope

        @scope('cell_payment_status', $proforma)
            <div class="flex items-center gap-2">
                <x-badge :value="$proforma->payment_status->label()" :class="$proforma->payment_status->color() . ' whitespace-nowrap'" />
                @if($proforma->due_date)
                    <span class="text-xs text-base-content/40">{{ $proforma->due_date->format('d/m') }}</span>
                @endif
            </div>
        @endscope

        @scope('actions', $proforma)
            <x-dropdown>
                <x-slot:trigger>
                    <x-button icon="o-ellipsis-vertical" class="btn-ghost btn-sm btn-square" />
                </x-slot:trigger>

                {{-- Download PDF --}}
                <x-menu-item
                    :title="__('app.invoices.download_pdf')"
                    icon="o-document-text"
                    wire:click="downloadPdf({{ $proforma->id }})"
                    spinner
                />

                {{-- Convert to invoice --}}
                @if($proforma->isConvertible())
                    <x-menu-item
                        :title="__('app.proforma.convert_to_invoice')"
                        icon="o-document-check"
                        wire:click="convertToInvoice({{ $proforma->id }})"
                        wire:confirm="{{ __('app.proforma.confirm_convert') }}"
                        spinner
                    />
                @endif

                {{-- Send email --}}
                @if($proforma->contact?->email)
                    <x-menu-item
                        :title="__('app.email.send_email')"
                        icon="o-envelope"
                        wire:click="sendEmail({{ $proforma->id }})"
                        wire:confirm="{{ __('app.email.confirm_send') }}"
                        spinner
                    />
                @endif

                {{-- Delete --}}
                @if(!$this->isReadOnly && $proforma->status !== \App\Enums\ProformaStatus::Converted)
                    <hr class="my-1" />
                    <x-menu-item
                        :title="__('app.common.delete')"
                        icon="o-trash"
                        wire:click="delete({{ $proforma->id }})"
                        wire:confirm="{{ __('app.common.confirm_delete') }}"
                        class="text-error"
                        spinner
                    />
                @endif
            </x-dropdown>
        @endscope
    </x-table>
    </x-card>

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
            <x-button :label="__('app.common.reset')" icon="o-x-mark" wire:click="clear" spinner />
            <x-button :label="__('app.common.done')" icon="o-check" class="btn-primary" @click="$wire.drawer = false" />
        </x-slot:actions>
    </x-drawer>
</div>
