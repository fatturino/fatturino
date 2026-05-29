<script>
    import { useForm } from '@inertiajs/svelte'
    import { showToast } from '$lib/toast.js'
    import { onDestroy } from 'svelte'
    import DatePicker from '$lib/components/ui/DatePicker.svelte'
    import Button from '$lib/components/ui/Button.svelte'
    import Dialog from '$lib/components/ui/Dialog.svelte'
    import Input from '$lib/components/ui/Input.svelte'
    import Select from '$lib/components/ui/Select.svelte'
    import Textarea from '$lib/components/ui/Textarea.svelte'
    import Switch from '$lib/components/ui/Switch.svelte'
    import DownloadSimple from 'phosphor-svelte/lib/DownloadSimple'
    import ArrowsClockwise from 'phosphor-svelte/lib/ArrowsClockwise'
    import Envelope from 'phosphor-svelte/lib/Envelope'
    import { headerActionsStore } from '$lib/stores/header-actions.js'

    let {
        formData = {},
        errors = {},
        invoice = null,
        isReadOnly = false,
        indexPath = '/',
        endpointBase = '/',
        createLabel = 'Crea documento',
        updateLabel = 'Aggiorna documento',
        createSuccess = 'Documento creato.',
        updateSuccess = 'Documento aggiornato.',
        contactLabel = 'Cliente *',
        contactPlaceholder = 'Seleziona...',
        contactTypeLabel = 'cliente',
        showDocumentType = false,
        showDueDate = false,
        showRelatedInvoice = false,
        numberEditable = false,
        showPaidSummary = false,
        numberReadonlyText = 'Assegnato automaticamente al salvataggio',
        defaultDocumentType = 'TD01',
        showTabs = false,
        showPaymentTab = false,
        useSettingsDefaults = false,
        showLineDetails = false,
        showTaxOptions = false,
    } = $props()

    const bootstrap = JSON.parse(JSON.stringify({
        invoice,
        formData,
        useSettingsDefaults,
        showTaxOptions,
        defaultDocumentType,
    }))
    const initialInvoice = bootstrap.invoice ?? null
    const initialUseSettingsDefaults = Boolean(bootstrap.useSettingsDefaults)
    const initialShowTaxOptions = Boolean(bootstrap.showTaxOptions)
    const fiscalRegime = bootstrap.formData?.fiscal_regime ?? 'RF01'
    const isRf19 = fiscalRegime === 'RF19'

    const isEdit = initialInvoice !== null
    const settings = bootstrap.formData?.settings ?? {}
    const resolvedDefaultSequenceId = bootstrap.formData?.default_sequence_id ?? bootstrap.formData?.sequences?.[0]?.id ?? ''

    let activeDocumentTab = $state('dati')
    let confirmOpen = $state(false)
    let confirmTitle = $state('')
    let confirmDescription = $state('')
    let confirmText = $state('Conferma')
    let confirmVariant = $state('primary')
    let onConfirmAction = $state(() => {})

    function openConfirmDialog({ title, description, confirmText: confirmLabel = 'Conferma', variant = 'primary', onConfirm }) {
        confirmTitle = title
        confirmDescription = description
        confirmText = confirmLabel
        confirmVariant = variant
        onConfirmAction = onConfirm
        confirmOpen = true
    }

    const vatRateMap = {}
    bootstrap.formData?.vat_rates?.forEach((rate) => { vatRateMap[rate.id] = rate })

    function vatPercent(rateId) {
        const rate = vatRateMap[rateId]
        if (!rate) return 0
        const match = rate.name.match(/^(\d+)%/)
        return match ? parseInt(match[1], 10) : 0
    }

    function emptyLine() {
        return {
            description: '',
            quantity: 1,
            unit_of_measure: '',
            unit_price: 0,
            details_enabled: false,
            discount_enabled: false,
            discount_percent: null,
            vat_rate: isRf19 ? 'N2.2' : 'R22',
        }
    }

    function normalizeDateForPicker(value) {
        if (!value || typeof value !== 'string') return ''
        return value.includes('T') ? value.slice(0, 10) : value
    }

    let lines = $state(
        initialInvoice?.lines?.map((line) => ({
            id: line.id,
            description: line.description,
            quantity: Number(line.quantity),
            unit_of_measure: line.unit_of_measure ?? '',
            unit_price: (line.unit_price || 0) / 100,
            details_enabled: Number(line.quantity) !== 1 || (line.unit_of_measure ?? '') !== '' || (line.discount_percent !== null && line.discount_percent !== ''),
            discount_enabled: line.discount_percent !== null && line.discount_percent !== '',
            discount_percent: line.discount_percent ? Number(line.discount_percent) : null,
            vat_rate: line.vat_rate,
        })) ?? [emptyLine()]
    )

    function addLine() {
        lines = [...lines, emptyLine()]
    }

    function removeLine(index) {
        if (lines.length <= 1) return
        lines = lines.filter((_, i) => i !== index)
    }

    function lineTotal(line) {
        const gross = (Number(line.quantity) || 0) * (Number(line.unit_price) || 0)
        const discount = showLineDetails && line.discount_enabled && line.discount_percent ? Number(line.discount_percent) : 0
        return discount > 0 ? gross * (1 - discount / 100) : gross
    }

    let totalNet = $derived(lines.reduce((sum, line) => sum + lineTotal(line), 0))
    let withholdingTaxEnabled = $state(initialShowTaxOptions ? (initialInvoice?.withholding_tax_enabled ?? settings.withholding_tax_enabled ?? false) : false)
    let withholdingTaxPercent = $state(initialShowTaxOptions ? (initialInvoice?.withholding_tax_percent ?? settings.withholding_tax_percent ?? '20.00') : '20.00')
    let fundEnabled = $state(initialShowTaxOptions ? (initialInvoice?.fund_enabled ?? settings.fund_enabled ?? false) : false)
    let fundType = $state(initialShowTaxOptions ? (initialInvoice?.fund_type ?? settings.fund_type ?? '') : '')
    let fundPercent = $state(initialShowTaxOptions ? (initialInvoice?.fund_percent ?? settings.fund_percent ?? '4.00') : '4.00')
    let fundVatRate = $state(initialShowTaxOptions ? (initialInvoice?.fund_vat_rate ?? settings.fund_vat_rate ?? '') : '')
    let fundHasDeduction = $state(initialShowTaxOptions ? (initialInvoice?.fund_has_deduction ?? settings.fund_has_deduction ?? false) : false)
    let stampDutyApplied = $state(initialShowTaxOptions ? (initialInvoice?.stamp_duty_applied ?? settings.auto_stamp_duty ?? false) : false)
    let splitPayment = $state(initialShowTaxOptions ? (initialInvoice?.split_payment ?? settings.default_split_payment ?? false) : false)
    let vatPayability = $state(initialShowTaxOptions ? (initialInvoice?.vat_payability ?? settings.default_vat_payability ?? 'I') : 'I')

    let fundAmount = $derived.by(() => {
        if (!initialShowTaxOptions || !fundEnabled || !fundPercent) return 0
        return Math.round(totalNet * (Number(fundPercent) / 100) * 100) / 100
    })

    let fundVatAmount = $derived.by(() => {
        if (!initialShowTaxOptions || fundAmount <= 0 || !fundVatRate) return 0
        const pct = vatPercent(fundVatRate)
        return Math.round(fundAmount * (pct / 100) * 100) / 100
    })

    let totalVat = $derived(lines.reduce((sum, line) => {
        const pct = vatPercent(line.vat_rate)
        return sum + Math.round(lineTotal(line) * (pct / 100) * 100) / 100
    }, 0) + fundVatAmount)
    let totalGross = $derived(totalNet + totalVat + fundAmount)

    let totalDue = $derived.by(() => {
        let due = totalGross
        if (initialShowTaxOptions && stampDutyApplied) due += 2
        return due
    })

    let withholdingTaxAmount = $derived.by(() => {
        if (!initialShowTaxOptions || !withholdingTaxEnabled || !withholdingTaxPercent) return 0
        return Math.round(totalNet * (Number(withholdingTaxPercent) / 100) * 100) / 100
    })

    let netDue = $derived.by(() => {
        let due = totalDue - withholdingTaxAmount
        if (initialShowTaxOptions && splitPayment) {
            const lineVat = totalVat - fundVatAmount
            due -= lineVat
        }
        return due
    })

    const form = useForm({
        contact_id: initialInvoice?.contact_id ?? '',
        sequence_id: initialInvoice?.sequence_id ?? resolvedDefaultSequenceId,
        date: normalizeDateForPicker(initialInvoice?.date ?? ''),
        due_date: normalizeDateForPicker(initialInvoice?.due_date ?? ''),
        number: initialInvoice?.number ?? '',
        document_type: initialInvoice?.document_type ?? (bootstrap.defaultDocumentType ?? 'TD01'),
        related_invoice_number: initialInvoice?.related_invoice_number ?? '',
        related_invoice_date: normalizeDateForPicker(initialInvoice?.related_invoice_date ?? ''),
        notes: initialInvoice?.notes ?? (initialUseSettingsDefaults ? (settings.default_notes ?? '') : ''),
        payment_method: initialInvoice?.payment_method ?? (initialUseSettingsDefaults ? (settings.default_payment_method ?? '') : ''),
        payment_terms: initialInvoice?.payment_terms ?? (initialUseSettingsDefaults ? (settings.default_payment_terms ?? '') : ''),
        bank_name: initialInvoice?.bank_name ?? (initialUseSettingsDefaults ? (settings.default_bank_name ?? '') : ''),
        bank_iban: initialInvoice?.bank_iban ?? (initialUseSettingsDefaults ? (settings.default_bank_iban ?? '') : ''),
        withholding_tax_enabled: initialShowTaxOptions ? (initialInvoice?.withholding_tax_enabled ?? settings.withholding_tax_enabled ?? false) : false,
        withholding_tax_percent: initialShowTaxOptions ? (initialInvoice?.withholding_tax_percent ?? settings.withholding_tax_percent ?? '20.00') : '20.00',
        fund_enabled: initialShowTaxOptions ? (initialInvoice?.fund_enabled ?? settings.fund_enabled ?? false) : false,
        fund_type: initialShowTaxOptions ? (initialInvoice?.fund_type ?? settings.fund_type ?? '') : '',
        fund_percent: initialShowTaxOptions ? (initialInvoice?.fund_percent ?? settings.fund_percent ?? '4.00') : '4.00',
        fund_vat_rate: initialShowTaxOptions ? (initialInvoice?.fund_vat_rate ?? settings.fund_vat_rate ?? '') : '',
        fund_has_deduction: initialShowTaxOptions ? (initialInvoice?.fund_has_deduction ?? settings.fund_has_deduction ?? false) : false,
        stamp_duty_applied: initialShowTaxOptions ? (initialInvoice?.stamp_duty_applied ?? settings.auto_stamp_duty ?? false) : false,
        split_payment: initialShowTaxOptions ? (initialInvoice?.split_payment ?? settings.default_split_payment ?? false) : false,
        vat_payability: initialShowTaxOptions ? (initialInvoice?.vat_payability ?? settings.default_vat_payability ?? 'I') : 'I',
        lines: initialInvoice?.lines ?? [],
    })

    let numberPreview = $derived.by(() => {
        const sequence = bootstrap.formData?.sequences?.find((s) => s.id === Number(form.sequence_id))
        return sequence?.next_number ?? ''
    })

    let displayErrors = $derived({ ...errors, ...form.errors })

    function handleValidationError(validationErrors = {}) {
        const firstError = Object.values(validationErrors).flat().find(Boolean)
        showToast(firstError || 'Controlla i campi obbligatori.', 'error')
    }

    function executeSubmit() {
        form.withholding_tax_enabled = showTaxOptions && !isRf19 ? withholdingTaxEnabled : false
        form.withholding_tax_percent = showTaxOptions && !isRf19 ? withholdingTaxPercent : '0'
        form.fund_enabled = showTaxOptions ? fundEnabled : false
        form.fund_type = showTaxOptions ? (fundType ?? '') : ''
        form.fund_percent = showTaxOptions ? fundPercent : '0'
        form.fund_vat_rate = showTaxOptions ? (fundVatRate ?? '') : ''
        form.fund_has_deduction = showTaxOptions ? fundHasDeduction : false
        form.stamp_duty_applied = showTaxOptions ? stampDutyApplied : false
        form.split_payment = showTaxOptions && !isRf19 ? splitPayment : false
        form.vat_payability = showTaxOptions && !isRf19 ? (splitPayment ? 'S' : vatPayability) : 'I'

        form.lines = lines.map((line) => ({
            description: line.description,
            quantity: line.quantity,
            unit_of_measure: line.unit_of_measure,
            unit_price: line.unit_price,
            vat_rate: isRf19 ? 'N2.2' : line.vat_rate,
            discount_percent: showLineDetails && line.discount_enabled ? line.discount_percent : null,
        }))
        if (isEdit) {
            form.put(`${endpointBase}/${invoice.id}`, {
                preserveScroll: true,
                onSuccess: () => showToast(updateSuccess),
                onError: handleValidationError,
            })
            return
        }

        form.post(endpointBase, {
            preserveScroll: true,
            onSuccess: () => showToast(createSuccess),
            onError: handleValidationError,
        })
    }

    function handleSubmit() {
        if (isEdit && invoice?.status === 'xml_validated') {
            openConfirmDialog({
                title: 'Conferma modifica documento salvato',
                description: 'Salvando le modifiche il documento tornera in bozza e dovrai verificare XML di nuovo.',
                confirmText: 'Conferma e salva',
                onConfirm: executeSubmit,
            })
            return
        }

        executeSubmit()
    }

    function formatCurrency(value) {
        return new Intl.NumberFormat('it-IT', {
            style: 'currency',
            currency: 'EUR',
            minimumFractionDigits: 2,
        }).format(value)
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

    const hasSdiFlow = $derived(isEdit && ['/sell-invoices', '/self-invoices', '/credit-notes'].includes(endpointBase))

    async function validateXml() {
        if (!invoice) return
        const label = invoice.number ?? '#' + invoice.id
        openConfirmDialog({
            title: 'Conferma validazione XML',
            description: `Confermi la validazione XML del documento ${label}?`,
            confirmText: 'Verifica XML',
            onConfirm: async () => {
                await postAction(`${endpointBase}/${invoice.id}/validate-xml`, 'XML validato.')
            },
        })
    }

    async function sendToSdi() {
        if (!invoice) return
        const label = invoice.number ?? '#' + invoice.id
        openConfirmDialog({
            title: 'Conferma invio SDI',
            description: `Stai per inviare allo SDI il documento ${label}.

Questa azione è irreversibile.
Dopo l'invio non potrai più modificarlo.

Controlla prima di confermare:
- Anagrafica cliente
- Importi e aliquote IVA
- Codice destinatario o PEC`,
            confirmText: 'Invia SDI',
            variant: 'danger',
            onConfirm: async () => {
                await postAction(`${endpointBase}/${invoice.id}/send-sdi`, 'Documento inviato allo SDI.')
            },
        })
    }

    $effect(() => {
        headerActionsStore.set({
            indexPath,
            isReadOnly,
            processing: form.processing,
            submitLabel: isEdit ? updateLabel : createLabel,
            onSubmit: handleSubmit,
        })
    })

    onDestroy(() => {
        headerActionsStore.set(null)
    })
</script>

<Dialog
    bind:open={confirmOpen}
    title={confirmTitle}
    description={confirmDescription}
    confirmText={confirmText}
    variant={confirmVariant}
    onConfirm={onConfirmAction}
/>

<div class="p-4 pb-28 sm:p-6 sm:pb-6 w-full max-w-7xl mx-auto">
    {#if isReadOnly}
        <div class="mb-6 bg-brand-accent/15 border border-brand-accent/25 rounded-xl p-4 text-sm text-brand-deep">
            Questo documento non è più modificabile.
        </div>
    {/if}

    {#if hasSdiFlow}
        <div class="mb-6 card-brand p-4">
            <div class="flex flex-wrap items-center gap-3">
                <a href={`${endpointBase}/${invoice.id}/xml`} class="inline-flex items-center gap-2 rounded-lg border border-border-light px-3 py-1.5 text-sm font-medium text-brand-secondary hover:bg-surface-muted hover:text-brand-deep transition-colors">
                    <DownloadSimple size={16} weight="bold" />
                    <span>Scarica XML</span>
                </a>
                {#if invoice.is_sdi_editable && invoice.status === 'draft'}
                    <Button class="inline-flex items-center gap-2 rounded-lg border border-border-light px-3 py-1.5 text-sm font-medium text-brand-secondary hover:bg-surface-muted hover:text-brand-deep transition-colors" onclick={validateXml}>
                        <ArrowsClockwise size={16} weight="bold" />
                        <span>Verifica XML</span>
                    </Button>
                {/if}
                {#if invoice.is_sdi_editable && invoice.status === 'xml_validated'}
                    <Button class="inline-flex items-center gap-2 rounded-lg border border-border-light px-3 py-1.5 text-sm font-medium text-brand-secondary hover:bg-surface-muted hover:text-brand-deep transition-colors" onclick={sendToSdi}>
                        <Envelope size={16} weight="bold" />
                        <span>Invia SDI</span>
                    </Button>
                {/if}
            </div>
        </div>
    {/if}

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            <div class="card-brand p-6">
                {#if showTabs}
                    <div class={`mb-4 grid ${showPaymentTab ? 'grid-cols-3' : 'grid-cols-2'} gap-2`}>
                        <button
                            type="button"
                            class="rounded-lg border px-3 py-2 text-sm font-medium transition-colors {activeDocumentTab === 'dati' ? 'border-brand-deep bg-brand-deep text-white' : 'border-border-light bg-white text-brand-deep hover:bg-surface-muted'}"
                            onclick={() => activeDocumentTab = 'dati'}
                        >
                            Dati
                        </button>
                        {#if showPaymentTab}
                            <button
                                type="button"
                                class="rounded-lg border px-3 py-2 text-sm font-medium transition-colors {activeDocumentTab === 'pagamento' ? 'border-brand-deep bg-brand-deep text-white' : 'border-border-light bg-white text-brand-deep hover:bg-surface-muted'}"
                                onclick={() => activeDocumentTab = 'pagamento'}
                            >
                                Dettagli pagamento
                            </button>
                        {/if}
                        <button
                            type="button"
                            class="rounded-lg border px-3 py-2 text-sm font-medium transition-colors {activeDocumentTab === 'note' ? 'border-brand-deep bg-brand-deep text-white' : 'border-border-light bg-white text-brand-deep hover:bg-surface-muted'}"
                            onclick={() => activeDocumentTab = 'note'}
                        >
                            Note
                        </button>
                    </div>
                {/if}

                {#if !showTabs || activeDocumentTab === 'dati'}
                    <div class="grid grid-cols-2 gap-4">
                        <label class="block col-span-2 sm:col-span-1">
                            <span class="text-sm font-medium text-brand-deep">{contactLabel}</span>
                            <Select useNative class="mt-1 block w-full rounded-lg border border-brand-secondary/20 px-3 py-2 text-sm form-focus bg-white" bind:value={form.contact_id} disabled={isReadOnly}>
                                <option value="">{contactPlaceholder}</option>
                                {#each (formData.contacts ?? []) as contact}
                                    <option value={contact.id}>{contact.name}</option>
                                {/each}
                            </Select>
                            {#if displayErrors.contact_id}
                                <span class="text-red-600 text-xs mt-0.5 block">{displayErrors.contact_id}</span>
                            {/if}
                        </label>

                        <label class="block col-span-2 sm:col-span-1">
                            <span class="text-sm font-medium text-brand-deep">Numero</span>
                            {#if numberEditable}
                                <Input class="mt-1 block w-full rounded-lg border border-brand-secondary/20 px-3 py-2 text-sm form-focus" type="text" bind:value={form.number} disabled={isReadOnly} />
                            {:else}
                                <div class="mt-1 block w-full rounded-lg border border-brand-secondary/10 bg-brand-bg px-3 py-2 text-sm font-semibold text-brand-deep">
                                    {isEdit ? invoice.number : (numberPreview || '—')}
                                </div>
                            {/if}
                            {#if !isEdit && !numberEditable}
                                <span class="text-xs text-brand-secondary/40 mt-0.5 block">{numberReadonlyText}</span>
                            {/if}
                            {#if displayErrors.number}
                                <span class="text-red-600 text-xs mt-0.5 block">{displayErrors.number}</span>
                            {/if}
                        </label>

                        <label class="block col-span-2 sm:col-span-1">
                            <span class="text-sm font-medium text-brand-deep">Data *</span>
                            <DatePicker class="w-full" bind:value={form.date} disabled={isReadOnly} />
                            {#if displayErrors.date}
                                <span class="text-red-600 text-xs mt-0.5 block">{displayErrors.date}</span>
                            {/if}
                        </label>

                        {#if showDueDate}
                            <label class="block col-span-2 sm:col-span-1">
                                <span class="text-sm font-medium text-brand-deep">Scadenza</span>
                                <DatePicker class="w-full" bind:value={form.due_date} disabled={isReadOnly} />
                            </label>
                        {/if}

                        {#if showDocumentType}
                            <label class="block col-span-2 sm:col-span-1">
                                <span class="text-sm font-medium text-brand-deep">Tipo documento *</span>
                                <Select useNative class="mt-1 block w-full rounded-lg border border-brand-secondary/20 px-3 py-2 text-sm form-focus bg-white" bind:value={form.document_type} disabled={isReadOnly}>
                                    {#each (formData.document_types ?? []) as type}
                                        <option value={type.value ?? type.id}>{type.label ?? type.name}</option>
                                    {/each}
                                </Select>
                            </label>
                        {/if}
                    </div>

                    {#if showRelatedInvoice}
                        <div class="mt-6 pt-6 border-t border-brand-secondary/5">
                            <h3 class="text-base font-semibold text-brand-deep mb-3">Fattura collegata</h3>
                            <div class="grid grid-cols-2 gap-4">
                                <label class="block col-span-2 sm:col-span-1">
                                    <span class="text-sm font-medium text-brand-deep">Numero fattura {contactTypeLabel}</span>
                                    <Input class="mt-1 block w-full rounded-lg border border-brand-secondary/20 px-3 py-2 text-sm form-focus" type="text" bind:value={form.related_invoice_number} disabled={isReadOnly} />
                                </label>

                                <label class="block col-span-2 sm:col-span-1">
                                    <span class="text-sm font-medium text-brand-deep">Data fattura {contactTypeLabel}</span>
                                    <DatePicker class="w-full" bind:value={form.related_invoice_date} disabled={isReadOnly} />
                                </label>
                            </div>
                        </div>
                    {/if}
                {:else if activeDocumentTab === 'pagamento' && showPaymentTab}
                    <div class="grid grid-cols-2 gap-4">
                        <label class="block col-span-2 sm:col-span-1">
                            <span class="text-sm font-medium text-brand-deep">Metodo pagamento</span>
                            <Select useNative class="mt-1 block w-full rounded-lg border border-brand-secondary/20 px-3 py-2 text-sm form-focus bg-white" bind:value={form.payment_method} disabled={isReadOnly}>
                                <option value="">Seleziona...</option>
                                {#each (formData.payment_methods ?? []) as method}
                                    <option value={method.value}>{method.label}</option>
                                {/each}
                            </Select>
                        </label>

                        <label class="block col-span-2 sm:col-span-1">
                            <span class="text-sm font-medium text-brand-deep">Termini pagamento</span>
                            <Select useNative class="mt-1 block w-full rounded-lg border border-brand-secondary/20 px-3 py-2 text-sm form-focus bg-white" bind:value={form.payment_terms} disabled={isReadOnly}>
                                <option value="">Seleziona...</option>
                                {#each (formData.payment_terms ?? []) as term}
                                    <option value={term.value}>{term.label}</option>
                                {/each}
                            </Select>
                        </label>

                        <label class="block col-span-2 sm:col-span-1">
                            <span class="text-sm font-medium text-brand-deep">Banca</span>
                            <Input class="mt-1 block w-full rounded-lg border border-brand-secondary/20 px-3 py-2 text-sm form-focus" type="text" bind:value={form.bank_name} disabled={isReadOnly} />
                        </label>

                        <label class="block col-span-2 sm:col-span-1">
                            <span class="text-sm font-medium text-brand-deep">IBAN</span>
                            <Input class="mt-1 block w-full rounded-lg border border-brand-secondary/20 px-3 py-2 text-sm form-focus" type="text" bind:value={form.bank_iban} disabled={isReadOnly} />
                        </label>
                    </div>
                {:else if activeDocumentTab === 'note'}
                    <label class="block">
                        <Textarea class="mt-1 block w-full rounded-lg border border-brand-secondary/20 px-3 py-2 text-sm form-focus resize-y" rows="3" bind:value={form.notes} disabled={isReadOnly}></Textarea>
                    </label>
                    {#if displayErrors.notes}
                        <span class="text-red-600 text-xs mt-0.5 block">{displayErrors.notes}</span>
                    {/if}
                {/if}
            </div>

            <div class="card-brand p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-sm font-semibold text-brand-deep">Righe fattura</h2>
                    {#if !isReadOnly}
                        <Button class="text-sm text-brand-secondary/70 hover:text-brand-deep transition-colors" onclick={addLine}>+ Aggiungi riga</Button>
                    {/if}
                </div>
                <div class="space-y-3">
                    {#each lines as line, index}
                        {#if showLineDetails}
                            <div class="rounded-lg border border-border-light bg-brand-bg p-3">
                                <div class="grid grid-cols-1 sm:grid-cols-[1fr_8rem] gap-2">
                                    <div>
                                        <label class="text-xs text-brand-secondary/80" for={`line-${index}-description`}>Descrizione</label>
                                        <Input id={`line-${index}-description`} class="mt-1 w-full rounded border border-brand-secondary/20 px-2 py-1.5 text-sm form-focus bg-white" type="text" placeholder="Descrizione" bind:value={line.description} isDisabled={isReadOnly} />
                                    </div>
                                    <div>
                                        <span class="text-xs text-brand-secondary/80">Totale</span>
                                        <div class="mt-1 h-[34px] rounded border border-brand-secondary/10 bg-white px-2 py-1.5 text-sm font-semibold text-brand-deep tabular-nums text-right" aria-label={`Totale riga ${index + 1}`}>
                                            {formatCurrency(lineTotal(line))}
                                        </div>
                                    </div>
                                </div>

                                <div class="mt-2 grid grid-cols-1 sm:grid-cols-[12rem_1fr_10rem] gap-2 items-end">
                                    <div>
                                        <label class="text-xs text-brand-secondary/80" for={`line-${index}-unit-price`}>Importo</label>
                                        <Input id={`line-${index}-unit-price`} class="mt-1 w-full rounded border border-brand-secondary/20 px-2 py-1.5 text-sm text-right form-focus bg-white tabular-nums" type="number" min="0" step="0.01" placeholder="0,00" bind:value={line.unit_price} isDisabled={isReadOnly} />
                                    </div>
                                    <div>
                                        <label class="text-xs text-brand-secondary/80" for={`line-${index}-vat-rate`}>IVA</label>
                                        <Select id={`line-${index}-vat-rate`} useNative class="mt-1 w-full rounded border border-brand-secondary/20 px-2 py-1.5 text-sm form-focus bg-white" bind:value={line.vat_rate} isDisabled={isReadOnly}>
                                            {#each (formData.vat_rates ?? []) as rate}
                                                <option value={rate.id}>{rate.name}</option>
                                            {/each}
                                        </Select>
                                    </div>
                                    <div class="flex items-end">
                                        <Button class="btn-outline text-xs w-full" onclick={() => line.details_enabled = !line.details_enabled} disabled={isReadOnly}>
                                            {line.details_enabled ? 'Nascondi dettagli' : 'Dettagli'}
                                        </Button>
                                    </div>
                                </div>

                                {#if line.details_enabled}
                                    <div class="mt-3 rounded-lg border border-border-light bg-white p-3">
                                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                                            <div>
                                                <label class="text-xs text-brand-secondary/80" for={`line-${index}-quantity`}>Quantita</label>
                                                <Input id={`line-${index}-quantity`} class="mt-1 w-full rounded border border-brand-secondary/20 px-2 py-1.5 text-sm form-focus bg-white tabular-nums" type="number" min="0.01" step="0.01" bind:value={line.quantity} isDisabled={isReadOnly} />
                                            </div>
                                            <div>
                                                <label class="text-xs text-brand-secondary/80" for={`line-${index}-unit`}>UM</label>
                                                <Input id={`line-${index}-unit`} class="mt-1 w-full rounded border border-brand-secondary/20 px-2 py-1.5 text-sm form-focus bg-white" type="text" placeholder="UM" bind:value={line.unit_of_measure} isDisabled={isReadOnly} />
                                            </div>
                                            <div>
                                                <div class="flex items-center justify-between">
                                                    <label class="text-xs text-brand-secondary/80" for={`line-${index}-discount-enabled`}>Sconto %</label>
                                                    <Switch id={`line-${index}-discount-enabled`} bind:checked={line.discount_enabled} isDisabled={isReadOnly} class="scale-75 origin-right" />
                                                </div>
                                                <Input id={`line-${index}-discount-percent`} class="mt-1 w-full rounded border border-brand-secondary/20 px-2 py-1.5 text-sm text-right form-focus bg-white tabular-nums disabled:bg-slate-100" type="number" min="0" max="100" step="0.01" placeholder="0" bind:value={line.discount_percent} isDisabled={isReadOnly || !line.discount_enabled} />
                                            </div>
                                            <div class="flex items-end justify-end">
                                                {#if !isReadOnly && lines.length > 1}
                                                    <Button class="text-brand-secondary/40 hover:text-red-600 transition-colors text-xs" onclick={() => removeLine(index)}>Rimuovi</Button>
                                                {/if}
                                            </div>
                                        </div>
                                    </div>
                                {/if}
                            </div>
                        {:else}
                            <div class="grid grid-cols-12 gap-2 items-start p-3 bg-brand-bg rounded-lg">
                                <div class="col-span-12 sm:col-span-5">
                                    <Input class="w-full rounded border border-brand-secondary/20 px-2 py-1.5 text-sm form-focus bg-white" type="text" placeholder="Descrizione" bind:value={line.description} isDisabled={isReadOnly} ariaLabel={`Descrizione riga ${index + 1}`} />
                                </div>
                                <div class="col-span-3 sm:col-span-1">
                                    <Input class="w-full rounded border border-brand-secondary/20 px-2 py-1.5 text-sm form-focus bg-white tabular-nums" type="number" min="0.01" step="0.01" bind:value={line.quantity} isDisabled={isReadOnly} ariaLabel={`Quantita riga ${index + 1}`} />
                                </div>
                                <div class="col-span-3 sm:col-span-1">
                                    <Input class="w-full rounded border border-brand-secondary/20 px-2 py-1.5 text-sm form-focus bg-white" type="text" placeholder="UM" bind:value={line.unit_of_measure} isDisabled={isReadOnly} ariaLabel={`Unita di misura riga ${index + 1}`} />
                                </div>
                                <div class="col-span-3 sm:col-span-2">
                                    <Input class="w-full rounded border border-brand-secondary/20 px-2 py-1.5 text-sm text-right form-focus bg-white tabular-nums" type="number" min="0" step="0.01" placeholder="0,00" bind:value={line.unit_price} isDisabled={isReadOnly} ariaLabel={`Importo riga ${index + 1}`} />
                                </div>
                                <div class="col-span-6 sm:col-span-2">
                                    <Select useNative class="w-full rounded border border-brand-secondary/20 px-2 py-1.5 text-sm form-focus bg-white" bind:value={line.vat_rate} isDisabled={isReadOnly} ariaLabel={`Aliquota IVA riga ${index + 1}`}>
                                        {#each (formData.vat_rates ?? []) as rate}
                                            <option value={rate.id}>{rate.name}</option>
                                        {/each}
                                    </Select>
                                </div>
                                <div class="col-span-5 sm:col-span-1 flex items-center gap-1">
                                    <span class="text-sm font-semibold text-brand-deep tabular-nums w-full text-right">{formatCurrency(lineTotal(line))}</span>
                                    {#if !isReadOnly && lines.length > 1}
                                        <Button class="text-brand-secondary/40 hover:text-red-600 transition-colors text-xs ml-1" onclick={() => removeLine(index)}>✕</Button>
                                    {/if}
                                </div>
                            </div>
                        {/if}
                    {/each}
                </div>
                {#if displayErrors.lines}
                    <span class="text-red-600 text-xs mt-2 block">{displayErrors.lines}</span>
                {/if}
            </div>

            {#if !showTabs}
                <div class="card-brand p-6">
                    <h2 class="text-base font-semibold text-brand-deep mb-4">Note</h2>
                    <label class="block">
                        <Textarea class="mt-1 block w-full rounded-lg border border-brand-secondary/20 px-3 py-2 text-sm form-focus resize-y" rows="3" bind:value={form.notes} disabled={isReadOnly}></Textarea>
                    </label>
                    {#if displayErrors.notes}
                        <span class="text-red-600 text-xs mt-0.5 block">{displayErrors.notes}</span>
                    {/if}
                </div>
            {/if}
        </div>

        <div class="lg:col-span-1">
            <div class="card-brand p-6 sticky top-6">
                <h2 class="text-base font-semibold text-brand-deep mb-4">Riepilogo</h2>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-brand-secondary/60">Totale netto</span>
                        <span class="font-semibold tabular-nums">{formatCurrency(totalNet)}</span>
                    </div>
                    {#if showTaxOptions && fundEnabled}
                        <div class="flex justify-between">
                            <span class="text-brand-secondary/60">Cassa ({fundPercent}%)</span>
                            <span class="font-semibold tabular-nums">{formatCurrency(fundAmount)}</span>
                        </div>
                        {#if fundVatAmount > 0}
                            <div class="flex justify-between">
                                <span class="text-brand-secondary/60">IVA su cassa</span>
                                <span class="font-semibold tabular-nums">{formatCurrency(fundVatAmount)}</span>
                            </div>
                        {/if}
                    {/if}
                    <div class="flex justify-between">
                        <span class="text-brand-secondary/60">Totale IVA</span>
                        <span class="font-semibold tabular-nums">{formatCurrency(totalVat)}</span>
                    </div>
                    <hr class="border-brand-secondary/10" />
                    <div class="flex justify-between text-base">
                        <span class="text-brand-deep font-semibold">Totale lordo</span>
                        <span class="font-bold tabular-nums text-brand-deep">{formatCurrency(totalGross)}</span>
                    </div>
                    {#if showTaxOptions && stampDutyApplied}
                        <div class="flex justify-between">
                            <span class="text-brand-secondary/60">Marca da bollo</span>
                            <span class="font-semibold tabular-nums">€2,00</span>
                        </div>
                    {/if}
                    {#if showTaxOptions && !isRf19 && withholdingTaxEnabled}
                        <div class="flex justify-between">
                            <span class="text-brand-secondary/60">Rit. acconto ({withholdingTaxPercent}%)</span>
                            <span class="font-semibold tabular-nums text-red-600">-{formatCurrency(withholdingTaxAmount)}</span>
                        </div>
                    {/if}
                    {#if showTaxOptions && !isRf19 && splitPayment}
                        <div class="flex justify-between">
                            <span class="text-brand-secondary/60">IVA split payment</span>
                            <span class="font-semibold tabular-nums text-red-600">-{formatCurrency(totalVat - fundVatAmount)}</span>
                        </div>
                    {/if}
                    {#if showTaxOptions}
                        <hr class="border-brand-secondary/10" />
                        <div class="flex justify-between text-base">
                            <span class="text-brand-deep font-semibold">Netto a pagare</span>
                            <span class="font-bold tabular-nums text-brand-deep">{formatCurrency(netDue)}</span>
                        </div>
                    {/if}
                    {#if showPaidSummary && isEdit && invoice?.net_due != null && invoice?.total_paid > 0}
                        <hr class="border-brand-secondary/10" />
                        <div class="flex justify-between">
                            <span class="text-brand-secondary/60">Pagato</span>
                            <span class="font-semibold tabular-nums text-green-600">{formatCurrency(invoice.total_paid / 100)}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-brand-deep font-medium">Da pagare</span>
                            <span class="font-bold tabular-nums">{formatCurrency(Math.max(0, (invoice.net_due - invoice.total_paid) / 100))}</span>
                        </div>
                    {/if}
                </div>
            </div>
            {#if showTaxOptions}
                <div class="card-brand p-6 sticky top-[28rem] mt-4">
                    <h2 class="text-base font-semibold text-brand-deep mb-1">Opzioni fiscali</h2>
                    <p class="text-xs text-brand-secondary/80 mb-4">Disponibili solo per le fatture di vendita.</p>
                    <div class="space-y-3">
                        <div class="rounded-lg border border-border-light p-3">
                            {#if !isRf19}
                                <label class="flex items-center justify-between gap-3">
                                    <span class="text-sm font-medium text-brand-deep">Ritenuta d'acconto</span>
                                    <Switch bind:checked={withholdingTaxEnabled} disabled={isReadOnly} />
                                </label>
                            {/if}
                            {#if !isRf19 && withholdingTaxEnabled}
                                <div class="mt-3">
                                    <label class="text-xs text-brand-secondary/80" for="withholding-tax-percent">Percentuale</label>
                                    <Input id="withholding-tax-percent" class="mt-1 w-28 rounded-lg border border-brand-secondary/20 px-3 py-1.5 text-sm text-right form-focus bg-white" type="number" min="0" max="100" step="0.01" bind:value={withholdingTaxPercent} isDisabled={isReadOnly} />
                                </div>
                            {/if}
                        </div>
                        <div class="rounded-lg border border-border-light p-3">
                            <label class="flex items-center justify-between gap-3">
                                <span class="text-sm font-medium text-brand-deep">Cassa previdenziale</span>
                                <Switch bind:checked={fundEnabled} disabled={isReadOnly} />
                            </label>
                            {#if fundEnabled}
                                <div class="mt-3 grid grid-cols-1 gap-2">
                                    <label class="text-xs text-brand-secondary/80" for="fund-type">Tipo cassa</label>
                                    <Select id="fund-type" useNative class="rounded-lg border border-brand-secondary/20 px-2 py-1.5 text-sm form-focus bg-white" bind:value={fundType} isDisabled={isReadOnly}>
                                        <option value="">Seleziona...</option>
                                        {#each (formData.fund_types ?? []) as type}
                                            <option value={type.id}>{type.name}</option>
                                        {/each}
                                    </Select>
                                    <div class="grid grid-cols-2 gap-2">
                                        <div>
                                            <label class="text-xs text-brand-secondary/80" for="fund-percent">Percentuale</label>
                                            <Input id="fund-percent" class="mt-1 w-full rounded-lg border border-brand-secondary/20 px-3 py-1.5 text-sm text-right form-focus bg-white" type="number" min="0" max="100" step="0.01" bind:value={fundPercent} isDisabled={isReadOnly} />
                                        </div>
                                        <div>
                                            <label class="text-xs text-brand-secondary/80" for="fund-vat-rate">IVA cassa</label>
                                            <Select id="fund-vat-rate" useNative class="mt-1 w-full rounded-lg border border-brand-secondary/20 px-2 py-1.5 text-sm form-focus bg-white" bind:value={fundVatRate} isDisabled={isReadOnly}>
                                                <option value="">Seleziona...</option>
                                                {#each (formData.vat_rates ?? []) as rate}
                                                    <option value={rate.id}>{rate.name}</option>
                                                {/each}
                                            </Select>
                                        </div>
                                    </div>
                                    <label class="flex items-center justify-between gap-2">
                                        <span class="text-xs text-brand-secondary/80">Contributo deducibile</span>
                                        <Switch bind:checked={fundHasDeduction} disabled={isReadOnly} />
                                    </label>
                                </div>
                            {/if}
                        </div>
                        <div class="rounded-lg border border-border-light p-3">
                            <label class="flex items-center justify-between gap-3">
                                <span class="text-sm font-medium text-brand-deep">Marca da bollo</span>
                                <Switch bind:checked={stampDutyApplied} disabled={isReadOnly} />
                            </label>
                        </div>
                        <div class="rounded-lg border border-border-light p-3">
                            {#if !isRf19}
                                <label class="flex items-center justify-between gap-3">
                                    <span class="text-sm font-medium text-brand-deep">Split payment</span>
                                    <Switch bind:checked={splitPayment} disabled={isReadOnly} />
                                </label>
                            {/if}
                        </div>
                        <div class="rounded-lg border border-border-light p-3">
                            <label class="block">
                                <span class="text-sm font-medium text-brand-deep">Esigibilità IVA</span>
                                <Select useNative class="mt-1 w-full rounded-lg border border-brand-secondary/20 px-2 py-1.5 text-sm form-focus bg-white" bind:value={vatPayability} disabled={isReadOnly || splitPayment || isRf19}>
                                    {#each (formData.vat_payability_options ?? formData.vatPayabilityOptions ?? []) as option}
                                        <option value={option.value ?? option.id}>{option.label ?? option.name}</option>
                                    {/each}
                                </Select>
                            </label>
                            {#if !isRf19 && splitPayment}
                                <p class="mt-1 text-xs text-brand-secondary/70">Con split payment attivo viene impostata automaticamente su "S".</p>
                            {/if}
                            {#if displayErrors.vat_payability}
                                <span class="text-red-600 text-xs mt-0.5 block">{displayErrors.vat_payability}</span>
                            {/if}
                        </div>
                    </div>
                </div>
            {/if}
        </div>
    </div>

    <div class="fixed inset-x-0 bottom-0 z-30 border-t border-border bg-white/95 p-3 backdrop-blur sm:hidden">
        <div class="flex items-center gap-2">
            <a href={indexPath} class="btn-outline text-sm text-center flex-1">Indietro</a>
            {#if !isReadOnly}
                <Button class="btn-brand text-sm flex-1" onclick={handleSubmit} disabled={form.processing}>
                    {form.processing ? 'Salvataggio...' : (isEdit ? updateLabel : createLabel)}
                </Button>
            {/if}
        </div>
    </div>
</div>
