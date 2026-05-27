<script>
    let { isCurrentYear = true, selfInvoicesEnabled = true } = $props()
</script>

<div class="card-brand p-5">
    <h3 class="text-base font-semibold text-brand-deep mb-3">Azioni rapide</h3>
    <div class="space-y-2.5">
        {#if isCurrentYear}
            <a href="/sell-invoices/create" class="flex items-center gap-2.5 text-sm text-brand-secondary hover:bg-brand/5 rounded-lg px-3 py-2 transition-colors">
                <span aria-hidden="true" class="inline-flex items-center justify-center w-5 h-5 rounded bg-brand/10 text-[10px] font-semibold text-brand-deep">FV</span>
                <span>Nuova Fattura</span>
            </a>
            {#if selfInvoicesEnabled}
                <a href="/self-invoices/create" class="flex items-center gap-2.5 text-sm text-brand-secondary hover:bg-brand/5 rounded-lg px-3 py-2 transition-colors">
                    <span aria-hidden="true" class="inline-flex items-center justify-center w-5 h-5 rounded bg-brand/10 text-[10px] font-semibold text-brand-deep">AF</span>
                    <span>Nuova Autofattura</span>
                </a>
            {/if}
            <a href="/contacts/create" class="flex items-center gap-2.5 text-sm text-brand-secondary hover:bg-brand/5 rounded-lg px-3 py-2 transition-colors">
                <span aria-hidden="true" class="inline-flex items-center justify-center w-5 h-5 rounded bg-brand/10 text-[10px] font-semibold text-brand-deep">CL</span>
                <span>Nuovo Cliente</span>
            </a>
        {/if}
        <a href="/sell-invoices" class="block text-sm text-brand-secondary hover:bg-brand/5 rounded-lg px-3 py-2 transition-colors">
            Tutte le fatture &rarr;
        </a>
        <a href="/contacts" class="block text-sm text-brand-secondary hover:bg-brand/5 rounded-lg px-3 py-2 transition-colors">
            Tutti i contatti &rarr;
        </a>
    </div>
</div>
