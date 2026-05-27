<x-dropdown right>
    <x-slot:trigger>
        <x-button icon="o-ellipsis-vertical" variant="ghost" size="sm" />
    </x-slot:trigger>

    <x-menu-item :title="__('app.invoices.download_pdf')" icon="o-document-text" wire:click="downloadPdf({{ $row->id }})" spinner />
    <x-menu-item :title="__('app.invoices.download_xml')" icon="o-arrow-down-tray" wire:click="downloadXml({{ $row->id }})" spinner />

    @if($row->isConvertible())
        <x-menu-item :title="__('app.proforma.convert_to_invoice')" icon="o-arrow-path" wire:click="convertToInvoice({{ $row->id }})" spinner />
    @endif

    @if($row->contact?->email)
        <x-menu-item :title="__('app.email.send_email')" icon="o-envelope" wire:click="sendEmail({{ $row->id }})" wire:confirm="{{ __('app.email.confirm_send') }}" spinner />
    @endif

    @if(!$this->isReadOnly && $row->status !== \App\Enums\ProformaStatus::Converted)
        <hr class="my-1 border-base-200" />
        <x-menu-item :title="__('app.common.delete')" icon="o-trash" wire:click="delete({{ $row->id }})" wire:confirm="{{ __('app.common.confirm_delete') }}" class="text-error" spinner />
    @endif
</x-dropdown>
