@props(['cancelRoute', 'submitLabel', 'readOnly' => false, 'variant' => 'default', 'netDue' => null])

@unless($readOnly)
    @if($variant === 'sales-editor')
        <div class="hidden border-t border-border pt-4 lg:flex lg:flex-col lg:gap-2">
            <x-app-link :href="route($cancelRoute)" @click="if (dirty && ! window.confirm('Le modifiche non salvate andranno perse. Vuoi annullare?')) $event.preventDefault()" class="inline-flex h-11 items-center justify-center rounded-lg border border-border bg-white px-4 text-sm font-medium text-content transition hover:border-border-strong hover:bg-surface-muted focus:outline-none focus:ring-2 focus:ring-primary/20">Annulla</x-app-link>
            <button type="submit" wire:loading.attr="disabled" wire:target="save" class="inline-flex h-11 items-center justify-center rounded-lg bg-primary px-4 text-sm font-medium text-white transition hover:bg-primary-hover focus:outline-none focus:ring-2 focus:ring-primary/20 disabled:cursor-not-allowed disabled:opacity-60"><span wire:loading.remove wire:target="save">{{ $submitLabel }}</span><span wire:loading wire:target="save" role="status">Salvataggio in corso...</span></button>
        </div>
        <div class="fixed inset-x-0 bottom-0 z-20 border-t border-border bg-white/95 p-3 backdrop-blur lg:hidden">
            <div class="mx-auto flex max-w-[90rem] items-center gap-3"><span class="min-w-0 flex-1 text-xs text-content-muted">Da pagare <span class="ml-1 font-semibold tabular-nums text-content">€ {{ number_format($netDue ?? 0, 2, ',', '.') }}</span></span><x-app-link :href="route($cancelRoute)" @click="if (dirty && ! window.confirm('Le modifiche non salvate andranno perse. Vuoi annullare?')) $event.preventDefault()" class="inline-flex h-11 items-center justify-center rounded-lg border border-border bg-white px-3 text-sm font-medium text-content">Annulla</x-app-link><button type="submit" wire:loading.attr="disabled" wire:target="save" class="inline-flex h-11 items-center justify-center rounded-lg bg-primary px-3 text-sm font-medium text-white disabled:opacity-60"><span wire:loading.remove wire:target="save">{{ $submitLabel }}</span><span wire:loading wire:target="save">Salvo...</span></button></div>
        </div>
    @else
        <div class="fixed inset-x-0 bottom-0 z-20 border-t border-border bg-white/95 p-3 backdrop-blur">
            <div @class(['mx-auto flex justify-end gap-3', 'max-w-[90rem] px-1' => $variant === 'editor', 'max-w-7xl' => $variant !== 'editor'])>
                @if($variant === 'editor')
                <x-app-link :href="route($cancelRoute)" class="inline-flex h-11 items-center justify-center rounded-lg border border-border bg-white px-4 text-sm font-medium text-content transition hover:border-border-strong hover:bg-surface-muted focus:outline-none focus:ring-2 focus:ring-primary/20">Annulla</x-app-link>
                <button type="submit" wire:loading.attr="disabled" class="inline-flex h-11 items-center justify-center rounded-lg bg-primary px-4 text-sm font-medium text-white transition hover:bg-primary-hover focus:outline-none focus:ring-2 focus:ring-primary/20 disabled:cursor-not-allowed disabled:opacity-60">{{ $submitLabel }}</button>
                @else
                <x-app-link :href="route($cancelRoute)" class="rounded-md border border-border px-4 py-2 text-sm font-bold">Annulla</x-app-link>
                <button type="submit" wire:loading.attr="disabled" class="rounded-md bg-primary px-4 py-2 text-sm font-bold text-white">{{ $submitLabel }}</button>
                @endif
            </div>
        </div>
    @endif
@endunless
