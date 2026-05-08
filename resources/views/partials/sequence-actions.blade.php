<x-button icon="o-pencil" wire:click="edit({{ $row->id }})" variant="ghost" size="sm" />
@if(!$row->is_system)
    <x-button icon="o-trash" wire:click="delete({{ $row->id }})" wire:confirm="{{ __('app.common.confirm_delete') }}" variant="ghost" size="sm" class="!text-error" />
@endif
