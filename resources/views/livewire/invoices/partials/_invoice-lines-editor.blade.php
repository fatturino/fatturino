{{--
    Invoice lines editor: add/remove, per-line quantity/price/discount/VAT.

    Required vars:
      $lines        : array
      $vatRates     : array of VAT options
    Optional vars:
      $isReadOnly   : bool (default false)
      $showDiscount : bool (default true) — hide the discount column for doc types that don't use it
--}}
@php
    $isReadOnly   = $isReadOnly   ?? false;
    $showDiscount = $showDiscount ?? true;
@endphp

<div>
    <div class="flex items-center justify-between mb-3">
        <h3 class="font-semibold text-base">{{ __('app.invoices.lines_section') }}</h3>
        @unless($isReadOnly)
            <x-button :label="__('app.invoices.add_line')" icon="o-plus" wire:click="addLine" variant="ghost" size="sm" />
        @endunless
    </div>

    {{-- Lines --}}
    <div class="space-y-3">
        @foreach($lines as $index => $line)
            <div class="flex gap-2" wire:key="line-{{ $index }}">
                {{-- Line fields --}}
                <div @class(['flex-1 bg-base-100 rounded-lg border border-base-200 p-3 space-y-2', 'pointer-events-none' => $isReadOnly])>
                    <x-input :label="__('app.invoices.line_description')" wire:model.blur="lines.{{ $index }}.description" />
                    <div @class(['grid grid-cols-2 gap-3', 'lg:grid-cols-5' => $showDiscount, 'lg:grid-cols-4' => !$showDiscount])>
                        <x-input :label="__('app.invoices.line_quantity')" wire:model.blur="lines.{{ $index }}.quantity" type="number" step="0.01" />
                        <x-input :label="__('app.invoices.line_unit_of_measure')" wire:model.blur="lines.{{ $index }}.unit_of_measure" list="uom-options" placeholder="pz" />
                        <x-input :label="__('app.invoices.line_price')" wire:model.blur="lines.{{ $index }}.unit_price" type="number" step="0.01" prefix="€" />
                        @if($showDiscount)
                            <x-input :label="__('app.invoices.line_discount')" wire:model.blur="lines.{{ $index }}.discount_percent" type="number" step="0.01" suffix="%" />
                        @endif
                        <x-select :label="__('app.invoices.line_vat')" :options="$vatRates" option-label="name" wire:model.live="lines.{{ $index }}.vat_rate" />
                    </div>
                </div>
                {{-- Delete button centered vertically --}}
                @unless($isReadOnly)
                    <div class="flex items-center">
                        <x-button icon="o-trash" wire:click="removeLine({{ $index }})" variant="ghost" size="sm" class="!text-error" />
                    </div>
                @endunless
            </div>
        @endforeach
    </div>

    @if(empty($lines))
        <div class="text-center py-8 text-base-content/40">
            <x-icon name="o-document-plus" class="w-8 h-8 mx-auto mb-2" />
            <p class="text-sm">{{ __('app.invoices.no_lines') }}</p>
        </div>
    @endif

    @include('livewire.invoices.partials._uom-datalist')
</div>
