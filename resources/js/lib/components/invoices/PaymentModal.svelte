<script>
    import Button from '$lib/components/ui/Button.svelte'
    import Input from '$lib/components/ui/Input.svelte'
    import Dialog from '$lib/components/ui/Dialog.svelte'
    import { showToast } from '$lib/toast.js'

    let {
        open = $bindable(false),
        invoice = null,
        basePath,
    } = $props()

    let paymentAmount = $state('')
    let paymentDate = $state('')
    let paymentReference = $state('')
    let paymentNotes = $state('')
    let paymentBankName = $state('')
    let editingPaymentId = $state(null)

    function formatCurrency(value) {
        return new Intl.NumberFormat('it-IT', { style: 'currency', currency: 'EUR', minimumFractionDigits: 0 }).format((value || 0) / 100)
    }

    function formatDate(dateStr) {
        if (!dateStr) return '-'
        return new Date(dateStr).toLocaleDateString('it-IT')
    }

    function csrfToken() {
        const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/)
        return match ? decodeURIComponent(match[1]) : ''
    }

    function remainingAmountCents(currentInvoice) {
        return Math.max(0, (currentInvoice?.net_due || currentInvoice?.total_gross || 0) - (currentInvoice?.total_paid || 0))
    }

    function vatRatePercent(value) {
        switch (value) {
            case 'R22': return 22
            case 'R10': return 10
            case 'R5': return 5
            case 'R4': return 4
            default: return 0
        }
    }

    function payableVatAmount(currentInvoice) {
        if (!currentInvoice) return 0
        if (!currentInvoice.split_payment) return currentInvoice.total_vat || 0
        if (!currentInvoice.fund_enabled || !currentInvoice.fund_amount || !currentInvoice.fund_vat_rate) return 0

        return Math.round((currentInvoice.fund_amount || 0) * (vatRatePercent(currentInvoice.fund_vat_rate) / 100))
    }

    function operationalPaymentSplit(currentInvoice) {
        const netDue = Math.max(0, currentInvoice?.net_due || 0)
        const payableVat = Math.min(netDue, payableVatAmount(currentInvoice))
        const appliedPaid = Math.min(Math.max(0, currentInvoice?.total_paid || 0), netDue)

        if (netDue === 0) {
            return {
                collectedNet: 0,
                collectedVat: 0,
                outstandingNet: 0,
                outstandingVat: 0,
            }
        }

        const collectedVat = payableVat > 0 ? Math.min(payableVat, Math.round((appliedPaid * payableVat) / netDue)) : 0
        const collectedNet = Math.max(0, appliedPaid - collectedVat)

        return {
            collectedNet,
            collectedVat,
            outstandingNet: Math.max(0, netDue - payableVat - collectedNet),
            outstandingVat: Math.max(0, payableVat - collectedVat),
        }
    }

    function setQuickPayment(fraction) {
        if (!invoice) return
        const cents = Math.max(1, Math.round(remainingAmountCents(invoice) * fraction))
        paymentAmount = (cents / 100).toFixed(2)
    }

    function applyPaymentResponse(data) {
        if (!invoice) return
        invoice.total_paid = data.total_paid
        invoice.payment_status = data.payment_status
        invoice.payments = data.payments || []
    }

    function startEditPayment(payment) {
        editingPaymentId = payment.id
        paymentAmount = ((payment.amount || 0) / 100).toFixed(2)
        paymentDate = payment.paid_at || ''
        paymentReference = payment.reference || ''
        paymentNotes = payment.notes || ''
        paymentBankName = payment.bank_name || ''
    }

    function resetPaymentForm() {
        editingPaymentId = null
        paymentAmount = ''
        paymentDate = ''
        paymentReference = ''
        paymentNotes = ''
        paymentBankName = ''
    }

    async function savePayment() {
        const parsedAmount = Number.parseFloat(paymentAmount)
        if (!Number.isFinite(parsedAmount) || parsedAmount <= 0) {
            showToast('Inserisci un importo valido maggiore di zero.', 'error')
            return
        }

        const isEdit = !!editingPaymentId
        const url = isEdit
            ? `${basePath}/${invoice.id}/payments/${editingPaymentId}`
            : `${basePath}/${invoice.id}/payments`

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
                notes: paymentNotes || null,
                bank_name: paymentBankName || null,
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
        const response = await fetch(`${basePath}/${invoice.id}/payments/${payment.id}`, {
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
    bind:open
    title={invoice ? `Registra pagamento - ${invoice.number ?? '#' + invoice.id}` : 'Registra pagamento'}
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
            <div class="mt-2">
                <label class="mb-1 block text-sm font-medium text-brand-deep">Causale accredito (opzionale)</label>
                <Input type="text" bind:value={paymentNotes} class="block w-full rounded-lg border border-border px-3 py-2 text-sm" placeholder="Es. saldo fattura aprile" />
            </div>
            <div class="mt-2">
                <label class="mb-1 block text-sm font-medium text-brand-deep">Banca accredito (opzionale)</label>
                <Input type="text" bind:value={paymentBankName} class="block w-full rounded-lg border border-border px-3 py-2 text-sm" placeholder="Es. Intesa Sanpaolo" />
            </div>
        </div>
        {#if invoice}
            {@const split = operationalPaymentSplit(invoice)}
            <div class="rounded-lg border border-border-light bg-surface-muted px-3 py-2 text-xs text-brand-secondary">
                <div class="flex items-center justify-between">
                    <span>Totale pagato</span>
                    <span class="font-semibold text-brand-deep">{formatCurrency(invoice.total_paid || 0)}</span>
                </div>
                <div class="mt-1 flex items-center justify-between">
                    <span>Incassato netto</span>
                    <span class="font-semibold text-brand-deep">{formatCurrency(split.collectedNet)}</span>
                </div>
                <div class="mt-1 flex items-center justify-between">
                    <span>IVA incassata</span>
                    <span class="font-semibold text-brand-deep">{formatCurrency(split.collectedVat)}</span>
                </div>
                <div class="mt-1 flex items-center justify-between">
                    <span>Residuo netto</span>
                    <span class="font-semibold text-brand-deep">{formatCurrency(split.outstandingNet)}</span>
                </div>
                <div class="mt-1 flex items-center justify-between">
                    <span>IVA da incassare</span>
                    <span class="font-semibold text-brand-deep">{formatCurrency(split.outstandingVat)}</span>
                </div>
            </div>
            <div class="rounded-lg border border-border-light px-3 py-2">
                <p class="mb-2 text-xs font-medium text-brand-deep">Pagamenti registrati</p>
                {#if (invoice.payments || []).length > 0}
                    <div class="space-y-1.5">
                        {#each invoice.payments as payment}
                            <div class="flex items-center justify-between gap-3 text-xs">
                                <div class="min-w-0">
                                    <p class="text-brand-secondary">{formatDate(payment.paid_at)}</p>
                                    <p class="font-semibold text-brand-deep">{formatCurrency(payment.amount || 0)}</p>
                                    {#if payment.reference}
                                        <p class="text-brand-secondary/80">Rif: {payment.reference}</p>
                                    {/if}
                                    {#if payment.notes}
                                        <p class="text-brand-secondary/80">Causale: {payment.notes}</p>
                                    {/if}
                                    {#if payment.bank_name}
                                        <p class="text-brand-secondary/80">Banca: {payment.bank_name}</p>
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
