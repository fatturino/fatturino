<script>
    import { formatCurrency, shortDate } from './formatters.js'

    let {
        paymentSummary = {},
        upcomingDueDates = [],
    } = $props()

    function paymentBucket(status) {
        return paymentSummary?.[status] ?? { count: 0, total: 0 }
    }
</script>

<div class="card-brand p-5">
    <h3 class="text-base font-semibold text-brand-deep mb-3">Stato pagamenti</h3>
    <div class="space-y-2 text-sm">
        <div class="flex items-center justify-between">
            <span class="text-brand-secondary">Da incassare</span>
            <span class="font-semibold text-brand-deep">{formatCurrency(paymentBucket('unpaid').total + paymentBucket('overdue').total)} ({paymentBucket('unpaid').count + paymentBucket('overdue').count})</span>
        </div>
        <div class="flex items-center justify-between">
            <span class="text-brand-secondary">Parziali</span>
            <span class="font-semibold text-brand-deep">{formatCurrency(paymentBucket('partial').total)} ({paymentBucket('partial').count})</span>
        </div>
        <div class="flex items-center justify-between">
            <span class="text-brand-secondary">Pagate</span>
            <span class="font-semibold text-brand-deep">{formatCurrency(paymentBucket('paid').total)} ({paymentBucket('paid').count})</span>
        </div>
    </div>
    {#if upcomingDueDates?.length > 0}
        <div class="mt-4 pt-3 border-t border-border-light space-y-1.5">
            {#each upcomingDueDates.slice(0, 4) as invoice}
                <a href="/sell-invoices/{invoice.id}/edit" class="flex items-center justify-between text-xs text-brand-secondary hover:underline">
                    <span class="truncate pr-2">{invoice.contact?.name ?? '—'}</span>
                    <span class="shrink-0">{formatCurrency(invoice.total_gross ?? 0)} · {shortDate(invoice.due_date)}</span>
                </a>
            {/each}
        </div>
    {/if}
</div>
