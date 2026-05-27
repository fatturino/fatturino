<script>
    import Authenticated from '$layouts/Authenticated.svelte'
    import Button from '$lib/components/ui/Button.svelte'
    import Input from '$lib/components/ui/Input.svelte'
    import Dialog from '$lib/components/ui/Dialog.svelte'
    import InvoiceDesktopContextMenu from '$lib/components/invoices/InvoiceDesktopContextMenu.svelte'
    import { buildInvoiceContextActions, InvoiceContentType } from '$lib/invoices/context-menu-registry.js'
    import { showToast } from '$lib/toast.js'

    let {
        invoices = { data: [], current_page: 1, last_page: 1, from: 0, to: 0, total: 0, links: [] },
        fiscalYear = new Date().getFullYear(),
        stats = {},
        search: initialSearch = '',
        filterStatus: initialStatus = '',
        filterPayment: initialPayment = '',
        statusOptions = [],
        paymentOptions = [],
    } = $props()

    let searchValue = $state(initialSearch)
    let statusFilter = $state(initialStatus)
    let paymentFilter = $state(initialPayment)
    let listState = $state({ invoices, stats, statusOptions, paymentOptions })
    let paymentModalOpen = $state(false)
    let paymentInvoice = $state(null)
    let paymentAmount = $state('')
    let paymentDate = $state('')
    let paymentReference = $state('')
    let editingPaymentId = $state(null)

    const statusTabs = $derived([
        { label: 'Tutte', value: '', count: listState.invoices.total ?? 0 },
        { label: 'Bozze', value: 'draft', count: listState.stats.draft_count ?? 0 },
        { label: 'Da pagare', value: 'unpaid', count: listState.stats.unpaid_count ?? 0 },
        { label: 'Scadute', value: 'overdue', count: listState.stats.overdue_count ?? 0 },
    ])

    function submitSearch() {
        const url = new URL(window.location.href)
        if (searchValue) url.searchParams.set('search', searchValue)
        else url.searchParams.delete('search')
        url.searchParams.delete('page')
        window.location.href = url.toString()
    }

    function clearFilters() {
        window.location.href = '/purchase-invoices'
    }

    function applyStatusTab(statusValue) {
        const url = new URL(window.location.href)
        if (statusValue === 'overdue') {
            url.searchParams.set('payment', 'overdue')
            url.searchParams.delete('status')
        } else if (statusValue === 'unpaid') {
            url.searchParams.set('payment', 'unpaid')
            url.searchParams.delete('status')
        } else {
            url.searchParams.delete('payment')
            if (statusValue) url.searchParams.set('status', statusValue)
            else url.searchParams.delete('status')
        }
        url.searchParams.delete('page')
        window.location.href = url.toString()
    }

    function formatCurrency(value) {
        return new Intl.NumberFormat('it-IT', { style: 'currency', currency: 'EUR', minimumFractionDigits: 0 }).format((value || 0) / 100)
    }

    function formatDate(dateStr) {
        if (!dateStr) return '—'
        return new Date(dateStr).toLocaleDateString('it-IT')
    }

    function statusLabel(value) {
        const opt = listState.statusOptions.find((o) => o.value === value)
        return opt ? opt.label : value
    }

    function statusBadgeClass(value) {
        switch (value) {
            case 'draft': return 'badge-draft'
            case 'xml_validated': return 'badge-neutral'
            case 'sent': return 'badge-sent'
            default: return 'badge-neutral'
        }
    }

    function paymentLabel(value) {
        const opt = listState.paymentOptions.find((o) => o.value === value)
        return opt ? opt.label : value
    }

    function paymentBadgeClass(value) {
        switch (value) {
            case 'unpaid': return 'badge-draft'
            case 'partial': return 'badge-neutral'
            case 'paid': return 'badge-sent'
            case 'overdue': return 'badge-overdue'
            default: return 'badge-neutral'
        }
    }

    function hasActiveFilters() {
        return statusFilter || paymentFilter || searchValue
    }

    function isTabActive(tabValue) {
        if (tabValue === 'overdue') return paymentFilter === 'overdue'
        if (tabValue === 'unpaid') return paymentFilter === 'unpaid'
        if (tabValue === '') return !statusFilter && !paymentFilter
        return statusFilter === tabValue
    }

    function contextActions(invoice) {
        return buildInvoiceContextActions({
            contentType: InvoiceContentType.PURCHASE,
            item: invoice,
            links: {
                edit: `/purchase-invoices/${invoice.id}/edit`,
            },
            callbacks: {
                recordPayment: openPaymentModal,
            },
        })
    }

    function csrfToken() {
        const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/)
        return match ? decodeURIComponent(match[1]) : ''
    }

    function openPaymentModal(invoice) {
        paymentInvoice = invoice
        paymentInvoice.payments = (invoice.payments || []).slice().sort((a, b) => {
            const da = a.paid_at ?? ''
            const db = b.paid_at ?? ''
            if (da === db) return (b.id || 0) - (a.id || 0)
            return db.localeCompare(da)
        })
        paymentAmount = ''
        paymentDate = ''
        paymentReference = ''
        editingPaymentId = null
        paymentModalOpen = true
    }

    function remainingAmountCents(invoice) {
        return Math.max(0, (invoice?.net_due || invoice?.total_gross || 0) - (invoice?.total_paid || 0))
    }

    function setQuickPayment(fraction) {
        if (!paymentInvoice) return
        const cents = Math.max(1, Math.round(remainingAmountCents(paymentInvoice) * fraction))
        paymentAmount = (cents / 100).toFixed(2)
    }

    function applyPaymentResponse(data) {
        if (!paymentInvoice) return
        paymentInvoice.total_paid = data.total_paid
        paymentInvoice.payment_status = data.payment_status
        paymentInvoice.payments = data.payments || []
    }

    function startEditPayment(payment) {
        editingPaymentId = payment.id
        paymentAmount = ((payment.amount || 0) / 100).toFixed(2)
        paymentDate = payment.paid_at || ''
        paymentReference = payment.reference || ''
    }

    function resetPaymentForm() {
        editingPaymentId = null
        paymentAmount = ''
        paymentDate = ''
        paymentReference = ''
    }

    async function savePayment() {
        const parsedAmount = Number.parseFloat(paymentAmount)
        if (!Number.isFinite(parsedAmount) || parsedAmount <= 0) {
            showToast('Inserisci un importo valido maggiore di zero.', 'error')
            return
        }

        const isEdit = !!editingPaymentId
        const url = isEdit
            ? `/purchase-invoices/${paymentInvoice.id}/payments/${editingPaymentId}`
            : `/purchase-invoices/${paymentInvoice.id}/payments`

        const response = await fetch(url, {
            method: isEdit ? 'PUT' : 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-XSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify({
                amount: parsedAmount,
                paid_at: paymentDate || null,
                reference: paymentReference || null,
            }),
        })

        const data = await response.json()
        if (!response.ok || !data.success) {
            const errors = Array.isArray(data.errors) ? data.errors.join('\n') : null
            showToast(errors || data.error || 'Registrazione pagamento non riuscita.', 'error')
            return
        }

        applyPaymentResponse(data)
        showToast(isEdit ? 'Pagamento aggiornato.' : 'Pagamento registrato.')
        resetPaymentForm()
    }

    async function deletePayment(payment) {
        const response = await fetch(`/purchase-invoices/${paymentInvoice.id}/payments/${payment.id}`, {
            method: 'DELETE',
            headers: {
                'Accept': 'application/json',
                'X-XSRF-TOKEN': csrfToken(),
            },
        })

        const data = await response.json()
        if (!response.ok || !data.success) {
            const errors = Array.isArray(data.errors) ? data.errors.join('\n') : null
            showToast(errors || data.error || 'Eliminazione pagamento non riuscita.', 'error')
            return
        }

        applyPaymentResponse(data)
        if (editingPaymentId === payment.id) {
            resetPaymentForm()
        }
        showToast('Pagamento eliminato.')
    }

