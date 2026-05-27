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
        <p class="text-[11px] text-brand-secondary/70 font-medium uppercase tracking-wide mb-2">Fatturato</p>
        <p class="text-2xl font-semibold text-brand-deep">{formatCurrency(stats.total_gross)}</p>
        <p class="text-xs text-brand-secondary/70 mt-1">{formatNumber(stats.total_count)} fatture</p>
    </div>

    <div class="card-brand p-4 sm:p-5">
        <p class="text-[11px] text-brand-secondary/70 font-medium uppercase tracking-wide mb-2">Da incassare</p>
        <p class="text-2xl font-semibold text-brand-deep">{formatCurrency(stats.unpaid_amount)}</p>
        <p class="text-xs text-brand-secondary/70 mt-1">{stats.unpaid_count} non pagate</p>
        {#if stats.overdue_count > 0}
            <p class="text-xs text-red-700 mt-1">{stats.overdue_count} scadute ({formatCurrency(stats.overdue_amount)})</p>
        {/if}
    </div>

    <div class="card-brand p-4 sm:p-5">
        <p class="text-[11px] text-brand-secondary/70 font-medium uppercase tracking-wide mb-2">Valore medio</p>
        <p class="text-2xl font-semibold text-brand-deep">
            {stats.total_count > 0 ? formatCurrency(stats.total_gross / stats.total_count) : '—'}
        </p>
        <p class="text-xs text-brand-secondary/70 mt-1">per fattura</p>
    </div>

    <div class="card-brand p-4 sm:p-5">
        <p class="text-[11px] text-brand-secondary/70 font-medium uppercase tracking-wide mb-2">Bozze</p>
        <p class="text-2xl font-semibold text-brand-deep">{stats.draft_count ?? 0}</p>
        <p class="text-xs text-brand-secondary/70 mt-1">da completare</p>
    </div>
</div>
