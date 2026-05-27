<script>
    import { formatCurrency, formatPercent } from './formatters.js'

    let {
        revenueThisMonth = 0,
        revenueYtd = 0,
        invoicesThisMonth = 0,
        invoicesYtd = 0,
        activeClientsCount = 0,
        totalContactsCount = 0,
        averageInvoiceValue = 0,
        monthChangePercent = 0,
    } = $props()
</script>

<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
    <div class="card-brand p-5">
        <p class="text-xs text-brand-secondary/60 font-medium uppercase tracking-wide mb-2">Ricavi questo mese</p>
        <p class="text-2xl font-bold text-brand-deep">{formatCurrency(revenueThisMonth)}</p>
        {#if monthChangePercent !== 0}
            <p class="text-xs mt-1 {monthChangePercent >= 0 ? 'text-green-600' : 'text-red-600'}">
                {formatPercent(monthChangePercent)} vs mese scorso
            </p>
        {/if}
    </div>

    <div class="card-brand p-5">
        <p class="text-xs text-brand-secondary/60 font-medium uppercase tracking-wide mb-2">Ricavi YTD</p>
        <p class="text-2xl font-bold text-brand-deep">{formatCurrency(revenueYtd)}</p>
        <p class="text-xs text-brand-secondary/60 mt-1">{invoicesYtd} fatture</p>
    </div>

    <div class="card-brand p-5">
        <p class="text-xs text-brand-secondary/60 font-medium uppercase tracking-wide mb-2">Fatture questo mese</p>
        <p class="text-2xl font-bold text-brand-deep">{invoicesThisMonth}</p>
        <p class="text-xs text-brand-secondary/60 mt-1">Valore medio {formatCurrency(averageInvoiceValue)}</p>
    </div>

    <div class="card-brand p-5">
        <p class="text-xs text-brand-secondary/60 font-medium uppercase tracking-wide mb-2">Clienti attivi</p>
        <p class="text-2xl font-bold text-brand-deep">{activeClientsCount}</p>
        <p class="text-xs text-brand-secondary/60 mt-1">su {totalContactsCount} contatti totali</p>
    </div>
</div>
