<div>
    <!-- HEADER -->
    <x-header :title="__('app.contacts.title')" separator progress-indicator>
        <x-slot:actions>
            <x-button :label="__('app.common.create')" link="/contacts/create" responsive icon="o-plus" variant="primary" />
        </x-slot:actions>
    </x-header>

    {{-- Summary stats --}}
    <div class="grid grid-cols-2 gap-4 mb-6">
        <x-stat :title="__('app.contacts.stat_total')" icon="o-users" :value="$contacts->total()" />
    </div>

    {{-- Toolbar with search --}}
    <x-table-toolbar :selected-count="$this->selectedCount" class="mb-4">
        <x-slot:search>
            <x-input
                :placeholder="__('app.common.search')"
                wire:model.live.debounce="search"
                icon="o-magnifying-glass"
                class="w-full max-w-sm"
            />
        </x-slot:search>
    </x-table-toolbar>

    <!-- TABLE  -->
        <x-table :headers="$headers" :rows="$contacts" :sort-by="$sortBy" :selectable="true" :selected-ids="$selectedIds" with-pagination link="/contacts/{id}/edit">
            <x-slot:empty>
                <div class="py-8 flex flex-col items-center gap-2">
                    <x-icon name="o-inbox" class="w-8 h-8" />
                    <p class="text-sm">{{ __('app.common.empty_table') }}</p>
                </div>
            </x-slot:empty>
        </x-table>
</div>
