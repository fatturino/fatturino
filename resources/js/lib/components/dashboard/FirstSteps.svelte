<script>
    let { hasSdi, hasContacts } = $props();
</script>

<div class="mb-8 bg-brand-deep rounded-2xl p-6 sm:p-8 text-white">
    <h2 class="text-xl font-bold mb-1">Benvenuto in Fatturino!</h2>
    <p class="text-white/60 text-sm mb-6">Completa questi passaggi per iniziare a fatturare.</p>

    <div class="space-y-2">
        <div class="flex items-center gap-3 text-sm bg-white/10 rounded-lg px-4 py-3">
            <span class="text-green-400 text-lg">&#10003;</span>
            <span class="text-white/80">Account creato</span>
        </div>

        {#if hasSdi}
            <div class="flex items-center gap-3 text-sm bg-white/10 rounded-lg px-4 py-3">
                <span class="text-green-400 text-lg">&#10003;</span>
                <span class="text-white/80">Fatturazione elettronica configurata</span>
            </div>
        {:else}
            <a href="/electronic-invoice-settings" class="flex items-center gap-3 text-sm bg-white/15 hover:bg-white/20 rounded-lg px-4 py-3 transition-colors">
                <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-white/20 text-xs font-bold shrink-0">2</span>
                <span class="flex-1">Configura fatturazione elettronica</span>
                <span class="text-white/50">&rarr;</span>
            </a>
        {/if}

        {#if hasContacts}
            <div class="flex items-center gap-3 text-sm bg-white/10 rounded-lg px-4 py-3">
                <span class="text-green-400 text-lg">&#10003;</span>
                <span class="text-white/80">Anagrafica clienti creata</span>
            </div>
        {:else}
            <a href="/contacts/create" class="flex items-center gap-3 text-sm bg-white/15 hover:bg-white/20 rounded-lg px-4 py-3 transition-colors">
                <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-white/20 text-xs font-bold shrink-0">3</span>
                <span class="flex-1">Crea il tuo primo contatto</span>
                <span class="text-white/50">&rarr;</span>
            </a>
        {/if}

        <a href="/sell-invoices/create" class="flex items-center gap-3 text-sm bg-brand-accent/20 hover:bg-brand-accent/30 rounded-lg px-4 py-3 transition-colors border border-amber-400/30">
            <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-brand-accent text-brand-deep text-xs font-bold shrink-0">4</span>
            <span class="flex-1 text-amber-200 font-medium">Crea la tua prima fattura</span>
            <span class="text-amber-300">&rarr;</span>
        </a>
    </div>
</div>
