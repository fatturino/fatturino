<div>
    <!-- HEADER -->
    <x-header :title="__('app.credit_notes.title')" separator progress-indicator>
        <x-slot:actions>
            @unless($isReadOnly)
                <x-button :label="__('app.common.create')" link="{{ route('credit-notes.create') }}" responsive icon="o-plus" class="btn-primary" />
            @endunless
            <x-button icon="o-funnel" @click="$wire.drawer = true" responsive class="btn-ghost" />
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
        <div class="bg-base-100 rounded-xl border border-base-200 p-4">
            <div class="text-xs text-base-content/50 uppercase tracking-wide">{{ __('app.credit_notes.stat_total_notes') }}</div>
            <div class="text-2xl font-bold mt-1">{{ $this->stats['total_count'] }}</div>
        </div>

        <div class="bg-base-100 rounded-xl border border-base-200 p-4">
            <div class="text-xs text-base-content/50 uppercase tracking-wide">{{ __('app.credit_notes.stat_total_amount') }}</div>
            <div class="text-2xl font-bold mt-1">€ {{ number_format($this->stats['total_gross'] / 100, 2, ',', '.') }}</div>
        </div>
    </div>

    <!-- TABLE -->
    <x-card>
        <x-table :headers="$headers" :rows="$creditNotes" :sort-by="$sortBy" with-pagination link="/credit-notes/{id}/edit">
            <x-slot:empty>
                <div class="py-8 flex flex-col items-center gap-2">
                    <x-icon name="o-inbox" class="w-8 h-8" />
                    <p class="text-sm">{{ __('app.common.empty_table') }}</p>
                </div>
            </x-slot:empty>

            @scope('cell_number', $creditNote)
                <span class="font-semibold whitespace-nowrap">{{ $creditNote->number }}</span>
            @endscope

            @scope('cell_date', $creditNote)
                <span class="text-sm">{{ $creditNote->date->format('d/m/Y') }}</span>
            @endscope

            @scope('cell_contact.name', $creditNote)
                <span class="font-medium">{{ $creditNote->contact?->name }}</span>
            @endscope

            @scope('cell_total_gross', $creditNote)
                <div class="text-right font-semibold">
                    € {{ number_format($creditNote->total_gross / 100, 2, ',', '.') }}
                </div>
            @endscope

            @scope('cell_status', $creditNote)
                @if($creditNote->sdi_status)
                    <x-badge :value="$creditNote->sdi_status->label()" :class="$creditNote->sdi_status->color()" />
                @else
                    <x-badge :value="$creditNote->status->label()" :class="$creditNote->status->color()" />
                @endif
            @endscope

            @scope('actions', $creditNote)
                @if(!$this->isReadOnly && $creditNote->isSdiEditable())
                    <x-dropdown>
                        <x-slot:trigger>
                            <x-button icon="o-ellipsis-vertical" class="btn-ghost btn-sm btn-square" />
                        </x-slot:trigger>

                        <x-menu-item
                            :title="__('app.common.delete')"
                            icon="o-trash"
                            wire:click="delete({{ $creditNote->id }})"
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
                :label="__('app.credit_notes.filter_status')"
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
