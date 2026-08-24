@props(['cancelRoute', 'submitLabel', 'readOnly' => false, 'variant' => 'default'])

@unless($readOnly)
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
@endunless
