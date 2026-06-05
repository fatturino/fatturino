<script>
    import Authenticated from '../Layouts/Authenticated.svelte'
    import { page } from '@inertiajs/svelte'
    import { formatCurrency, shortDate } from '$lib/components/dashboard/formatters.js'

    let {
        fiscalYear = new Date().getFullYear(),
        isCurrentYear = true,
        hasInvoices = false,
        hasSdi = false,
        hasContacts = false,
        revenueThisMonth = 0,
        revenueYtd = 0,
        invoicesThisMonth = 0,
        invoicesYtd = 0,
        activeClientsCount = 0,
        totalContactsCount = 0,
        averageInvoiceValue = 0,
        monthChangePercent = 0,
        withholdingTaxYtd = 0,
        vatCollectedYtd = 0,
        vatOnPurchasesYtd = 0,
        vatBalanceYtd = 0,
        collectedNetYtd = 0,
        collectedVatYtd = 0,
        outstandingNetYtd = 0,
        outstandingVatYtd = 0,
        openInvoicesCount = 0,
        vatByQuarter = [],
        topClients = [],
        recentInvoices = [],
        draftCount = 0,
        readyForSdiCount = 0,
        revenueTrend = [],
        paymentSummary = {},
        upcomingDueDates = [],
    } = $props()

    const overdueCount = $derived(paymentSummary?.overdue?.count ?? 0)
    const collectionRate = $derived(revenueYtd > 0 ? Math.min(100, (collectedNetYtd / revenueYtd) * 100) : 0)
    const selfInvoicesEnabled = $derived((page.props.fiscalRegime ?? null) !== 'RF19' || !!page.props.rf19SelfInvoicesEnabled)
</script>

