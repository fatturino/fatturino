<x-dropdown right>
    <x-slot:trigger>
        <x-button icon="o-ellipsis-vertical" variant="ghost" size="sm" />
    </x-slot:trigger>

    <x-menu-item :title="__('app.invoices.download_pdf')" icon="o-document-text" wire:click="downloadPdf({{ $row->id }})" spinner />
    <x-menu-item :title="__('app.invoices.download_xml')" icon="o-arrow-down-tray" wire:click="downloadXml({{ $row->id }})" spinner />

    @if(!$this->isReadOnly && $row->isSdiEditable() && $row->status->canValidateXml())
        <x-menu-item :title="__('app.invoices.validate_xml')" icon="o-shield-check" wire:click="validateXml({{ $row->id }})" wire:confirm="{{ __('app.invoices.confirm_validate_xml') }}" spinner />
    @endif

    @if(!$this->isReadOnly && $row->isSdiEditable() && $row->status->canSendToSdi())
        <x-menu-item :title="__('app.invoices.send_to_sdi')" icon="o-paper-airplane" wire:click="sendToSdi({{ $row->id }})" wire:confirm="{{ __('app.invoices.confirm_send_sdi') }}" spinner />
    @endif

    @if($row->contact?->email)
        <x-menu-item :title="__('app.email.send_email')" icon="o-envelope" wire:click="sendEmail({{ $row->id }})" wire:confirm="{{ __('app.email.confirm_send') }}" spinner />
    @endif

    @if(!$this->isReadOnly && $row->isSdiEditable())
        <hr class="my-1 border-base-200" />
        <x-menu-item :title="__('app.common.delete')" icon="o-trash" wire:click="delete({{ $row->id }})" wire:confirm="{{ __('app.common.confirm_delete') }}" class="text-error" spinner />
    @endif
</x-dropdown>
