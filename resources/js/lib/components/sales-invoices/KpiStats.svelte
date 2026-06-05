<script>
    let { stats = {} } = $props()

    function formatCurrency(value) {
        return new Intl.NumberFormat('it-IT', { style: 'currency', currency: 'EUR', minimumFractionDigits: 0 }).format((value || 0) / 100)
    }

    function formatNumber(value) {
        return new Intl.NumberFormat('it-IT').format(value || 0)
    }
</script>

<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
    <div class="card-brand p-4 sm:p-5">
        <p class="text-[11px] text-brand-secondary/70 font-medium uppercase tracking-wide mb-2">Fatturato netto IVA</p>
        <p class="text-2xl font-semibold text-brand-deep">{formatCurrency(stats.total_revenue_net)}</p>
        <p class="text-xs text-brand-secondary/70 mt-1">{formatNumber(stats.total_count)} fatture</p>
        <p class="text-xs text-brand-secondary/70 mt-1">IVA documenti {formatCurrency(stats.total_document_vat)}</p>
    </div>

    <div class="card-brand p-4 sm:p-5">
        <p class="text-[11px] text-brand-secondary/70 font-medium uppercase tracking-wide mb-2">Da incassare netto</p>
        <p class="text-2xl font-semibold text-brand-deep">{formatCurrency(stats.outstanding_net)}</p>
        <p class="text-xs text-brand-secondary/70 mt-1">{stats.outstanding_count} documenti aperti</p>
        <p class="text-xs text-brand-secondary/70 mt-1">IVA da incassare {formatCurrency(stats.outstanding_vat)}</p>
        {#if stats.overdue_count > 0}
            <p class="text-xs text-red-700 mt-1">{stats.overdue_count} scadute ({formatCurrency(stats.overdue_net)})</p>
        {/if}
    </div>

    <div class="card-brand p-4 sm:p-5">
        <p class="text-[11px] text-brand-secondary/70 font-medium uppercase tracking-wide mb-2">Valore medio netto IVA</p>
        <p class="text-2xl font-semibold text-brand-deep">
            {stats.total_count > 0 ? formatCurrency(stats.average_revenue_net) : '—'}
        </p>
        <p class="text-xs text-brand-secondary/70 mt-1">per fattura</p>
    </div>

    <div class="card-brand p-4 sm:p-5">
        <p class="text-[11px] text-brand-secondary/70 font-medium uppercase tracking-wide mb-2">Bozze</p>
        <p class="text-2xl font-semibold text-brand-deep">{stats.draft_count ?? 0}</p>
        <p class="text-xs text-brand-secondary/70 mt-1">da completare</p>
    </div>
</div>
