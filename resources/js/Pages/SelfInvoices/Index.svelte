<script>
    import Authenticated from '$layouts/Authenticated.svelte'
    import Button from '$lib/components/ui/Button.svelte'
    import Input from '$lib/components/ui/Input.svelte'
    import Dialog from '$lib/components/ui/Dialog.svelte'
    import PaymentModal from '$lib/components/invoices/PaymentModal.svelte'
    import InvoiceDesktopContextMenu from '$lib/components/invoices/InvoiceDesktopContextMenu.svelte'
    import SortableInvoiceTable from '$lib/components/invoices/SortableInvoiceTable.svelte'
    import { buildInvoiceContextActions, InvoiceContentType } from '$lib/invoices/context-menu-registry.js'
    import { showToast } from '$lib/toast.js'

    let {
        invoices = { data: [], current_page: 1, last_page: 1, from: 0, to: 0, total: 0, links: [] },
        fiscalYear = new Date().getFullYear(),
        stats = {},
        search: initialSearch = '',
        filterStatus: initialStatus = '',
        filterPayment: initialPayment = '',
        sort: initialSort = 'created_at',
        direction: initialDirection = 'desc',
        statusOptions = [],
        paymentOptions = [],
    } = $props()

    let searchValue = $state(initialSearch)
    let statusFilter = $state(initialStatus)
    let paymentFilter = $state(initialPayment)
    let sort = $state(initialSort)
    let direction = $state(initialDirection)
    let listState = $state({ invoices, stats, statusOptions, paymentOptions })
    let confirmOpen = $state(false)
    let confirmTitle = $state('')
    let confirmDescription = $state('')
    let confirmText = $state('Conferma')
    let confirmVariant = $state('primary')
    let onConfirmAction = $state(() => {})
    let paymentModalOpen = $state(false)
    let paymentInvoice = $state(null)

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
        window.location.href = '/self-invoices'
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

    function csrfToken() {
        const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/)
        return match ? decodeURIComponent(match[1]) : ''
    }

    async function postAction(url, successMessage) {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-XSRF-TOKEN': csrfToken(),
            },
        })

        const data = await response.json()
        if (data.success) {
            showToast(successMessage)
            window.location.reload()
            return
        }

        const errors = Array.isArray(data.errors) ? data.errors.join('\n') : null
        showToast(errors || data.error || 'Operazione non riuscita.', 'error')
    }

    async function validateXml(invoice) {
        confirmTitle = 'Conferma validazione XML'
        confirmDescription = `Confermi la validazione XML dell'autofattura ${invoice.number ?? '#' + invoice.id}?`
        confirmText = 'Verifica XML'
        confirmVariant = 'primary'
        onConfirmAction = async () => {
            await postAction(`/self-invoices/${invoice.id}/validate-xml`, 'XML validato.')
        }
        confirmOpen = true
    }

    async function sendToSdi(invoice) {
        confirmTitle = 'Conferma invio SDI'
        confirmDescription = `Stai per inviare allo SDI l'autofattura ${invoice.number ?? '#' + invoice.id}.

Questa azione è irreversibile.
Dopo l'invio non potrai più modificarla.

Controlla prima di confermare:
- Anagrafica fornitore
- Importi e aliquote IVA
- Codice destinatario o PEC`
        confirmText = 'Invia SDI'
        confirmVariant = 'danger'
        onConfirmAction = async () => {
            await postAction(`/self-invoices/${invoice.id}/send-sdi`, 'Autofattura inviata allo SDI.')
        }
        confirmOpen = true
    }

    async function sendEmail(invoice) {
        await postAction(`/self-invoices/${invoice.id}/send-email`, 'Email inviata.')
    }

    function openPaymentModal(invoice) {
        paymentInvoice = invoice
        paymentInvoice.payments = (invoice.payments || []).slice().sort((a, b) => {
            const da = a.paid_at ?? ''
            const db = b.paid_at ?? ''
            if (da === db) return (b.id || 0) - (a.id || 0)
            return db.localeCompare(da)
        })
        paymentModalOpen = true
    }

    function contextActions(invoice) {
        return buildInvoiceContextActions({
            contentType: InvoiceContentType.SELF_INVOICE,
            item: invoice,
            links: {
                edit: `/self-invoices/${invoice.id}/edit`,
                xml: `/self-invoices/${invoice.id}/xml`,
                pdf: `/self-invoices/${invoice.id}/pdf`,
            },
            callbacks: {
                validateXml,
                sendToSdi,
                sendEmail,
                recordPayment: openPaymentModal,
            },
        })
    }

