<script>
    import { formatCurrency } from './formatters.js'

    let { topClients = [] } = $props()
</script>

<div class="card-brand p-5">
    <h3 class="text-base font-semibold text-brand-deep mb-3">Top clienti</h3>
    {#if topClients.length > 0}
        <div class="space-y-2">
            {#each topClients as client}
                <div class="flex items-center justify-between text-sm py-1.5 border-b border-border-light last:border-0">
                    <span class="text-brand-deep">{client.contact?.name ?? '—'}</span>
                    <span class="text-brand-deep tabular-nums">{formatCurrency(client.revenue_total ?? 0)}</span>
                </div>
            {/each}
        </div>
    {:else}
        <div class="flex items-center justify-center text-sm text-brand-secondary/40 py-8">
            Nessun cliente con fatturato disponibile.
        </div>
    {/if}
</div>
