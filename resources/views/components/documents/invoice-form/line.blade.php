@props([
    'line',
    'index',
    'linesCount',
    'readOnly' => false,
    'lineTotal' => 0,
    'hasDiscount' => false,
    'vatDisabled' => false,
    'variant' => 'default',
])

@if($variant === 'sales-editor')
    @php($detailsId = 'sales-line-'.$line['key'].'-details')
    <div wire:key="line-{{ $line['key'] }}" class="py-4 2xl:grid 2xl:grid-cols-[minmax(18rem,1fr)_4.5rem_7.5rem_6.5rem_7.5rem_4.5rem] 2xl:items-start 2xl:gap-3">
        <div>
            <label class="text-xs font-medium text-content-muted 2xl:sr-only" for="sales-line-{{ $line['key'] }}-description">Descrizione</label>
            <textarea id="sales-line-{{ $line['key'] }}-description" wire:model.blur="lines.{{ $index }}.description" @disabled($readOnly) rows="1" placeholder="Descrizione" class="mt-1 min-h-10 w-full resize-y rounded-lg border border-border-strong bg-white px-3 py-2 text-sm leading-5 text-content placeholder:text-text-muted focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20 2xl:mt-0"></textarea>
            @error("lines.$index.description")<span class="mt-1 block text-xs text-danger">{{ $message }}</span>@enderror
        </div>

        <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-[4.5rem_7.5rem_minmax(0,1fr)_7.5rem_auto] 2xl:col-span-5 2xl:mt-0 2xl:contents">
            <label class="block text-xs font-medium text-content-muted 2xl:text-[0]">Quantità
                <input wire:model.live.debounce.250ms="lines.{{ $index }}.quantity" @disabled($readOnly) type="number" min="0.01" step="0.01" class="mt-1 h-10 w-full rounded-lg border border-border-strong bg-white px-3 text-right text-sm tabular-nums text-content focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20 2xl:mt-0">
                @error("lines.$index.quantity")<span class="mt-1 block text-xs text-danger 2xl:text-xs">{{ $message }}</span>@enderror
            </label>

            <label class="block text-xs font-medium text-content-muted 2xl:text-[0]">Prezzo
                <input wire:model.live.debounce.250ms="lines.{{ $index }}.unit_price" @disabled($readOnly) type="number" min="0" step="0.01" class="mt-1 h-10 w-full rounded-lg border border-border-strong bg-white px-3 text-right text-sm tabular-nums text-content focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20 2xl:mt-0">
                @error("lines.$index.unit_price")<span class="mt-1 block text-xs text-danger 2xl:text-xs">{{ $message }}</span>@enderror
            </label>

            <div>
                <span class="text-xs font-medium text-content-muted 2xl:sr-only">IVA</span>
                <x-select wire:model.change="lines.{{ $index }}.vat_rate" :disabled="$readOnly || $vatDisabled" :options="\App\Enums\VatRate::options()" />
                @error("lines.$index.vat_rate")<span class="mt-1 block text-xs text-danger">{{ $message }}</span>@enderror
            </div>

            <div>
                <span class="text-xs font-medium text-content-muted 2xl:sr-only">Totale</span>
                <div class="mt-1 flex h-10 items-center justify-end rounded-lg bg-surface-muted px-3 text-sm font-semibold tabular-nums text-content 2xl:mt-0">€ {{ number_format($lineTotal, 2, ',', '.') }}</div>
            </div>

            @unless($readOnly)
                <div class="col-span-2 flex items-end justify-end gap-1 sm:col-auto 2xl:items-center">
                    <div class="tooltip tooltip-left" data-tip="{{ ($line['details_enabled'] ?? false) ? 'Nascondi dettagli riga' : 'Mostra unità di misura e sconto' }}">
                        <button type="button" wire:click="toggleLineDetails({{ $index }})" class="inline-flex size-10 items-center justify-center rounded-lg text-content-muted transition hover:bg-primary-subtle hover:text-primary focus:outline-none focus:ring-2 focus:ring-primary/20" aria-controls="{{ $detailsId }}" aria-expanded="{{ ($line['details_enabled'] ?? false) ? 'true' : 'false' }}" aria-label="{{ ($line['details_enabled'] ?? false) ? 'Nascondi dettagli riga' : 'Mostra unità di misura e sconto' }}"><x-icon name="o-adjustments-horizontal" class="size-4" /></button>
                    </div>
                    <div class="tooltip tooltip-left" data-tip="Rimuovi riga">
                        <button type="button" wire:click="removeLine({{ $index }})" @disabled($linesCount === 1) class="inline-flex size-10 items-center justify-center rounded-lg text-danger transition hover:bg-danger/5 focus:outline-none focus:ring-2 focus:ring-danger/20 disabled:cursor-not-allowed disabled:opacity-30" aria-label="Rimuovi riga"><x-icon name="o-trash" class="size-4" /></button>
                    </div>
                </div>
            @endunless
        </div>

        @if($line['details_enabled'] ?? false)
            <div id="{{ $detailsId }}" class="col-span-full mt-4 grid gap-3 border-t border-border pt-4 sm:grid-cols-2">
                <label class="text-xs font-medium text-content-muted">Unità di misura
                    <input wire:model.blur="lines.{{ $index }}.unit_of_measure" @disabled($readOnly) placeholder="es. ore" class="mt-1 h-10 w-full rounded-lg border border-border-strong bg-white px-3 text-sm text-content focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                </label>
                @if($hasDiscount)
                    <label class="text-xs font-medium text-content-muted">Sconto %
                        <span class="float-right"><input wire:model.change="lines.{{ $index }}.discount_enabled" @disabled($readOnly) type="checkbox" class="peer sr-only"><span class="inline-block h-5 w-8 rounded-full bg-surface-muted align-middle transition-colors peer-checked:bg-primary before:inline-block before:size-3 before:translate-x-1 before:rounded-full before:bg-white before:transition-transform before:content-[''] peer-checked:before:translate-x-4"></span></span>
                        <input wire:model.live.debounce.250ms="lines.{{ $index }}.discount_percent" @disabled($readOnly || !($line['discount_enabled'] ?? false)) type="number" min="0" max="100" step="0.01" class="mt-1 h-10 w-full rounded-lg border border-border-strong bg-white px-3 text-right text-sm tabular-nums text-content focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20 disabled:bg-surface-muted">
                    </label>
                @endif
            </div>
        @endif
    </div>
