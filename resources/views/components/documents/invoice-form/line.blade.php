@props([
    'line',
    'index',
    'linesCount',
    'readOnly' => false,
    'lineTotal' => 0,
    'hasDiscount' => false,
    'vatDisabled' => false,
])

<div wire:key="line-{{ $line['key'] }}" class="space-y-2 rounded-md border border-border-light bg-surface-muted p-3">
    <div class="grid gap-2 sm:grid-cols-[minmax(0,1fr)_10rem]">
        <div>
            <label class="text-xs text-content-muted">Descrizione</label>
            <input wire:model.blur="lines.{{ $index }}.description" @disabled($readOnly) placeholder="Descrizione" class="mt-1 h-10 w-full rounded-md border border-border bg-white px-2 text-sm">
            @error("lines.$index.description")<span class="text-xs text-danger">{{ $message }}</span>@enderror
        </div>
        <div>
            <span class="text-xs text-content-muted">Totale riga</span>
            <div class="mt-1 h-10 rounded-md border border-border-light bg-white px-2 py-2 text-right text-sm font-semibold tabular-nums">€ {{ number_format($lineTotal, 2, ',', '.') }}</div>
        </div>
    </div>

    <div class="grid gap-2 sm:grid-cols-[10rem_minmax(0,1fr)_auto]">
        <label class="text-xs text-content-muted">Importo<input wire:model.live.debounce.250ms="lines.{{ $index }}.unit_price" @disabled($readOnly) type="number" min="0" step="0.01" class="mt-1 h-10 w-full rounded-md border border-border bg-white px-2 text-sm"></label>
        <label class="text-xs text-content-muted">IVA<select wire:model.change="lines.{{ $index }}.vat_rate" @disabled($readOnly || $vatDisabled) class="mt-1 h-10 w-full rounded-md border border-border bg-white px-2 text-sm">@foreach(\App\Enums\VatRate::options() as $rate)<option value="{{ $rate['id'] }}">{{ $rate['name'] }}</option>@endforeach</select></label>
        @unless($readOnly)
            <div class="flex items-end gap-3">
                <button type="button" wire:click="toggleLineDetails({{ $index }})" class="h-10 whitespace-nowrap text-sm font-semibold text-primary">{{ ($line['details_enabled'] ?? false) ? 'Nascondi dettagli' : 'Dettagli' }}</button>
                <button type="button" wire:click="removeLine({{ $index }})" @disabled($linesCount === 1) class="h-10 whitespace-nowrap text-sm text-danger disabled:opacity-30">Rimuovi</button>
            </div>
        @endunless
    </div>

    @if($line['details_enabled'] ?? false)
        <div @class(['grid grid-cols-2 gap-3 rounded-md border border-border-light bg-white p-3', 'sm:grid-cols-4' => $hasDiscount])>
            <label class="text-xs text-content-muted">Quantità<input wire:model.live.debounce.250ms="lines.{{ $index }}.quantity" @disabled($readOnly) type="number" min="0.01" step="0.01" class="mt-1 h-10 w-full rounded-md border border-border bg-white px-2 text-sm"></label>
            <label class="text-xs text-content-muted">UM<input wire:model.blur="lines.{{ $index }}.unit_of_measure" @disabled($readOnly) placeholder="UM" class="mt-1 h-10 w-full rounded-md border border-border bg-white px-2 text-sm"></label>
            @if($hasDiscount)
                <label class="text-xs text-content-muted">Sconto %
                    <span class="float-right"><input wire:model.change="lines.{{ $index }}.discount_enabled" @disabled($readOnly) type="checkbox" class="peer sr-only"><span class="inline-block h-5 w-8 rounded-full bg-zinc-300 align-middle transition-colors peer-checked:bg-primary before:inline-block before:size-3 before:translate-x-1 before:rounded-full before:bg-white before:transition-transform before:content-[''] peer-checked:before:translate-x-4"></span></span>
                    <input wire:model.live.debounce.250ms="lines.{{ $index }}.discount_percent" @disabled($readOnly || !($line['discount_enabled'] ?? false)) type="number" min="0" max="100" step="0.01" class="mt-1 h-10 w-full rounded-md border border-border bg-white px-2 text-sm disabled:bg-surface-muted">
                </label>
            @endif
        </div>
    @endif
</div>
