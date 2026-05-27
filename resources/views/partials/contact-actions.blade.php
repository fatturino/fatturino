<x-button icon="o-trash" wire:click="delete({{ $row['id'] }})" wire:confirm="{{ __('app.common.confirm_delete') }}" spinner="delete" variant="ghost" size="sm" class="!text-error" />
