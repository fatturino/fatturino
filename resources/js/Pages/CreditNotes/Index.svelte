<script>
    import Authenticated from '$layouts/Authenticated.svelte'
    import Button from '$lib/components/ui/Button.svelte'
    import Input from '$lib/components/ui/Input.svelte'
    import Textarea from '$lib/components/ui/Textarea.svelte'
    import Dialog from '$lib/components/ui/Dialog.svelte'
    import SortableInvoiceTable from '$lib/components/invoices/SortableInvoiceTable.svelte'
    import { showToast } from '$lib/toast.js'
    import { router } from '@inertiajs/svelte'

    let {
        creditNotes = { data: [], current_page: 1, last_page: 1, from: 0, to: 0, total: 0, links: [] },
        fiscalYear = new Date().getFullYear(),
        stats = {},
        search: initialSearch = '',
        filterStatus: initialStatus = '',
        sort: initialSort = 'date',
        direction: initialDirection = 'desc',
        statusOptions = [],
    } = $props()

    let searchValue = $state(initialSearch)
    let statusFilter = $state(initialStatus)
    let sort = $state(initialSort)
    let direction = $state(initialDirection)
    let listState = $state({ invoices: creditNotes, stats, statusOptions, paymentOptions: [] })
    let confirmOpen = $state(false)
    let confirmTitle = $state('')
    let confirmDescription = $state('')
    let confirmText = $state('Conferma')
    let confirmVariant = $state('primary')
    let onConfirmAction = $state(() => {})
    let emailModalOpen = $state(false)
    let emailModalLoading = $state(false)
    let emailSending = $state(false)
    let emailCreditNote = $state(null)
    let emailForm = $state({ recipient_email: '', cc: '', subject: '', body: '' })

    const statusTabs = $derived([
        { label: 'Tutte', value: '', count: listState.invoices.total ?? 0 },
        { label: 'Bozze', value: 'draft', count: listState.stats.draft_count ?? 0 },
        { label: 'Salvate', value: 'xml_validated', count: listState.stats.xml_validated_count ?? 0 },
        { label: 'Inviate', value: 'sent', count: listState.stats.sent_count ?? 0 },
    ])

    function submitSearch() {
        const url = new URL(window.location.href)
        if (searchValue) url.searchParams.set('search', searchValue)
        else url.searchParams.delete('search')
        url.searchParams.delete('page')
        window.location.href = url.toString()
    }

    function clearFilters() {
        window.location.href = '/credit-notes'
    }

    function applyStatusTab(statusValue) {
        const url = new URL(window.location.href)
        if (statusValue) url.searchParams.set('status', statusValue)
        else url.searchParams.delete('status')
        url.searchParams.delete('page')
        window.location.href = url.toString()
    }

    function hasActiveFilters() {
        return statusFilter || searchValue
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

    function isTabActive(tabValue) {
        if (tabValue === '') return !statusFilter
        return statusFilter === tabValue
    }

    async function postAction(url, payload = {}) {
        return await new Promise((resolve) => {
            router.post(url, payload, {
                preserveScroll: true,
                preserveState: true,
                only: ['creditNotes', 'stats', 'statusOptions'],
                onError: (errors) => {
                    const firstError = Object.values(errors ?? {})[0]
                    const message = Array.isArray(firstError) ? firstError[0] : firstError
                    showToast(message || 'Operazione non riuscita.', 'error')
                    resolve(false)
                },
                onSuccess: () => resolve(true),
            })
        })
    }

    async function validateXml(creditNote) {
        confirmTitle = 'Conferma validazione XML'
        confirmDescription = `Confermi la validazione XML della nota ${creditNote.number ?? '#' + creditNote.id}?`
        confirmText = 'Verifica XML'
        confirmVariant = 'primary'
        onConfirmAction = async () => {
            await postAction(`/credit-notes/${creditNote.id}/validate-xml`)
        }
        confirmOpen = true
    }

    async function sendToSdi(creditNote) {
        confirmTitle = 'Conferma invio SDI'
        confirmDescription = `Stai per inviare allo SDI la nota di credito ${creditNote.number ?? '#' + creditNote.id}.

Questa azione è irreversibile.
Dopo l'invio non potrai più modificarla.

Controlla prima di confermare:
- Anagrafica cliente
- Importi e aliquote IVA
- Codice destinatario o PEC`
        confirmText = 'Invia SDI'
        confirmVariant = 'danger'
        onConfirmAction = async () => {
            await postAction(`/credit-notes/${creditNote.id}/send-sdi`)
        }
        confirmOpen = true
    }

    async function sendEmail(creditNote) {
        emailCreditNote = creditNote
        emailModalOpen = true
        emailModalLoading = true

        try {
            const response = await fetch(`/credit-notes/${creditNote.id}/email-preview`, {
                headers: {
                    'Accept': 'application/json',
                },
            })
            const data = await response.json()
            if (!response.ok || !data.success) {
                throw new Error(data.error || 'Anteprima email non disponibile.')
            }

            emailForm = {
                recipient_email: data.preview?.recipient_email ?? '',
                cc: data.preview?.cc ?? '',
                subject: data.preview?.subject ?? '',
                body: data.preview?.body ?? '',
            }
        } catch (error) {
            showToast(error?.message || 'Anteprima email non disponibile.', 'error')
            emailModalOpen = false
            emailCreditNote = null
        } finally {
            emailModalLoading = false
        }
    }

    async function submitEmailModal() {
        if (!emailCreditNote) return false
        if (!emailForm.recipient_email) {
            showToast('Inserisci un destinatario email.', 'error')
            return false
        }

        emailSending = true
        try {
            const sent = await postAction(`/credit-notes/${emailCreditNote.id}/send-email`, emailForm)
            return sent
        } finally {
            emailSending = false
        }
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
<Dialog
    bind:open={emailModalOpen}
    title={`Invia email ${emailCreditNote?.number ?? (emailCreditNote ? '#' + emailCreditNote.id : '')}`}
    confirmText="Invia email"
    onConfirm={submitEmailModal}
    isLoading={emailSending}
    contentClass="max-w-2xl"
>
    {#if emailModalLoading}
        <p class="text-sm text-brand-secondary">Caricamento anteprima email...</p>
    {:else}
        <div class="space-y-4">
            <label class="block">
                <span class="text-sm font-medium text-brand-deep">Destinatario</span>
                <Input class="mt-1 block w-full" type="email" bind:value={emailForm.recipient_email} />
            </label>
            <label class="block">
                <span class="text-sm font-medium text-brand-deep">CC (opzionale)</span>
                <Input class="mt-1 block w-full" type="email" bind:value={emailForm.cc} />
            </label>
            <label class="block">
                <span class="text-sm font-medium text-brand-deep">Oggetto</span>
                <Input class="mt-1 block w-full" type="text" bind:value={emailForm.subject} />
            </label>
            <label class="block">
                <span class="text-sm font-medium text-brand-deep">Messaggio</span>
                <Textarea class="mt-1 block w-full min-h-64 resize-y" bind:value={emailForm.body} />
            </label>
        </div>
    {/if}
</Dialog>

<Authenticated>
    {#snippet headerActions()}
        <a href="/credit-notes/create" class="btn-brand text-sm">Nuova nota di credito</a>
    {/snippet}

    <div class="page-shell pb-24 sm:pb-6 w-full">
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
            <div class="card-brand p-4 sm:p-5"><p class="text-[11px] text-brand-secondary/70 font-medium uppercase tracking-wide mb-2">Totale</p><p class="text-2xl font-semibold text-brand-deep">{formatCurrency(listState.stats.total_gross)}</p><p class="text-xs text-brand-secondary/70 mt-1">{listState.stats.total_count ?? 0} note</p></div>
            <div class="card-brand p-4 sm:p-5"><p class="text-[11px] text-brand-secondary/70 font-medium uppercase tracking-wide mb-2">Inviate</p><p class="text-2xl font-semibold text-brand-deep">{listState.stats.sent_count ?? 0}</p><p class="text-xs text-brand-secondary/70 mt-1">allo SDI</p></div>
            <div class="card-brand p-4 sm:p-5"><p class="text-[11px] text-brand-secondary/70 font-medium uppercase tracking-wide mb-2">Valore medio</p><p class="text-2xl font-semibold text-brand-deep">{listState.stats.total_count > 0 ? formatCurrency(listState.stats.total_gross / listState.stats.total_count) : '—'}</p><p class="text-xs text-brand-secondary/70 mt-1">per nota</p></div>
            <div class="card-brand p-4 sm:p-5"><p class="text-[11px] text-brand-secondary/70 font-medium uppercase tracking-wide mb-2">Bozze</p><p class="text-2xl font-semibold text-brand-deep">{listState.stats.draft_count ?? 0}</p><p class="text-xs text-brand-secondary/70 mt-1">da completare</p></div>
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
                    <label class="sr-only" for="credit-note-search">Cerca note di credito</label>
                    <Input
                        id="credit-note-search"
                        type="text"
                        class="block w-full rounded-lg border border-border px-3 py-2 text-sm"
                        placeholder="Cerca per numero o cliente"
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

        <SortableInvoiceTable
            invoices={listState.invoices.data}
            {sort}
            {direction}
            contactLabel="Cliente"
            hasActiveFilters={hasActiveFilters()}
            emptyFilteredMessage="Nessuna nota di credito trovata con questi filtri."
            emptyMessage="Nessuna nota di credito ancora emessa."
            desktopColspan={6}
        >
            {#snippet desktopHeaders()}
                <th class="px-4 py-3 font-semibold text-brand-secondary text-xs uppercase tracking-wider text-right">Totale</th>
                <th class="px-4 py-3 font-semibold text-brand-secondary text-xs uppercase tracking-wider">Stato</th>
                <th class="px-4 py-3 font-semibold text-brand-secondary text-xs uppercase tracking-wider text-right">Azioni</th>
            {/snippet}
            {#snippet desktopRow({ invoice: creditNote, formatDate })}
                <tr class="border-b border-border-light hover:bg-surface-muted/70 transition-colors">
                    <td class="px-4 py-3 font-semibold text-brand-deep whitespace-nowrap">{creditNote.number ?? '#' + creditNote.id}</td>
                    <td class="px-4 py-3 text-brand-secondary whitespace-nowrap">{formatDate(creditNote.date)}</td>
                    <td class="px-4 py-3 font-medium text-brand-deep">{creditNote.contact?.name ?? '—'}</td>
                    <td class="px-4 py-3 text-right font-semibold tabular-nums text-brand-deep">{formatCurrency(creditNote.total_gross)}</td>
                    <td class="px-4 py-3"><span class="inline-block px-2 py-0.5 rounded-full text-xs font-medium {statusBadgeClass(creditNote.status)}">{statusLabel(creditNote.status)}</span></td>
                    <td class="px-4 py-3 text-right"><div class="flex justify-end gap-2 flex-wrap"><a href={`/credit-notes/${creditNote.id}/xml`} class="text-xs font-medium text-brand-secondary hover:text-brand-deep">XML</a><Button class="text-xs font-medium text-brand-secondary hover:text-brand-deep" onclick={() => sendEmail(creditNote)}>Email</Button>{#if creditNote.is_sdi_editable && creditNote.status === 'draft'}<Button class="text-xs font-medium text-brand-secondary hover:text-brand-deep" onclick={() => validateXml(creditNote)}>Verifica XML</Button>{/if}{#if creditNote.is_sdi_editable && creditNote.status === 'xml_validated'}<Button class="text-xs font-medium text-brand-secondary hover:text-brand-deep" onclick={() => sendToSdi(creditNote)}>Invia SDI</Button>{/if}<a href={`/credit-notes/${creditNote.id}/edit`} class="text-xs font-medium text-brand-accent hover:underline">Modifica</a></div></td>
                </tr>
            {/snippet}
            {#snippet mobileRow({ invoice: creditNote, formatDate })}
                <article class="card-brand p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <a href={`/credit-notes/${creditNote.id}/edit`} class="text-sm font-semibold text-brand-deep hover:underline">{creditNote.number ?? '#' + creditNote.id}</a>
                            <p class="text-sm text-brand-secondary/80 mt-0.5">{creditNote.contact?.name ?? '—'}</p>
                        </div>
                        <span class="text-xs text-brand-secondary">{formatDate(creditNote.date)}</span>
                    </div>
                    <div class="mt-3 grid grid-cols-2 gap-2 text-sm">
                        <p class="text-brand-secondary">Totale</p>
                        <p class="text-right font-semibold text-brand-deep tabular-nums">{formatCurrency(creditNote.total_gross)}</p>
                    </div>
                    <div class="mt-3 flex items-center gap-2 flex-wrap">
                        <span class="inline-block px-2 py-0.5 rounded-full text-xs font-medium {statusBadgeClass(creditNote.status)}">{statusLabel(creditNote.status)}</span>
                    </div>
                    <div class="mt-3 flex items-center gap-3 text-xs">
                        <a href={`/credit-notes/${creditNote.id}/xml`} class="font-medium text-brand-secondary">XML</a>
                        <Button class="font-medium text-brand-secondary" onclick={() => sendEmail(creditNote)}>Email</Button>
                        {#if creditNote.is_sdi_editable && creditNote.status === 'draft'}
                            <Button class="font-medium text-brand-secondary" onclick={() => validateXml(creditNote)}>Verifica XML</Button>
                        {/if}
                        {#if creditNote.is_sdi_editable && creditNote.status === 'xml_validated'}
                            <Button class="font-medium text-brand-secondary" onclick={() => sendToSdi(creditNote)}>Invia SDI</Button>
                        {/if}
                        <a href={`/credit-notes/${creditNote.id}/edit`} class="font-medium text-brand-accent">Modifica</a>
                    </div>
                </article>
            {/snippet}
        </SortableInvoiceTable>

        {#if listState.invoices.last_page > 1}<div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between mt-4 text-sm"><p class="text-brand-secondary/70">{listState.invoices.from}–{listState.invoices.to} di {listState.invoices.total} note di credito</p><div class="flex gap-1 flex-wrap">{#each listState.invoices.links as link}{#if link.url}<a href={link.url} class="px-3 py-1.5 rounded-lg text-sm font-medium transition-colors {link.active ? 'bg-brand-deep text-white' : 'text-brand-secondary hover:bg-surface-muted'}">{@html link.label}</a>{/if}{/each}</div></div>{/if}

    </div>
</Authenticated>