<Authenticated>
    {#snippet headerActions()}
        <a href="/sell-invoices/create" class="btn-brand text-sm">Nuova Fattura</a>
        {#if selfInvoicesEnabled}
            <a href="/self-invoices/create" class="btn-outline text-sm">Nuova Autofattura</a>
        {/if}
        <a href="/contacts/create" class="btn-outline text-sm">Nuovo Contatti</a>
    {/snippet}

    <div class="page-shell w-full">
        <section class="card-brand p-4 sm:p-6 mb-6">
            <div>
                <div>
                    <p class="text-xs uppercase tracking-wide text-brand-secondary/70">Dashboard operativa</p>
                    <h2 class="mt-1 text-2xl font-semibold text-brand-deep">Controllo fatture e incassi</h2>
                    <p class="mt-1 text-sm text-brand-secondary/80">Anno fiscale {fiscalYear}</p>
                </div>
            </div>

            <div class="mt-5 grid grid-cols-2 gap-3 lg:grid-cols-4">
                <article class="rounded-xl border border-border-light bg-surface-muted p-4">
                    <p class="text-xs uppercase tracking-wide text-brand-secondary/70">Fatturato netto IVA mese</p>
                    <p class="mt-2 text-xl font-semibold text-brand-deep">{formatCurrency(revenueThisMonth)}</p>
                    <p class="mt-1 text-xs {monthChangePercent >= 0 ? 'text-emerald-700' : 'text-red-700'}">
                        {monthChangePercent >= 0 ? '+' : ''}{monthChangePercent.toFixed(1)}% vs mese scorso
                    </p>
                </article>
                <article class="rounded-xl border border-border-light bg-surface-muted p-4">
                    <p class="text-xs uppercase tracking-wide text-brand-secondary/70">Da incassare netto</p>
                    <p class="mt-2 text-xl font-semibold text-brand-deep">{formatCurrency(outstandingNetYtd)}</p>
                    <p class="text-xs text-brand-secondary/80">{openInvoicesCount} documenti aperti</p>
                    <p class="mt-1 text-xs text-brand-secondary/80">IVA da incassare {formatCurrency(outstandingVatYtd)}</p>
                </article>
                <article class="rounded-xl border border-border-light bg-surface-muted p-4">
                    <p class="text-xs uppercase tracking-wide text-brand-secondary/70">IVA incassata</p>
                    <p class="mt-2 text-xl font-semibold text-brand-deep">{formatCurrency(collectedVatYtd)}</p>
                    <p class="text-xs text-brand-secondary/80">separata dall'incassato operativo</p>
                </article>
                <article class="rounded-xl border border-border-light bg-surface-muted p-4">
                    <p class="text-xs uppercase tracking-wide text-brand-secondary/70">Incassato netto</p>
                    <p class="mt-2 text-xl font-semibold text-brand-deep">{formatCurrency(collectedNetYtd)}</p>
                    <p class="text-xs text-brand-secondary/80">al netto dell'IVA</p>
                </article>
            </div>
        </section>

        {#if !isCurrentYear}
            <div class="mb-6 bg-brand-accent/15 border border-brand-accent/25 rounded-xl p-4 flex items-center gap-3 text-sm text-brand-deep">
                <svg aria-hidden="true" viewBox="0 0 20 20" class="h-4 w-4 shrink-0 text-brand-deep/80 fill-current">
                    <path fill-rule="evenodd" d="M5.75 8.5a4.25 4.25 0 1 1 8.5 0V10h.25A1.75 1.75 0 0 1 16.25 11.75v4.5A1.75 1.75 0 0 1 14.5 18h-9A1.75 1.75 0 0 1 3.75 16.25v-4.5A1.75 1.75 0 0 1 5.5 10h.25V8.5Zm1.5 0V10h5V8.5a2.75 2.75 0 1 0-5 0Z" clip-rule="evenodd"></path>
                </svg>
                <span>Visualizzazione in sola lettura per l'anno fiscale {fiscalYear}.</span>
            </div>
        {/if}

        <section class="card-brand p-4 sm:p-5 mb-6">
            <h3 class="text-base font-semibold text-brand-deep">Focus oggi</h3>
            <div class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-3 text-sm">
                <a href="/sell-invoices?payment=overdue" class="dashboard-action-link">
                    <span>Fatture scadute</span>
                    <span class="badge badge-overdue">{overdueCount}</span>
                </a>
                <a href="/sell-invoices?status=draft" class="dashboard-action-link">
                    <span>Bozze da chiudere</span>
                    <span class="badge badge-draft">{draftCount}</span>
                </a>
                <a href="/sell-invoices?status=xml_validated" class="dashboard-action-link">
                    <span>Pronte per SDI</span>
                    <span class="badge badge-neutral">{readyForSdiCount}</span>
                </a>
            </div>
        </section>

        <section class="grid grid-cols-1 gap-6 xl:grid-cols-12 mb-6">
            <article class="card-brand xl:col-span-8">
                <div class="border-b border-border-light px-4 py-3 sm:px-6 flex items-center justify-between gap-3">
                    <h3 class="text-base font-semibold text-brand-deep">Fatture recenti</h3>
                    <a href="/sell-invoices" class="text-xs font-medium text-brand-secondary hover:text-brand-deep">Vedi tutte</a>
                </div>
                <div class="hidden sm:grid grid-cols-12 gap-3 px-6 py-2 text-[11px] uppercase tracking-wide text-brand-secondary/70 border-b border-border-light bg-surface-muted">
                    <span class="col-span-4">Documento</span>
                    <span class="col-span-2">Stato</span>
                    <span class="col-span-2">Scadenza</span>
                    <span class="col-span-2 text-right">Totale documento</span>
                    <span class="col-span-2 text-right">Azione</span>
                </div>
                <div class="divide-y divide-border-light">
                    {#if recentInvoices.length > 0}
                        {#each recentInvoices.slice(0, 6) as invoice}
                            <div class="px-4 py-4 sm:px-6 sm:py-3 hover:bg-surface-muted/60 transition-colors">
                                <div class="sm:hidden">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <a href="/sell-invoices/{invoice.id}/edit" class="text-sm font-semibold text-brand-deep hover:underline">
                                                {invoice.number ?? '#' + invoice.id}
                                            </a>
                                            <p class="text-xs text-brand-secondary/80 truncate mt-0.5">{invoice.contact?.name ?? 'Cliente non associato'}</p>
                                        </div>
                                        <span class="text-sm font-semibold text-brand-deep tabular-nums">{formatCurrency(invoice.total_gross ?? 0)}</span>
                                    </div>
                                    <div class="mt-2 flex items-center justify-between">
                                        <span class="badge {invoice.payment_status === 'paid' ? 'badge-sent' : (invoice.payment_status === 'overdue' ? 'badge-overdue' : 'badge-draft')}">
                                            {invoice.payment_status === 'paid' ? 'Pagata' : (invoice.payment_status === 'overdue' ? 'Scaduta' : 'Da incassare')}
                                        </span>
                                        <a href="/sell-invoices/{invoice.id}/edit" class="text-xs font-medium text-brand-accent hover:underline">Apri</a>
                                    </div>
                                </div>

                                <div class="hidden sm:grid sm:grid-cols-12 sm:gap-3 sm:items-center">
                                    <div class="col-span-4 min-w-0">
                                        <a href="/sell-invoices/{invoice.id}/edit" class="text-sm font-semibold text-brand-deep hover:underline">
                                            {invoice.number ?? '#' + invoice.id}
                                        </a>
                                        <p class="text-xs text-brand-secondary/80 truncate">{invoice.contact?.name ?? 'Cliente non associato'}</p>
                                    </div>
                                    <div class="col-span-2">
                                        <span class="badge {invoice.payment_status === 'paid' ? 'badge-sent' : (invoice.payment_status === 'overdue' ? 'badge-overdue' : 'badge-draft')}">
                                            {invoice.payment_status === 'paid' ? 'Pagata' : (invoice.payment_status === 'overdue' ? 'Scaduta' : 'Da incassare')}
                                        </span>
                                    </div>
                                    <div class="col-span-2 text-sm text-brand-secondary/80">{shortDate(invoice.due_date ?? invoice.issue_date)}</div>
                                    <div class="col-span-2 text-right text-sm font-semibold text-brand-deep tabular-nums">{formatCurrency(invoice.total_gross ?? 0)}</div>
                                    <div class="col-span-2 text-right">
                                        <a href="/sell-invoices/{invoice.id}/edit" class="text-xs font-medium text-brand-accent hover:underline">Apri</a>
                                    </div>
                                </div>
                            </div>
                        {/each}
                    {:else}
                        <div class="px-4 py-10 text-sm text-brand-secondary/70 sm:px-6">
                            Nessuna fattura disponibile.
                        </div>
                    {/if}
                </div>
            </article>

            <article class="card-brand xl:col-span-4">
                <div class="border-b border-border-light px-4 py-3 sm:px-5">
                    <h3 class="text-base font-semibold text-brand-deep">Scadenze</h3>
                </div>
                <div class="p-4 sm:p-5">
                    <p class="text-xs uppercase tracking-wide text-brand-secondary/70 mb-2">Prossime scadenze</p>
                    <div class="space-y-2">
                        {#if upcomingDueDates.length > 0}
                            {#each upcomingDueDates.slice(0, 5) as invoice}
                                <a href="/sell-invoices/{invoice.id}/edit" class="dashboard-list-link">
                                    <p class="text-sm font-medium text-brand-deep truncate">{invoice.contact?.name ?? 'Cliente'}</p>
                                    <p class="text-xs text-brand-secondary/80 mt-0.5">{shortDate(invoice.due_date)} - {formatCurrency(invoice.total_gross ?? 0)}</p>
                                </a>
                            {/each}
                        {:else}
                            <p class="text-sm text-brand-secondary/70">Nessuna scadenza imminente.</p>
                        {/if}
                    </div>
                </div>
            </article>
        </section>

        <section class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <article class="card-brand p-4 sm:p-5">
                <h3 class="text-base font-semibold text-brand-deep">Salute incassi</h3>
                <p class="mt-3 text-xs uppercase tracking-wide text-brand-secondary/70">Tasso incasso YTD</p>
                <p class="mt-1 text-2xl font-semibold text-brand-deep">{collectionRate.toFixed(1)}%</p>
                <div class="mt-3 h-2 w-full rounded-full bg-surface-muted">
                    <div class="h-2 rounded-full bg-brand-deep" style={`width: ${collectionRate}%`}></div>
                </div>
                <p class="mt-2 text-xs text-brand-secondary/80">{formatCurrency(collectedNetYtd)} incassati su {formatCurrency(revenueYtd)}</p>
                <p class="mt-1 text-xs text-brand-secondary/80">IVA incassata separata: {formatCurrency(collectedVatYtd)}</p>
            </article>

            <article class="card-brand p-4 sm:p-5">
                <div class="flex items-center justify-between gap-3">
                    <h3 class="text-base font-semibold text-brand-deep">Clienti migliori</h3>
                    <a href="/contacts" class="text-xs font-medium text-brand-secondary hover:text-brand-deep">Vedi tutti</a>
                </div>
                {#if topClients.length > 0}
                    <div class="mt-3 space-y-1">
                        {#each topClients.slice(0, 5) as client, index}
                            <div class="flex items-center justify-between rounded-lg border border-border-light px-3 py-2">
                                <div class="min-w-0">
                                    <p class="text-sm font-medium text-brand-deep truncate">{index + 1}. {client.contact?.name ?? 'Cliente'}</p>
                                    <p class="text-xs text-brand-secondary/80">Valore medio {formatCurrency(averageInvoiceValue)}</p>
                                </div>
                                <span class="text-sm font-semibold text-brand-deep tabular-nums">{formatCurrency(client.revenue_total ?? 0)}</span>
                            </div>
                        {/each}
                    </div>
                {:else}
                    <p class="mt-3 text-sm text-brand-secondary/70">Nessun cliente con fatturato disponibile.</p>
                {/if}
            </article>
        </section>
    </div>
</Authenticated>
