<div>
    <!-- HEADER -->
    <x-header :title="__('app.contacts.title')" separator progress-indicator>
        <x-slot:actions>
            <x-button :label="__('app.common.create')" link="/contacts/create" responsive icon="o-plus" class="btn-primary" />
        </x-slot:actions>
    </x-header>

    <!-- TABLE  -->
    <x-card>
        <x-table :headers="$headers" :rows="$contacts" :sort-by="$sortBy" with-pagination link="/contacts/{id}/edit">
            <x-slot:empty>
                <div class="py-8 flex flex-col items-center gap-2">
                    <x-icon name="o-inbox" class="w-8 h-8" />
                    <p class="text-sm">{{ __('app.common.empty_table') }}</p>
                </div>
            </x-slot:empty>

            @scope('name', $contact)
            {{ $contact->name }}
            @endscope

            @scope('actions', $contact)
            <x-button icon="o-trash" wire:click="delete({{ $contact['id'] }})" wire:confirm="{{ __('app.common.confirm_delete') }}" spinner class="btn-ghost btn-sm text-red-500" />
            @endscope
        </x-table>
    </x-card>

    <!-- FILTER DRAWER -->
    <x-drawer wire:model="drawer" :title="__('app.common.filters')" right separator with-close-button class="lg:w-1/3">
        <x-input :placeholder="__('app.common.search')" wire:model.live.debounce="search" icon="o-magnifying-glass" @keydown.enter="$wire.drawer = false" />

        <x-slot:actions>
            <x-button :label="__('app.common.reset')" icon="o-x-mark" wire:click="clear" spinner />
            <x-button :label="__('app.common.done')" icon="o-check" class="btn-primary" @click="$wire.drawer = false" />
        </x-slot:actions>
    </x-drawer>
</div>
