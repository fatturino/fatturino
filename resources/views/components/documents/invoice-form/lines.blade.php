@props(['title', 'readOnly' => false, 'variant' => 'default'])

@if($variant === 'sales-editor')
    <section class="border-t border-border pt-6">
        <div class="mb-4 flex items-center justify-between gap-4">
            <h2 class="text-base font-semibold text-content">{{ $title }}</h2>
        </div>
        <div class="hidden 2xl:grid 2xl:grid-cols-[minmax(18rem,1fr)_4.5rem_7.5rem_6.5rem_7.5rem_4.5rem] 2xl:gap-3 border-b border-border px-3 py-2 text-xs font-medium text-content-muted">
            <span>Descrizione</span><span class="text-right">Quantità</span><span class="text-right">Prezzo</span><span>IVA</span><span class="text-right">Totale</span><span class="sr-only">Azioni</span>
        </div>
        <div class="divide-y divide-border">{{ $slot }}</div>
        @unless($readOnly)
            <button type="button" wire:click="addLine" class="mt-4 inline-flex h-10 items-center rounded-lg px-2 text-sm font-medium text-primary transition hover:bg-primary-subtle focus:outline-none focus:ring-2 focus:ring-primary/20">+ Aggiungi riga</button>
        @endunless
    </section>
@elseif($variant === 'editor')
    <section class="border-t border-border pt-6">
        <div class="mb-4 flex items-center justify-between gap-4">
            <h2 class="text-base font-semibold text-content">{{ $title }}</h2>
            @unless($readOnly)<button type="button" wire:click="addLine" class="inline-flex h-9 items-center rounded-lg px-2 text-sm font-medium text-primary transition hover:bg-primary-subtle focus:outline-none focus:ring-2 focus:ring-primary/20">+ Aggiungi riga</button>@endunless
        </div>
        <div class="hidden grid-cols-[minmax(10rem,1fr)_4.5rem_5.5rem_6rem_6rem_4rem] gap-3 border-b border-border px-3 py-2 text-xs font-medium text-content-muted xl:grid">
            <span>Descrizione</span><span>Quantità</span><span>Prezzo</span><span>IVA</span><span class="text-right">Totale</span><span class="sr-only">Azioni</span>
        </div>
        <div class="divide-y divide-border">{{ $slot }}</div>
    </section>
@else
    <article class="rounded-xl border border-border-light bg-white p-5 shadow-[var(--shadow-card)]">
        <div class="mb-4 flex items-center justify-between">
            <h2 class="font-bold">{{ $title }}</h2>
            @unless($readOnly)<button type="button" wire:click="addLine" class="text-sm font-semibold text-primary">Aggiungi riga</button>@endunless
        </div>
        <div class="space-y-3">{{ $slot }}</div>
    </article>
@endif
