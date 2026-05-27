<script>
    import { formatCurrency } from './formatters.js'

    let { recentInvoices = [] } = $props()
</script>

<div class="card-brand p-5">
    <h3 class="text-base font-semibold text-brand-deep mb-3">Fatture recenti</h3>
    {#if recentInvoices.length > 0}
        <div class="space-y-2">
            {#each recentInvoices as invoice}
                <div class="flex items-center justify-between text-sm py-1.5 border-b border-border-light last:border-0">
                    <div>
                        <a href="/sell-invoices/{invoice.id}/edit" class="text-brand-deep font-medium hover:underline">
                            {invoice.number ?? '#' + invoice.id}
                        </a>
                        <span class="text-brand-secondary/60 ml-2">{invoice.contact?.name ?? '—'}</span>
                    </div>
                    <span class="text-brand-deep tabular-nums">{formatCurrency(invoice.total_gross ?? 0)}</span>
                </div>
            {/each}
        </div>
    {:else}
        <div class="flex items-center justify-center text-sm text-brand-secondary/40 py-8">
            Nessuna fattura ancora emessa.
        </div>
    {/if}
</div>
