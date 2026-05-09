{{--
    Invoice header fields: number / sequence / date / customer / document type.

    Required vars:
      $mode          : 'create' | 'edit'
      $contacts      : Collection of Contact models
    Optional vars:
      $sequences     : Collection (edit with new invoice)
      $sequenceName  : string (create)
      $invoice       : Invoice model (edit)
      $isReadOnly    : bool (edit)
--}}
@php
    $isReadOnly = $isReadOnly ?? false;
    $mode = $mode ?? 'create';
@endphp

@if($mode === 'create')
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <x-input :label="__('app.invoices.number')" wire:model="number" :hint="$sequenceName ?? null" readonly />
        <x-datetime :label="__('app.invoices.date')" wire:model="date" type="date" />
        <x-select :label="__('app.invoices.customer')" :options="$contacts" wire:model.live="contact_id" search :placeholder="__('app.invoices.select_customer')" placholder-value="null" class="col-span-2" />
        <x-select :label="__('app.invoices.document_type')" :options="\App\Enums\SalesDocumentType::options()" wire:model="document_type" />
    </div>
@else
    <div @class(['grid grid-cols-2 lg:grid-cols-5 gap-4', 'pointer-events-none' => $isReadOnly])>
        @if(isset($invoice) && $invoice->exists)
            <x-input :label="__('app.invoices.number')" wire:model="number" :hint="$invoice->sequence?->name" readonly />
        @else
            <x-select :label="__('app.invoices.sequence')" :options="$sequences ?? []" wire:model.live="sequence_id" />
            <x-input :label="__('app.invoices.number')" wire:model="number" />
        @endif
        <x-datetime :label="__('app.invoices.date')" wire:model="date" type="date" />
        <x-select :label="__('app.invoices.customer')" :options="$contacts" wire:model.live="contact_id" search />
        <x-select :label="__('app.invoices.document_type')" :options="\App\Enums\SalesDocumentType::options()" wire:model="document_type" />
    </div>
@endif
