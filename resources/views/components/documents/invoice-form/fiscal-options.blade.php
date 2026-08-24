@props([
    'readOnly' => false,
    'isRf19' => false,
    'withholding' => false,
    'fund' => false,
    'stampDuty' => false,
    'splitPayment' => false,
    'stampDutyChargedToCustomer' => false,
    'withholdingEnabled' => false,
    'fundEnabled' => false,
    'stampDutyApplied' => false,
    'splitPaymentEnabled' => false,
    'variant' => 'default',
    'showFundDetails' => false,
])

@php
    $editorVariant = in_array($variant, ['editor', 'sales-editor'], true);
    $toggleBackground = $editorVariant ? 'bg-surface-muted' : 'bg-zinc-300';
    $numericInputClasses = $editorVariant
        ? 'mt-1 h-10 w-full rounded-lg border border-border-strong px-3 text-sm'
        : 'mt-1 h-10 w-full rounded-md border border-border px-2 text-sm';
@endphp

@if($editorVariant)
    <details @if($variant === 'editor' && ($withholdingEnabled || $fundEnabled || $stampDutyApplied || $splitPaymentEnabled)) open @endif class="rounded-xl border border-border bg-white">
        <summary class="flex cursor-pointer items-center justify-between gap-3 px-5 py-4 text-sm font-semibold text-content marker:hidden"><span>Opzioni fiscali</span><span class="ml-auto text-xs font-medium text-content-muted">{{ collect([$withholdingEnabled, $fundEnabled, $stampDutyApplied, $splitPaymentEnabled])->filter()->count() ?: 'Nessuna' }}</span><x-icon name="o-chevron-down" class="size-4 text-content-muted" /></summary>
        <div class="border-t border-border px-5 pb-5">
@else
    <article class="rounded-xl border border-border-light bg-white p-5 shadow-[var(--shadow-card)]">
        <h2 class="font-bold">Opzioni fiscali</h2>
@endif
    <div class="mt-4 space-y-4">
        @if($withholding && ! $isRf19)
            <div class="space-y-2"><label class="group flex cursor-pointer items-center justify-between gap-3 {{ $readOnly ? 'cursor-not-allowed opacity-60' : '' }}"><span class="text-sm font-medium text-content">Ritenuta d'acconto</span><span class="relative inline-flex"><input type="checkbox" wire:model.live="withholding_tax_enabled" @disabled($readOnly) class="peer sr-only"><span class="relative h-6 w-10 flex-none rounded-full {{ $toggleBackground }} transition-colors peer-checked:bg-primary before:absolute before:left-1 before:top-1 before:size-4 before:rounded-full before:bg-white before:transition-transform before:content-[''] peer-checked:before:translate-x-full"></span></span></label>@if($withholdingEnabled)<label class="block text-xs font-semibold text-content-muted">Percentuale<input wire:model.live.debounce.250ms="withholding_tax_percent" @disabled($readOnly) type="number" min="0" max="100" step="0.01" class="{{ $numericInputClasses }}"></label>@endif</div>
        @endif
        @if($fund)
            <div @class(['space-y-2', 'border-t border-border pt-4' => $editorVariant])><label class="group flex cursor-pointer items-center justify-between gap-3 {{ $readOnly ? 'cursor-not-allowed opacity-60' : '' }}"><span class="text-sm font-medium text-content">Cassa previdenziale</span><span class="relative inline-flex"><input type="checkbox" wire:model.live="fund_enabled" @disabled($readOnly) class="peer sr-only"><span class="relative h-6 w-10 flex-none rounded-full {{ $toggleBackground }} transition-colors peer-checked:bg-primary before:absolute before:left-1 before:top-1 before:size-4 before:rounded-full before:bg-white before:transition-transform before:content-[''] peer-checked:before:translate-x-full"></span></span></label>@if($fundEnabled)<div @class(['grid gap-3', 'sm:grid-cols-2' => $showFundDetails])>@if($showFundDetails)<label class="text-xs font-semibold text-content-muted">Tipo cassa<x-select wire:model.live="fund_type" :disabled="$readOnly" :options="\App\Enums\FundType::options()" placeholder="Seleziona..." /></label>@endif<label class="block text-xs font-semibold text-content-muted">Percentuale<input wire:model.live.debounce.250ms="fund_percent" @disabled($readOnly) type="number" min="0" max="100" step="0.01" class="{{ $numericInputClasses }}"></label>@if($showFundDetails)<label class="text-xs font-semibold text-content-muted">IVA cassa<x-select wire:model.live="fund_vat_rate" :disabled="$readOnly" :options="\App\Enums\VatRate::options()" placeholder="Seleziona..." /></label><label class="flex items-center gap-2 self-end text-sm text-content"><input wire:model.live="fund_has_deduction" @disabled($readOnly) type="checkbox" class="size-4 rounded border-border text-primary accent-primary focus:ring-primary/20"> Soggetta a ritenuta</label>@endif</div>@endif</div>
        @endif
        @if($stampDuty)
            <div @class(['space-y-2', 'border-t border-border pt-4' => $editorVariant])><label class="group flex cursor-pointer items-center justify-between gap-3 {{ $readOnly || $isRf19 ? 'cursor-not-allowed opacity-60' : '' }}"><span class="text-sm font-medium text-content">Marca da bollo</span><span class="relative inline-flex"><input type="checkbox" wire:model.live="stamp_duty_applied" @disabled($readOnly || $isRf19) class="peer sr-only"><span class="relative h-6 w-10 flex-none rounded-full {{ $toggleBackground }} transition-colors peer-checked:bg-primary before:absolute before:left-1 before:top-1 before:size-4 before:rounded-full before:bg-white before:transition-transform before:content-[''] peer-checked:before:translate-x-full"></span></span></label>@if($stampDutyChargedToCustomer && $stampDutyApplied)<fieldset @class(['space-y-2 bg-surface-muted p-3', 'rounded-lg' => $editorVariant, 'rounded-md' => ! $editorVariant])><legend class="px-1 text-xs font-semibold text-content-muted">Addebito bollo</legend><label class="flex items-center gap-2 text-sm"><input wire:model.live="stamp_duty_charged_to_customer" @disabled($readOnly) type="radio" value="1"> A carico del cliente</label><label class="flex items-center gap-2 text-sm"><input wire:model.live="stamp_duty_charged_to_customer" @disabled($readOnly) type="radio" value="0"> A carico del cedente</label></fieldset>@endif</div>
        @endif
        @if($splitPayment && ! $isRf19)
            <div @class(['space-y-2', 'border-t border-border pt-4' => $editorVariant])><label class="group flex cursor-pointer items-center justify-between gap-3 {{ $readOnly ? 'cursor-not-allowed opacity-60' : '' }}"><span class="text-sm font-medium text-content">Split payment</span><span class="relative inline-flex"><input type="checkbox" wire:model.live="split_payment" @disabled($readOnly) class="peer sr-only"><span class="relative h-6 w-10 flex-none rounded-full {{ $toggleBackground }} transition-colors peer-checked:bg-primary before:absolute before:left-1 before:top-1 before:size-4 before:rounded-full before:bg-white before:transition-transform before:content-[''] peer-checked:before:translate-x-full"></span></span></label><label class="block text-sm font-medium text-content">Esigibilità IVA<x-select wire:model.live="vat_payability" :disabled="$readOnly || $splitPaymentEnabled" :options="\App\Enums\VatPayability::options()" /></label></div>
        @endif
    </div>
@if($editorVariant)
        </div>
    </details>
@else
    </article>
@endif