</script>

<Dialog
    bind:open={paymentModalOpen}
    title={paymentInvoice ? `Registra pagamento - ${paymentInvoice.number ?? '#' + paymentInvoice.id}` : 'Registra pagamento'}
    description="Inserisci importo e, se disponibile, la data pagamento."
    confirmText={editingPaymentId ? 'Aggiorna pagamento' : 'Salva pagamento'}
    onConfirm={savePayment}
>
    <div class="space-y-3">
        <div class="rounded-lg border border-border-light p-3">
            <div class="mb-2 flex items-center justify-between">
                <p class="text-xs font-medium text-brand-deep">{editingPaymentId ? 'Modifica pagamento' : 'Nuovo pagamento'}</p>
                {#if editingPaymentId}
                    <Button class="text-xs text-brand-secondary" onclick={resetPaymentForm}>Annulla modifica</Button>
                {/if}
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-brand-deep">Importo (EUR)</label>
                <Input type="number" min="0.01" step="0.01" bind:value={paymentAmount} class="block w-full rounded-lg border border-border px-3 py-2 text-sm" />
            </div>
            <div class="mt-2 flex items-center gap-2">
                <Button class="btn-outline text-xs" onclick={() => setQuickPayment(1)}>Tutto</Button>
                <Button class="btn-outline text-xs" onclick={() => setQuickPayment(0.5)}>1/2</Button>
                <Button class="btn-outline text-xs" onclick={() => setQuickPayment(1 / 3)}>1/3</Button>
            </div>
            <div class="mt-2">
                <label class="mb-1 block text-sm font-medium text-brand-deep">Data pagamento (opzionale)</label>
                <Input type="date" bind:value={paymentDate} class="block w-full rounded-lg border border-border px-3 py-2 text-sm" />
            </div>
            <div class="mt-2">
                <label class="mb-1 block text-sm font-medium text-brand-deep">Rif. bancario (opzionale)</label>
                <Input type="text" bind:value={paymentReference} class="block w-full rounded-lg border border-border px-3 py-2 text-sm" placeholder="CRO, TRN, ID operazione" />
            </div>
        </div>
        {#if paymentInvoice}
            <div class="rounded-lg border border-border-light bg-surface-muted px-3 py-2 text-xs text-brand-secondary">
                <div class="flex items-center justify-between">
                    <span>Totale pagato</span>
                    <span class="font-semibold text-brand-deep">{formatCurrency(paymentInvoice.total_paid || 0)}</span>
                </div>
                <div class="mt-1 flex items-center justify-between">
                    <span>Residuo</span>
                    <span class="font-semibold text-brand-deep">{formatCurrency(remainingAmountCents(paymentInvoice))}</span>
                </div>
            </div>
            <div class="rounded-lg border border-border-light px-3 py-2">
                <p class="mb-2 text-xs font-medium text-brand-deep">Pagamenti registrati</p>
                {#if (paymentInvoice.payments || []).length > 0}
                    <div class="space-y-1.5">
                        {#each paymentInvoice.payments as payment}
                            <div class="flex items-center justify-between gap-3 text-xs">
                                <div class="min-w-0">
                                    <p class="text-brand-secondary">{formatDate(payment.paid_at)}</p>
                                    <p class="font-semibold text-brand-deep">{formatCurrency(payment.amount || 0)}</p>
                                    {#if payment.reference}
                                        <p class="text-brand-secondary/80">Rif: {payment.reference}</p>
                                    {/if}
                                </div>
                                <div class="flex items-center gap-2">
                                    <Button class="text-xs text-brand-secondary" onclick={() => startEditPayment(payment)}>Modifica</Button>
                                    <Button class="text-xs text-red-600" onclick={() => deletePayment(payment)}>Elimina</Button>
                                </div>
                            </div>
                        {/each}
                    </div>
                {:else}
                    <p class="text-xs text-brand-secondary">Nessun pagamento registrato.</p>
                {/if}
            </div>
        {/if}
    </div>
</Dialog>

<Authenticated>
    <div class="page-shell pb-24 sm:pb-6 w-full">
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
            <div class="card-brand p-4 sm:p-5"><p class="text-[11px] text-brand-secondary/70 font-medium uppercase tracking-wide mb-2">Totale</p><p class="text-2xl font-semibold text-brand-deep">{formatCurrency(listState.stats.total_gross)}</p><p class="text-xs text-brand-secondary/70 mt-1">{listState.stats.total_count ?? 0} fatture</p></div>
            <div class="card-brand p-4 sm:p-5"><p class="text-[11px] text-brand-secondary/70 font-medium uppercase tracking-wide mb-2">Da pagare</p><p class="text-2xl font-semibold text-brand-deep">{formatCurrency(listState.stats.unpaid_amount)}</p><p class="text-xs text-brand-secondary/70 mt-1">{listState.stats.unpaid_count ?? 0} non pagate</p></div>
            <div class="card-brand p-4 sm:p-5"><p class="text-[11px] text-brand-secondary/70 font-medium uppercase tracking-wide mb-2">Valore medio</p><p class="text-2xl font-semibold text-brand-deep">{listState.stats.total_count > 0 ? formatCurrency(listState.stats.total_gross / listState.stats.total_count) : '—'}</p><p class="text-xs text-brand-secondary/70 mt-1">per fattura</p></div>
            <div class="card-brand p-4 sm:p-5"><p class="text-[11px] text-brand-secondary/70 font-medium uppercase tracking-wide mb-2">Scadute</p><p class="text-2xl font-semibold text-brand-deep">{listState.stats.overdue_count ?? 0}</p><p class="text-xs text-brand-secondary/70 mt-1">da saldare</p></div>
        </div>

        <section class="card-brand p-4 sm:p-5 mb-4">
            <p class="mb-3 text-sm text-brand-deep bg-brand-accent/15 border border-brand-accent/25 rounded-lg px-3 py-2">Le fatture di acquisto vengono importate automaticamente dallo SDI.</p>
            <div class="mb-3 grid grid-cols-2 gap-2 lg:grid-cols-4">
                {#each statusTabs as tab}
                    <button type="button" class="rounded-lg border px-3 py-2 text-left text-sm transition-colors {isTabActive(tab.value) ? 'border-brand-deep bg-brand-deep text-white' : 'border-border-light bg-white text-brand-deep hover:bg-surface-muted'}" onclick={() => applyStatusTab(tab.value)}>
                        <span class="font-medium">{tab.label}</span>
                        <span class="ml-2 text-xs opacity-80">{tab.count}</span>
                    </button>
                {/each}
            </div>
            <div class="flex flex-col gap-3 lg:flex-row">
                <div class="flex-1 min-w-0">
                    <label class="sr-only" for="purchase-search">Cerca fatture di acquisto</label>
                    <Input
                        id="purchase-search"
                        type="text"
                        class="block w-full rounded-lg border border-border px-3 py-2 text-sm"
                        placeholder="Cerca per numero o fornitore"
                        bind:value={searchValue}
                        onkeydown={(e) => { if (e.key === 'Enter') submitSearch() }}
                    />
                </div>
                <div class="flex items-center gap-2">
                    <Button class="btn-outline text-sm" onclick={submitSearch}>Cerca</Button>
                    {#if hasActiveFilters()}
                        <Button class="text-sm text-brand-secondary hover:text-brand-deep" onclick={clearFilters}>Reset</Button>
                    {/if}
                </div>
            </div>
        </section>

        <p class="hidden md:block mb-2 text-xs text-brand-secondary/80">Suggerimento: clic destro su una riga per aprire il menu contestuale.</p>
        <section class="card-brand overflow-hidden hidden md:block"><table class="w-full text-sm"><thead><tr class="border-b border-border-light bg-surface-muted text-left"><th class="px-4 py-3 font-semibold text-brand-secondary text-xs uppercase tracking-wider">Numero</th><th class="px-4 py-3 font-semibold text-brand-secondary text-xs uppercase tracking-wider">Data</th><th class="px-4 py-3 font-semibold text-brand-secondary text-xs uppercase tracking-wider">Fornitore</th><th class="px-4 py-3 font-semibold text-brand-secondary text-xs uppercase tracking-wider text-right">Totale</th><th class="px-4 py-3 font-semibold text-brand-secondary text-xs uppercase tracking-wider text-right">Da pagare</th><th class="px-4 py-3 font-semibold text-brand-secondary text-xs uppercase tracking-wider">Stato</th><th class="px-4 py-3 font-semibold text-brand-secondary text-xs uppercase tracking-wider">Pagamento</th><th class="px-4 py-3 font-semibold text-brand-secondary text-xs uppercase tracking-wider text-right">Azioni</th></tr></thead><tbody>{#each listState.invoices.data as invoice}<InvoiceDesktopContextMenu actions={contextActions(invoice)}>{#snippet children({ triggerProps })}<tr {...triggerProps} class="border-b border-border-light hover:bg-surface-muted/70 transition-colors cursor-context-menu"><td class="px-4 py-3 font-semibold text-brand-deep whitespace-nowrap">{invoice.number ?? '#' + invoice.id}</td><td class="px-4 py-3 text-brand-secondary whitespace-nowrap">{formatDate(invoice.date)}</td><td class="px-4 py-3 font-medium text-brand-deep">{invoice.contact?.name ?? '—'}</td><td class="px-4 py-3 text-right font-semibold tabular-nums text-brand-deep">{formatCurrency(invoice.total_gross)}</td><td class="px-4 py-3 text-right font-semibold tabular-nums text-brand-deep">{formatCurrency(Math.max(0, (invoice.net_due || invoice.total_gross) - (invoice.total_paid || 0)))}</td><td class="px-4 py-3"><span class="inline-block px-2 py-0.5 rounded-full text-xs font-medium {statusBadgeClass(invoice.status)}">{statusLabel(invoice.status)}</span></td><td class="px-4 py-3"><span class="inline-block px-2 py-0.5 rounded-full text-xs font-medium {paymentBadgeClass(invoice.payment_status)}">{paymentLabel(invoice.payment_status)}</span></td><td class="px-4 py-3 text-right"><a href={`/purchase-invoices/${invoice.id}/edit`} class="inline-flex h-8 w-8 items-center justify-center rounded-md text-brand-secondary transition hover:bg-surface-muted hover:text-brand-deep" aria-label="Modifica fattura acquisto" title="Modifica"><svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M13.586 2.586a2 2 0 0 1 2.828 2.828l-9.5 9.5a1 1 0 0 1-.447.263l-3 1a1 1 0 0 1-1.264-1.264l1-3a1 1 0 0 1 .263-.447l9.5-9.5ZM12.172 4 5.02 11.152l-.58 1.739 1.739-.58L13.33 5.16 12.172 4Z" /></svg></a></td></tr>{/snippet}</InvoiceDesktopContextMenu>{:else}<tr><td colspan="8" class="px-4 py-12 text-center text-brand-secondary/60">{hasActiveFilters() ? 'Nessuna fattura trovata con questi filtri.' : 'Nessuna fattura di acquisto.'}</td></tr>{/each}</tbody></table></section>

        <section class="md:hidden space-y-3">
            {#each listState.invoices.data as invoice}
                <article class="card-brand p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <a href={`/purchase-invoices/${invoice.id}/edit`} class="text-sm font-semibold text-brand-deep hover:underline">{invoice.number ?? '#' + invoice.id}</a>
                            <p class="text-sm text-brand-secondary/80 mt-0.5">{invoice.contact?.name ?? '—'}</p>
                        </div>
                        <span class="text-xs text-brand-secondary">{formatDate(invoice.date)}</span>
                    </div>
                    <div class="mt-3 grid grid-cols-2 gap-2 text-sm">
                        <p class="text-brand-secondary">Totale</p>
                        <p class="text-right font-semibold text-brand-deep tabular-nums">{formatCurrency(invoice.total_gross)}</p>
                        <p class="text-brand-secondary">Da pagare</p>
                        <p class="text-right font-semibold text-brand-deep tabular-nums">{formatCurrency(Math.max(0, (invoice.net_due || invoice.total_gross) - (invoice.total_paid || 0)))}</p>
                    </div>
                    <div class="mt-3 flex items-center gap-2 flex-wrap">
                        <span class="inline-block px-2 py-0.5 rounded-full text-xs font-medium {statusBadgeClass(invoice.status)}">{statusLabel(invoice.status)}</span>
                        <span class="inline-block px-2 py-0.5 rounded-full text-xs font-medium {paymentBadgeClass(invoice.payment_status)}">{paymentLabel(invoice.payment_status)}</span>
                    </div>
                    <div class="mt-3 flex items-center gap-3 text-xs">
                        <Button class="font-medium text-brand-secondary" onclick={() => openPaymentModal(invoice)}>Segna pagamento</Button>
                        <a href={`/purchase-invoices/${invoice.id}/edit`} class="font-medium text-brand-accent">Dettagli</a>
                    </div>
                </article>
            {:else}
                <div class="card-brand p-8 text-center text-sm text-brand-secondary/60">{hasActiveFilters() ? 'Nessuna fattura trovata con questi filtri.' : 'Nessuna fattura di acquisto.'}</div>
            {/each}
        </section>

        {#if listState.invoices.last_page > 1}<div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between mt-4 text-sm"><p class="text-brand-secondary/70">{listState.invoices.from}–{listState.invoices.to} di {listState.invoices.total} fatture</p><div class="flex gap-1 flex-wrap">{#each listState.invoices.links as link}{#if link.url}<a href={link.url} class="px-3 py-1.5 rounded-lg text-sm font-medium transition-colors {link.active ? 'bg-brand-deep text-white' : 'text-brand-secondary hover:bg-surface-muted'}">{@html link.label}</a>{/if}{/each}</div></div>{/if}
    </div>
</Authenticated>
