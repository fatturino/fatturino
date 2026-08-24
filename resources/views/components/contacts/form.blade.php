@props(['submitLabel'])

<section class="mx-auto max-w-4xl pb-24">
    <form wire:submit="save" class="space-y-6">
        <article class="rounded-xl border border-border bg-white p-5 sm:p-6">
            <div>
                <h2 class="text-base font-semibold text-content">Dati identificativi</h2>
                <p class="mt-1 text-sm text-content-muted">Inserisci la ragione sociale e gli identificativi fiscali del contatto.</p>
            </div>

            <div class="mt-5 grid gap-4 sm:grid-cols-2">
                <x-settings.input wire:model="name" label="Nome o ragione sociale *" />
                <x-select label="Nazione *" wire:model.live="country" :options="$this->countries()" />
                <x-settings.input wire:model="vat_number" label="Partita IVA" />
                <x-settings.input wire:model="tax_code" label="Codice fiscale" />
            </div>
        </article>

        <article class="rounded-xl border border-border bg-white p-5 sm:p-6">
            <div>
                <h2 class="text-base font-semibold text-content">Fatturazione elettronica</h2>
                <p class="mt-1 text-sm text-content-muted">Indica il canale a cui inviare le fatture elettroniche e i recapiti ordinari.</p>
            </div>

            <div class="mt-5 grid gap-4 sm:grid-cols-2">
                <x-settings.input wire:model="email" type="email" label="Email" />
                <x-settings.input wire:model="pec" type="email" label="PEC" />
                <div class="sm:col-span-2">
                    <x-settings.input wire:model="sdi_code" label="Codice SDI" maxlength="7" />
                    <p class="mt-1.5 text-xs text-content-muted">Per i contatti italiani, inserisci il codice destinatario di 7 caratteri oppure usa la PEC.</p>
                </div>
            </div>
        </article>

        <article class="rounded-xl border border-border bg-white p-5 sm:p-6">
            <div>
                <h2 class="text-base font-semibold text-content">Indirizzo</h2>
                <p class="mt-1 text-sm text-content-muted">Completa l’indirizzo usato nei documenti fiscali.</p>
            </div>

            <div class="mt-5 grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2"><x-settings.input wire:model="address" label="Indirizzo" /></div>
                <x-settings.input wire:model="postal_code" label="CAP" />
                <x-settings.input wire:model="city" label="Città" />
                <x-settings.input wire:model="province" label="Provincia" maxlength="2" />
            </div>
        </article>

        <div class="hidden items-center justify-end gap-3 sm:flex">
            <x-app-link :href="route('contacts.index')" class="inline-flex h-11 items-center justify-center rounded-lg border border-border bg-white px-4 text-sm font-medium text-content transition hover:border-border-strong hover:bg-surface-muted focus:outline-none focus:ring-2 focus:ring-primary/20">Annulla</x-app-link>
            <button type="submit" wire:loading.attr="disabled" wire:target="save" class="inline-flex h-11 items-center justify-center gap-2 rounded-lg bg-primary px-4 text-sm font-medium text-white transition hover:bg-primary-hover focus:outline-none focus:ring-2 focus:ring-primary/20 disabled:cursor-not-allowed disabled:opacity-50"><span wire:loading.remove wire:target="save">{{ $submitLabel }}</span><span wire:loading wire:target="save" class="inline-flex items-center gap-2"><x-icon name="o-arrow-path" class="size-4 animate-spin" />Salvataggio...</span></button>
        </div>

        <div class="fixed inset-x-0 bottom-0 z-20 border-t border-border bg-white/95 p-3 backdrop-blur sm:hidden">
            <div class="mx-auto flex max-w-4xl items-center justify-end gap-3">
                <x-app-link :href="route('contacts.index')" class="inline-flex h-11 items-center justify-center rounded-lg border border-border bg-white px-4 text-sm font-medium text-content transition hover:border-border-strong hover:bg-surface-muted focus:outline-none focus:ring-2 focus:ring-primary/20">Annulla</x-app-link>
                <button type="submit" wire:loading.attr="disabled" wire:target="save" class="inline-flex h-11 items-center justify-center gap-2 rounded-lg bg-primary px-4 text-sm font-medium text-white transition hover:bg-primary-hover focus:outline-none focus:ring-2 focus:ring-primary/20 disabled:cursor-not-allowed disabled:opacity-50"><span wire:loading.remove wire:target="save">{{ $submitLabel }}</span><span wire:loading wire:target="save" class="inline-flex items-center gap-2"><x-icon name="o-arrow-path" class="size-4 animate-spin" />Salvataggio...</span></button>
            </div>
        </div>
    </form>
</section>