</script>

<Dialog
    bind:open={confirmOpen}
    title={confirmTitle}
    description={confirmDescription}
    confirmText={confirmText}
    variant={confirmVariant}
    onConfirm={onConfirmAction}
/>
<PaymentModal bind:open={paymentModalOpen} invoice={paymentInvoice} basePath="/self-invoices" />

<Authenticated>
    {#snippet headerActions()}
        <a href="/self-invoices/create" class="btn-brand text-sm">Nuova autofattura</a>
    {/snippet}

    <div class="page-shell pb-24 sm:pb-6 w-full">
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
            <div class="card-brand p-4 sm:p-5"><p class="text-[11px] text-brand-secondary/70 font-medium uppercase tracking-wide mb-2">Totale</p><p class="text-2xl font-semibold text-brand-deep">{formatCurrency(listState.stats.total_gross)}</p><p class="text-xs text-brand-secondary/70 mt-1">{listState.stats.total_count ?? 0} autofatture</p></div>
            <div class="card-brand p-4 sm:p-5"><p class="text-[11px] text-brand-secondary/70 font-medium uppercase tracking-wide mb-2">Da pagare</p><p class="text-2xl font-semibold text-brand-deep">{formatCurrency(listState.stats.unpaid_amount)}</p><p class="text-xs text-brand-secondary/70 mt-1">{listState.stats.unpaid_count ?? 0} non pagate</p></div>
            <div class="card-brand p-4 sm:p-5"><p class="text-[11px] text-brand-secondary/70 font-medium uppercase tracking-wide mb-2">Valore medio</p><p class="text-2xl font-semibold text-brand-deep">{listState.stats.total_count > 0 ? formatCurrency(listState.stats.total_gross / listState.stats.total_count) : '—'}</p><p class="text-xs text-brand-secondary/70 mt-1">per autofattura</p></div>
            <div class="card-brand p-4 sm:p-5"><p class="text-[11px] text-brand-secondary/70 font-medium uppercase tracking-wide mb-2">Scadute</p><p class="text-2xl font-semibold text-brand-deep">{listState.stats.overdue_count ?? 0}</p><p class="text-xs text-brand-secondary/70 mt-1">da saldare</p></div>
        </div>

        <section class="card-brand p-4 sm:p-5 mb-6">
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
                    <label class="sr-only" for="self-search">Cerca autofatture</label>
                    <Input
                        id="self-search"
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
        <SortableInvoiceTable
            invoices={listState.invoices.data}
            {sort}
            {direction}
            contactLabel="Fornitore"
            hasActiveFilters={hasActiveFilters()}
            emptyFilteredMessage="Nessuna autofattura trovata con questi filtri."
            emptyMessage="Nessuna autofattura."
            desktopColspan={7}
        >
            {#snippet desktopHeaders()}
                <th class="px-4 py-3 font-semibold text-brand-secondary text-xs uppercase tracking-wider text-right">Totale</th>
                <th class="px-4 py-3 font-semibold text-brand-secondary text-xs uppercase tracking-wider">Stato</th>
                <th class="px-4 py-3 font-semibold text-brand-secondary text-xs uppercase tracking-wider">Pagamento</th>
                <th class="px-4 py-3 font-semibold text-brand-secondary text-xs uppercase tracking-wider text-right">Azioni</th>
            {/snippet}
            {#snippet desktopRow({ invoice, formatDate })}
                <InvoiceDesktopContextMenu actions={contextActions(invoice)}>
                    {#snippet children({ triggerProps })}
                        <tr {...triggerProps} class="border-b border-border-light hover:bg-surface-muted/70 transition-colors cursor-context-menu">
                            <td class="px-4 py-3 font-semibold text-brand-deep whitespace-nowrap">{invoice.number ?? '#' + invoice.id}</td>
                            <td class="px-4 py-3 text-brand-secondary whitespace-nowrap">{formatDate(invoice.created_at ?? invoice.date)}</td>
                            <td class="px-4 py-3 font-medium text-brand-deep">{invoice.contact?.name ?? '—'}</td>
                            <td class="px-4 py-3 text-right font-semibold tabular-nums text-brand-deep">{formatCurrency(invoice.total_gross)}</td>
                            <td class="px-4 py-3"><span class="inline-block px-2 py-0.5 rounded-full text-xs font-medium {statusBadgeClass(invoice.status)}">{statusLabel(invoice.status)}</span></td>
                            <td class="px-4 py-3"><span class="inline-block px-2 py-0.5 rounded-full text-xs font-medium {paymentBadgeClass(invoice.payment_status)}">{paymentLabel(invoice.payment_status)}</span></td>
                            <td class="px-4 py-3 text-right">
                                <a href={`/self-invoices/${invoice.id}/edit`} class="inline-flex h-8 w-8 items-center justify-center rounded-md text-brand-secondary transition hover:bg-surface-muted hover:text-brand-deep" aria-label="Modifica autofattura" title="Modifica">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                        <path d="M13.586 2.586a2 2 0 0 1 2.828 2.828l-9.5 9.5a1 1 0 0 1-.447.263l-3 1a1 1 0 0 1-1.264-1.264l1-3a1 1 0 0 1 .263-.447l9.5-9.5ZM12.172 4 5.02 11.152l-.58 1.739 1.739-.58L13.33 5.16 12.172 4Z" />
                                    </svg>
                                </a>
                            </td>
                        </tr>
                    {/snippet}
                </InvoiceDesktopContextMenu>
            {/snippet}
            {#snippet mobileRow({ invoice, formatDate })}
                <article class="card-brand p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <a href={`/self-invoices/${invoice.id}/edit`} class="text-sm font-semibold text-brand-deep hover:underline">{invoice.number ?? '#' + invoice.id}</a>
                            <p class="text-sm text-brand-secondary/80 mt-0.5">{invoice.contact?.name ?? '—'}</p>
                        </div>
                        <span class="text-xs text-brand-secondary">{formatDate(invoice.created_at ?? invoice.date)}</span>
                    </div>
                    <div class="mt-3 grid grid-cols-2 gap-2 text-sm">
                        <p class="text-brand-secondary">Totale</p>
                        <p class="text-right font-semibold text-brand-deep tabular-nums">{formatCurrency(invoice.total_gross)}</p>
                    </div>
                    <div class="mt-3 flex items-center gap-2 flex-wrap">
                        <span class="inline-block px-2 py-0.5 rounded-full text-xs font-medium {statusBadgeClass(invoice.status)}">{statusLabel(invoice.status)}</span>
                        <span class="inline-block px-2 py-0.5 rounded-full text-xs font-medium {paymentBadgeClass(invoice.payment_status)}">{paymentLabel(invoice.payment_status)}</span>
                    </div>
                    <div class="mt-3 flex items-center gap-3 text-xs">
                        <Button class="font-medium text-brand-secondary" onclick={() => openPaymentModal(invoice)}>Segna pagamento</Button>
                        <a href={`/self-invoices/${invoice.id}/xml`} class="font-medium text-brand-secondary">XML</a>
                        {#if invoice.is_sdi_editable && invoice.status === 'draft'}
                            <Button class="font-medium text-brand-secondary" onclick={() => validateXml(invoice)}>Verifica XML</Button>
                        {/if}
                        {#if invoice.is_sdi_editable && invoice.status === 'xml_validated'}
                            <Button class="font-medium text-brand-secondary" onclick={() => sendToSdi(invoice)}>Invia SDI</Button>
                        {/if}
                        <a href={`/self-invoices/${invoice.id}/edit`} class="font-medium text-brand-accent">Modifica</a>
                    </div>
                </article>
            {/snippet}
        </SortableInvoiceTable>

        {#if listState.invoices.last_page > 1}<div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between mt-4 text-sm"><p class="text-brand-secondary/70">{listState.invoices.from}–{listState.invoices.to} di {listState.invoices.total} autofatture</p><div class="flex gap-1 flex-wrap">{#each listState.invoices.links as link}{#if link.url}<a href={link.url} class="px-3 py-1.5 rounded-lg text-sm font-medium transition-colors {link.active ? 'bg-brand-deep text-white' : 'text-brand-secondary hover:bg-surface-muted'}">{@html link.label}</a>{/if}{/each}</div></div>{/if}

    </div>
</Authenticated>
