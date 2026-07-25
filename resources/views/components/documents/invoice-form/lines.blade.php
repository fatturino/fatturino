@props(['title', 'readOnly' => false])

<article class="rounded-xl border border-border-light bg-white p-5 shadow-[var(--shadow-card)]">
    <div class="mb-4 flex items-center justify-between">
        <h2 class="font-bold">{{ $title }}</h2>
        @unless($readOnly)<button type="button" wire:click="addLine" class="text-sm font-semibold text-primary">Aggiungi riga</button>@endunless
    </div>
    <div class="space-y-3">{{ $slot }}</div>
</article>
