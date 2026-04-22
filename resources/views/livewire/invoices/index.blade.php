<div>
    <!-- HEADER -->
    <x-header :title="__('app.invoices.title')" separator progress-indicator>
        <x-slot:actions>
            @unless($isReadOnly)
                <x-button :label="__('app.common.create')" link="{{ route('sell-invoices.create') }}" responsive icon="o-plus" class="btn-primary" />
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
            {{-- Right-aligned monospace amount --}}
            <div class="text-right font-semibold">
                € {{ number_format($invoice->total_gross / 100, 2, ',', '.') }}
            </div>
        @endscope

        @scope('cell_status', $invoice)
            @if($invoice->sdi_status)
                {{-- SDI status takes priority when invoice has been submitted --}}
                <x-badge :value="$invoice->sdi_status->label()" :class="$invoice->sdi_status->color()" />
            @else
                <x-badge :value="$invoice->status->label()" :class="$invoice->status->color()" />
            @endif
        @endscope

        @scope('cell_payment_status', $invoice)
            <x-badge :value="$invoice->payment_status->label()" :class="$invoice->payment_status->color() . ' whitespace-nowrap'" />
        @endscope

        @scope('actions', $invoice)
            {{-- noXAnchor = pure CSS positioning (no JS x-anchor jump); overflow-visible on x-table prevents clipping --}}
            <x-dropdown noXAnchor right>
                <x-slot:trigger>
                    <x-button icon="o-ellipsis-vertical" class="btn-ghost btn-sm btn-square" />
                </x-slot:trigger>

                {{-- Download PDF --}}
                <x-menu-item
                    :title="__('app.invoices.download_pdf')"
                    icon="o-document-text"
                    wire:click="downloadPdf({{ $invoice->id }})"
                    spinner
                />

                {{-- Download XML --}}
                <x-menu-item
                    :title="__('app.invoices.download_xml')"
                    icon="o-arrow-down-tray"
                    wire:click="downloadXml({{ $invoice->id }})"
                    spinner
                />

                {{-- Validate XML --}}
                @if(!$this->isReadOnly && $invoice->isSdiEditable() && $invoice->status->canValidateXml())
                    <x-menu-item
                        :title="__('app.invoices.validate_xml')"
                        icon="o-shield-check"
                        wire:click="validateXml({{ $invoice->id }})"
                        wire:confirm="{{ __('app.invoices.confirm_validate_xml') }}"
                        spinner
                    />
                @endif

                {{-- Send to SDI --}}
                @if(!$this->isReadOnly && $invoice->isSdiEditable() && $invoice->status->canSendToSdi())
                    <x-menu-item
                        :title="__('app.invoices.send_to_sdi')"
                        icon="o-paper-airplane"
                        wire:click="sendToSdi({{ $invoice->id }})"
                        wire:confirm="{{ __('app.invoices.confirm_send_sdi') }}"
                        spinner
                    />
                @endif

                {{-- Send email --}}
                @if($invoice->contact?->email)
                    <x-menu-item
                        :title="__('app.email.send_email')"
                        icon="o-envelope"
                        wire:click="sendEmail({{ $invoice->id }})"
                        wire:confirm="{{ __('app.email.confirm_send') }}"
                        spinner
                    />
                @endif

                {{-- Delete (separated visually) --}}
                @if(!$this->isReadOnly && $invoice->isSdiEditable())
                    <hr class="my-1" />
                    <x-menu-item
                        :title="__('app.common.delete')"
                        icon="o-trash"
                        wire:click="delete({{ $invoice->id }})"
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
            <x-button :label="__('app.common.reset')" icon="o-x-mark" wire:click="clear" spinner />
            <x-button :label="__('app.common.done')" icon="o-check" class="btn-primary" @click="$wire.drawer = false" />
        </x-slot:actions>
    </x-drawer>
</div>