@elseif($variant === 'editor')
    <div wire:key="line-{{ $line['key'] }}" class="py-4 xl:grid xl:grid-cols-[minmax(10rem,1fr)_4.5rem_5.5rem_6rem_6rem_4rem] xl:gap-3">
        <label class="block text-xs font-medium text-content-muted xl:text-[0]">Descrizione<input wire:model.blur="lines.{{ $index }}.description" @disabled($readOnly) placeholder="Descrizione" class="mt-1 h-10 w-full rounded-lg border border-border-strong bg-white px-3 text-sm text-content placeholder:text-text-muted focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20 xl:mt-0">@error("lines.$index.description")<span class="mt-1 block text-xs text-danger xl:text-xs">{{ $message }}</span>@enderror</label>
        <label class="mt-3 block text-xs font-medium text-content-muted xl:mt-0 xl:text-[0]">Quantità<input wire:model.live.debounce.250ms="lines.{{ $index }}.quantity" @disabled($readOnly) type="number" min="0.01" step="0.01" class="mt-1 h-10 w-full rounded-lg border border-border-strong bg-white px-3 text-sm tabular-nums text-content focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20 xl:mt-0"></label>
        <label class="mt-3 block text-xs font-medium text-content-muted xl:mt-0 xl:text-[0]">Prezzo<input wire:model.live.debounce.250ms="lines.{{ $index }}.unit_price" @disabled($readOnly) type="number" min="0" step="0.01" class="mt-1 h-10 w-full rounded-lg border border-border-strong bg-white px-3 text-sm tabular-nums text-content focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20 xl:mt-0"></label>
        <div class="mt-3 xl:mt-0"><span class="text-xs font-medium text-content-muted xl:sr-only">IVA</span><x-select wire:model.change="lines.{{ $index }}.vat_rate" :disabled="$readOnly || $vatDisabled" :options="\App\Enums\VatRate::options()" /></div>
        <div class="mt-3 xl:mt-0"><span class="text-xs font-medium text-content-muted xl:sr-only">Totale</span><div class="mt-1 flex h-10 items-center justify-end rounded-lg bg-surface-muted px-3 text-sm font-medium tabular-nums text-content xl:mt-0">€ {{ number_format($lineTotal, 2, ',', '.') }}</div></div>
        @unless($readOnly)<div class="mt-3 flex items-center justify-end gap-1 xl:mt-0"><button type="button" wire:click="toggleLineDetails({{ $index }})" class="inline-flex h-9 items-center rounded-lg px-2 text-sm font-medium text-primary transition hover:bg-primary-subtle focus:outline-none focus:ring-2 focus:ring-primary/20" aria-expanded="{{ ($line['details_enabled'] ?? false) ? 'true' : 'false' }}">{{ ($line['details_enabled'] ?? false) ? 'Meno' : 'Dettagli' }}</button><button type="button" wire:click="removeLine({{ $index }})" @disabled($linesCount === 1) class="inline-flex size-9 items-center justify-center rounded-lg text-danger transition hover:bg-danger/5 focus:outline-none focus:ring-2 focus:ring-danger/20 disabled:cursor-not-allowed disabled:opacity-30" aria-label="Rimuovi riga"><x-icon name="o-trash" class="size-4" /></button></div>@endunless
        @if($line['details_enabled'] ?? false)
            <div class="col-span-full mt-3 grid gap-3 border-t border-border pt-3 sm:grid-cols-3">
                <label class="text-xs font-medium text-content-muted">Unità di misura<input wire:model.blur="lines.{{ $index }}.unit_of_measure" @disabled($readOnly) placeholder="UM" class="mt-1 h-10 w-full rounded-lg border border-border-strong bg-white px-3 text-sm text-content focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"></label>
                @if($hasDiscount)<label class="text-xs font-medium text-content-muted">Sconto %<span class="float-right"><input wire:model.change="lines.{{ $index }}.discount_enabled" @disabled($readOnly) type="checkbox" class="peer sr-only"><span class="inline-block h-5 w-8 rounded-full bg-surface-muted align-middle transition-colors peer-checked:bg-primary before:inline-block before:size-3 before:translate-x-1 before:rounded-full before:bg-white before:transition-transform before:content-[''] peer-checked:before:translate-x-4"></span></span><input wire:model.live.debounce.250ms="lines.{{ $index }}.discount_percent" @disabled($readOnly || !($line['discount_enabled'] ?? false)) type="number" min="0" max="100" step="0.01" class="mt-1 h-10 w-full rounded-lg border border-border-strong bg-white px-3 text-sm disabled:bg-surface-muted"></label>@endif
            </div>
        @endif
    </div>
