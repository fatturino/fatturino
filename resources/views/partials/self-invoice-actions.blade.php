<x-dropdown right>
    <x-slot:trigger>
        <x-button icon="o-ellipsis-vertical" variant="ghost" size="sm" />
    </x-slot:trigger>

    <x-menu-item :title="__('app.invoices.download_pdf')" icon="o-document-text" wire:click="downloadPdf({{ $row->id }})" spinner />
    <x-menu-item :title="__('app.self_invoices.download_xml')" icon="o-arrow-down-tray" wire:click="downloadXml({{ $row->id }})" spinner />

    @if(!$this->isReadOnly && $row->isSdiEditable())
        <hr class="my-1 border-base-200" />
        <x-menu-item :title="__('app.common.delete')" icon="o-trash" wire:click="delete({{ $row->id }})" wire:confirm="{{ __('app.common.confirm_delete') }}" class="text-error" spinner />
    @endif
</x-dropdown>
