@props(['cancelRoute', 'submitLabel', 'readOnly' => false])

@unless($readOnly)
    <div class="fixed inset-x-0 bottom-0 z-20 border-t border-border bg-white/95 p-3 backdrop-blur">
        <div class="mx-auto flex max-w-7xl justify-end gap-3">
            <x-app-link :href="route($cancelRoute)" class="rounded-md border border-border px-4 py-2 text-sm font-bold">Annulla</x-app-link>
            <button type="submit" wire:loading.attr="disabled" class="rounded-md bg-primary px-4 py-2 text-sm font-bold text-white">{{ $submitLabel }}</button>
        </div>
    </div>
@endunless