@else
    <div wire:key="line-{{ $line['key'] }}" class="space-y-2 rounded-md border border-border-light bg-surface-muted p-3">
        <div class="grid gap-2 sm:grid-cols-[minmax(0,1fr)_10rem]"><label class="text-xs text-content-muted">Descrizione<input wire:model.blur="lines.{{ $index }}.description" @disabled($readOnly) placeholder="Descrizione" class="mt-1 h-10 w-full rounded-md border border-border bg-white px-2 text-sm">@error("lines.$index.description")<span class="text-xs text-danger">{{ $message }}</span>@enderror</label><div><span class="text-xs text-content-muted">Totale riga</span><div class="mt-1 h-10 rounded-md border border-border-light bg-white px-2 py-2 text-right text-sm font-semibold tabular-nums">€ {{ number_format($lineTotal, 2, ',', '.') }}</div></div></div>
        <div class="grid gap-2 sm:grid-cols-[10rem_minmax(0,1fr)_auto]"><label class="text-xs text-content-muted">Importo<input wire:model.live.debounce.250ms="lines.{{ $index }}.unit_price" @disabled($readOnly) type="number" min="0" step="0.01" class="mt-1 h-10 w-full rounded-md border border-border bg-white px-2 text-sm"></label><label class="text-xs text-content-muted">IVA<x-select wire:model.change="lines.{{ $index }}.vat_rate" :disabled="$readOnly || $vatDisabled" :options="\App\Enums\VatRate::options()" /></label>@unless($readOnly)<div class="flex items-end gap-3"><button type="button" wire:click="toggleLineDetails({{ $index }})" class="h-10 whitespace-nowrap text-sm font-semibold text-primary">{{ ($line['details_enabled'] ?? false) ? 'Nascondi dettagli' : 'Dettagli' }}</button><button type="button" wire:click="removeLine({{ $index }})" @disabled($linesCount === 1) class="h-10 whitespace-nowrap text-sm text-danger disabled:opacity-30">Rimuovi</button></div>@endunless</div>
        @if($line['details_enabled'] ?? false)
            <div @class(['grid grid-cols-2 gap-3 rounded-md border border-border-light bg-white p-3', 'sm:grid-cols-4' => $hasDiscount])><label class="text-xs text-content-muted">Quantità<input wire:model.live.debounce.250ms="lines.{{ $index }}.quantity" @disabled($readOnly) type="number" min="0.01" step="0.01" class="mt-1 h-10 w-full rounded-md border border-border bg-white px-2 text-sm"></label><label class="text-xs text-content-muted">UM<input wire:model.blur="lines.{{ $index }}.unit_of_measure" @disabled($readOnly) placeholder="UM" class="mt-1 h-10 w-full rounded-md border border-border bg-white px-2 text-sm"></label>@if($hasDiscount)<label class="text-xs text-content-muted">Sconto %<span class="float-right"><input wire:model.change="lines.{{ $index }}.discount_enabled" @disabled($readOnly) type="checkbox" class="peer sr-only"><span class="inline-block h-5 w-8 rounded-full bg-zinc-300 align-middle transition-colors peer-checked:bg-primary before:inline-block before:size-3 before:translate-x-1 before:rounded-full before:bg-white before:transition-transform before:content-[''] peer-checked:before:translate-x-4"></span></span><input wire:model.live.debounce.250ms="lines.{{ $index }}.discount_percent" @disabled($readOnly || !($line['discount_enabled'] ?? false)) type="number" min="0" max="100" step="0.01" class="mt-1 h-10 w-full rounded-md border border-border bg-white px-2 text-sm disabled:bg-surface-muted"></label>@endif</div>
        @endif
    </div>
@endif