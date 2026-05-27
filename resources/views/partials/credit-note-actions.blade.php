@if(!$this->isReadOnly && $row->isSdiEditable())
    <x-dropdown>
        <x-slot:trigger>
            <x-button icon="o-ellipsis-vertical" variant="ghost" size="sm" />
        </x-slot:trigger>
        <x-menu-item
            :title="__('app.common.delete')"
            icon="o-trash"
            wire:click="delete({{ $row->id }})"
            wire:confirm="{{ __('app.common.confirm_delete') }}"
            class="text-error"
            spinner
        />
    </x-dropdown>
@endif
