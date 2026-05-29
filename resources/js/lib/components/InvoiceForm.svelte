<script>
    import { tick } from 'svelte'
    import { useForm } from '@inertiajs/svelte'
    import { showToast } from '$lib/toast.js'
    import DatePicker from '$lib/components/ui/DatePicker.svelte'
    import Button from '$lib/components/ui/Button.svelte'
    import Input from '$lib/components/ui/Input.svelte'
    import Select from '$lib/components/ui/Select.svelte'
    import Textarea from '$lib/components/ui/Textarea.svelte'
    import Switch from '$lib/components/ui/Switch.svelte'

    let {
        // Props from server
        formData = {},
        // For edit mode: existing invoice with lines
        invoice = null,
        // For edit mode: whether invoice is read-only
        isReadOnly = false,
        errors = {},
    } = $props()

    const isEdit = invoice !== null
    let apiErrors = $state({})
    let isSubmittingCreate = $state(false)
    let activeDocumentTab = $state('dati')
    let tabContentHeight = $state('auto')
    let tabPanelElement

    // ─── VAT rate lookup helpers ───────────────────────────────────────────
    const vatRateMap = {}
    formData.vat_rates?.forEach(r => { vatRateMap[r.id] = r })

    function vatPercent(rateId) {
        const rate = vatRateMap[rateId]
        if (!rate) return 0
        // Parse percentage from the rate name (e.g., "22% - ...")
        const match = rate.name.match(/^(\d+)%/)
        return match ? parseInt(match[1], 10) : 0
    }

    // ─── Initialize lines ──────────────────────────────────────────────────
    function emptyLine() {
        return {
            description: '',
            quantity: 1,
            unit_of_measure: '',
            unit_price: 0,
            details_enabled: false,
            discount_enabled: false,
            discount_percent: null,
            vat_rate: 'R22',
        }
    }

    let lines = $state(
        invoice?.lines?.map(l => ({
            id: l.id,
            description: l.description,
            quantity: Number(l.quantity),
            unit_of_measure: l.unit_of_measure ?? '',
            unit_price: (l.unit_price || 0) / 100,
            details_enabled: Number(l.quantity) !== 1 || (l.unit_of_measure ?? '') !== '' || (l.discount_percent !== null && l.discount_percent !== ''),
            discount_enabled: l.discount_percent !== null && l.discount_percent !== '',
            discount_percent: l.discount_percent ? Number(l.discount_percent) : null,
            vat_rate: l.vat_rate,
        })) ?? [emptyLine()]
    )

    function addLine() {
        lines = [...lines, emptyLine()]
    }

    function removeLine(index) {
        if (lines.length <= 1) return
        lines = lines.filter((_, i) => i !== index)
    }

    // ─── Line total calculation ────────────────────────────────────────────
    function lineDiscountedTotal(line) {
        const gross = (Number(line.quantity) || 0) * (Number(line.unit_price) || 0)
        const discount = line.discount_enabled && line.discount_percent ? Number(line.discount_percent) : 0
        return discount > 0 ? gross * (1 - discount / 100) : gross
    }

    // ─── Totals ────────────────────────────────────────────────────────────
    let totalNet = $derived(
        lines.reduce((sum, l) => sum + lineDiscountedTotal(l), 0)
    )

    let fundAmount = $derived.by(() => {
        if (!fundEnabled || !fundPercent) return 0
        return Math.round(totalNet * (Number(fundPercent) / 100) * 100) / 100
    })

    let fundVatAmount = $derived.by(() => {
        if (fundAmount <= 0 || !fundVatRate) return 0
        const pct = vatPercent(fundVatRate)
        return Math.round(fundAmount * (pct / 100) * 100) / 100
    })

    let totalVat = $derived(
        lines.reduce((sum, l) => {
            const pct = vatPercent(l.vat_rate)
            const lineTotal = lineDiscountedTotal(l)
            return sum + Math.round(lineTotal * (pct / 100) * 100) / 100
        }, 0) + fundVatAmount
    )

    let totalGross = $derived(totalNet + fundAmount + totalVat)

    let totalDue = $derived.by(() => {
        let t = totalGross
        if (stampDutyApplied) t += 2
        return t
    })

    let withholdingTaxAmount = $derived.by(() => {
        if (!withholdingTaxEnabled || !withholdingTaxPercent) return 0
        return Math.round(totalNet * (Number(withholdingTaxPercent) / 100) * 100) / 100
    })

    let netDue = $derived.by(() => {
        let due = totalDue - withholdingTaxAmount
        if (splitPayment) {
            const lineVat = totalVat - fundVatAmount
            due -= lineVat
        }
        return due
    })

    // ─── Tax options ───────────────────────────────────────────────────────
    let settings = $state(formData.settings ?? {})

    let withholdingTaxEnabled = $state(settings.withholding_tax_enabled ?? false)
    let withholdingTaxPercent = $state(settings.withholding_tax_percent ?? '20.00')

    let fundEnabled = $state(settings.fund_enabled ?? false)
    let fundType = $state(settings.fund_type ?? null)
    let fundPercent = $state(settings.fund_percent ?? '4.00')
    let fundVatRate = $state(settings.fund_vat_rate ?? null)
    let fundHasDeduction = $state(settings.fund_has_deduction ?? false)

    let stampDutyApplied = $state(settings.auto_stamp_duty ?? false)
    let splitPayment = $state(settings.default_split_payment ?? false)

    // ─── Form ──────────────────────────────────────────────────────────────
    const form = useForm({
        contact_id: invoice?.contact_id ?? '',
        sequence_id: invoice?.sequence_id ?? (formData.default_sequence_id ?? ''),
        date: invoice?.date ?? new Date().toISOString().split('T')[0],
        due_date: invoice?.due_date ?? '',
        document_type: invoice?.document_type ?? 'TD01',
        notes: invoice?.notes ?? (settings.default_notes ?? ''),
        withholding_tax_enabled: withholdingTaxEnabled,
        withholding_tax_percent: withholdingTaxPercent,
        fund_enabled: fundEnabled,
        fund_type: fundType ?? '',
        fund_percent: fundPercent,
        fund_vat_rate: fundVatRate ?? '',
        fund_has_deduction: fundHasDeduction,
        stamp_duty_applied: stampDutyApplied,
        payment_method: invoice?.payment_method ?? (settings.default_payment_method ?? ''),
        payment_terms: invoice?.payment_terms ?? (settings.default_payment_terms ?? ''),
        bank_name: invoice?.bank_name ?? (settings.default_bank_name ?? ''),
        bank_iban: invoice?.bank_iban ?? (settings.default_bank_iban ?? ''),
        vat_payability: invoice?.vat_payability ?? (settings.default_vat_payability ?? 'I'),
        split_payment: invoice?.split_payment ?? splitPayment,
        lines: lines,
    })

    // Next number preview from the selected sequence
    let numberPreview = $derived.by(() => {
        const seq = formData.sequences?.find(s => s.id === Number(form.sequence_id))
        return seq?.next_number ?? ''
    })
    let sequenceLabel = $derived.by(() => {
        const seq = formData.sequences?.find(s => s.id === Number(form.sequence_id))
        return seq?.name ?? 'sequenza predefinita'
    })

    let displayErrors = $derived({ ...errors, ...apiErrors })

    function getCsrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? ''
    }

    async function submitCreateViaApi(payload) {
        isSubmittingCreate = true
        apiErrors = {}

        try {
            const response = await fetch('/api/v1/sales-invoices', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                },
                credentials: 'same-origin',
                body: JSON.stringify(payload),
            })

            const data = await response.json()

            if (response.ok) {
                showToast(data.message ?? 'Fattura creata.')
                window.location.href = data.redirect ?? '/sell-invoices'
                return
            }

            if (response.status === 422 && data?.errors) {
                apiErrors = data.errors
                showToast('Controlla i campi evidenziati.')
                return
            }

            showToast(data?.message ?? 'Errore durante il salvataggio.')
        } catch {
            showToast('Errore di rete durante il salvataggio.')
        } finally {
            isSubmittingCreate = false
        }
    }

    function handleSubmit() {
        // Sync reactive state into form data
        form.withholding_tax_enabled = withholdingTaxEnabled
        form.withholding_tax_percent = withholdingTaxPercent
        form.fund_enabled = fundEnabled
        form.fund_type = fundType ?? ''
        form.fund_percent = fundPercent
        form.fund_vat_rate = fundVatRate ?? ''
        form.fund_has_deduction = fundHasDeduction
        form.stamp_duty_applied = stampDutyApplied
        form.split_payment = splitPayment
        form.lines = lines.map((line) => ({
            description: line.description,
            quantity: line.quantity,
            unit_of_measure: line.unit_of_measure,
            unit_price: line.unit_price,
            discount_percent: line.discount_enabled ? line.discount_percent : null,
            vat_rate: line.vat_rate,
        }))

        if (isEdit) {
            form.put(`/sell-invoices/${invoice.id}`, {
                preserveScroll: true,
                onSuccess: () => showToast('Fattura aggiornata.'),
            })
        } else {
            submitCreateViaApi({
                contact_id: form.contact_id,
                sequence_id: form.sequence_id,
                date: form.date,
                due_date: form.due_date || null,
                document_type: form.document_type,
                notes: form.notes || null,
                withholding_tax_enabled: form.withholding_tax_enabled,
                withholding_tax_percent: form.withholding_tax_percent || null,
                fund_enabled: form.fund_enabled,
                fund_type: form.fund_type || null,
                fund_percent: form.fund_percent || null,
                fund_vat_rate: form.fund_vat_rate || null,
                fund_has_deduction: form.fund_has_deduction,
                stamp_duty_applied: form.stamp_duty_applied,
                payment_method: form.payment_method || null,
                payment_terms: form.payment_terms || null,
                bank_name: form.bank_name || null,
                bank_iban: form.bank_iban || null,
                vat_payability: form.vat_payability,
                split_payment: form.split_payment,
                lines: form.lines.map((line) => ({
                    description: line.description,
                    quantity: line.quantity,
                    unit_of_measure: line.unit_of_measure,
                    unit_price: line.unit_price,
                    discount_percent: line.discount_percent,
                    vat_rate: line.vat_rate,
                })),
            })
        }
    }

    function formatCurrency(value) {
        return new Intl.NumberFormat('it-IT', { style: 'currency', currency: 'EUR', minimumFractionDigits: 2 }).format(value)
    }

    async function syncTabContentHeight() {
        await tick()
        if (!tabPanelElement) return
        tabContentHeight = `${tabPanelElement.scrollHeight}px`
    }

    function setActiveDocumentTab(tabName) {
        activeDocumentTab = tabName
        syncTabContentHeight()
    }

    $effect(() => {
        activeDocumentTab
        syncTabContentHeight()
    })
</script>

<div class="p-4 pb-28 sm:p-6 sm:pb-6 w-full max-w-7xl mx-auto">
    <div class="hidden sm:flex justify-end items-center gap-2 mb-4">
        <a href="/sell-invoices" class="btn-outline text-sm">Annulla</a>
        {#if !isReadOnly}
            <Button
                class="btn-brand text-sm"
                onclick={handleSubmit}
                disabled={isEdit ? form.processing : isSubmittingCreate}
            >
                {(isEdit ? form.processing : isSubmittingCreate) ? 'Salvataggio...' : (isEdit ? 'Aggiorna fattura' : 'Crea fattura')}
            </Button>
        {/if}
    </div>

    {#if isReadOnly}
        <div class="mb-6 bg-brand-accent/15 border border-brand-accent/25 rounded-xl p-4 text-sm text-brand-deep">
            Questa fattura non è più modificabile.
        </div>
    {/if}

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main form area -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Header fields -->
            <div class="card-brand p-6">
                <div class="mb-4">
                    <h3 class="text-base font-semibold text-brand-deep">Dati documento</h3>
                    <p class="text-xs text-brand-secondary/80 mt-1">Informazioni principali e opzionali in un unico punto.</p>
                </div>

                <div class="mb-4 grid grid-cols-3 gap-2">
                    <button
                        type="button"
                        class="rounded-lg border px-3 py-2 text-sm font-medium transition-colors {activeDocumentTab === 'dati' ? 'border-brand-deep bg-brand-deep text-white' : 'border-border-light bg-white text-brand-deep hover:bg-surface-muted'}"
                        onclick={() => setActiveDocumentTab('dati')}
                    >
                        Dati
                    </button>
                    <button
                        type="button"
                        class="rounded-lg border px-3 py-2 text-sm font-medium transition-colors {activeDocumentTab === 'pagamento' ? 'border-brand-deep bg-brand-deep text-white' : 'border-border-light bg-white text-brand-deep hover:bg-surface-muted'}"
                        onclick={() => setActiveDocumentTab('pagamento')}
                    >
                        Dettagli pagamento
                    </button>
                    <button
                        type="button"
                        class="rounded-lg border px-3 py-2 text-sm font-medium transition-colors {activeDocumentTab === 'note' ? 'border-brand-deep bg-brand-deep text-white' : 'border-border-light bg-white text-brand-deep hover:bg-surface-muted'}"
                        onclick={() => setActiveDocumentTab('note')}
                    >
                        Note
                    </button>
                </div>

                <div class="overflow-hidden transition-[height] duration-200 ease-out" style={`height: ${tabContentHeight};`}>
                <div bind:this={tabPanelElement}>
                {#if activeDocumentTab === 'dati'}
                    <div class="grid grid-cols-2 gap-4">
                    <label class="block col-span-2 sm:col-span-1">
                        <span class="text-sm font-medium text-brand-deep">Cliente *</span>
                        <Select useNative
                            class="mt-1 block w-full rounded-lg border border-brand-secondary/20 px-3 py-2 text-sm form-focus bg-white"
                            bind:value={form.contact_id}
                            disabled={isReadOnly}
                        >
                            <option value="">Seleziona cliente...</option>
                            {#each formData.contacts as contact}
                                <option value={contact.id}>{contact.name}</option>
                            {/each}
                        </Select>
                        {#if displayErrors.contact_id}
                                                    <span class="text-red-600 text-xs mt-0.5 block" role="alert">{displayErrors.contact_id}</span>
                                                {/if}
                    </label>

                    {#if !isEdit}
                        <label class="block col-span-2 sm:col-span-1">
                            <span class="text-sm font-medium text-brand-deep">Numero</span>
                            <div class="mt-1 block w-full rounded-lg border border-brand-secondary/10 bg-brand-bg px-3 py-2 text-sm font-semibold text-brand-deep">
                                {numberPreview || '—'}
                            </div>
                            <span class="text-xs text-brand-secondary/60 mt-0.5 block">Assegnato automaticamente con la sequenza predefinita: {sequenceLabel}</span>
                        </label>
                    {:else}
                        <label class="block col-span-2 sm:col-span-1">
                            <span class="text-sm font-medium text-brand-deep">Numero</span>
                            <div class="mt-1 block w-full rounded-lg border border-brand-secondary/10 bg-brand-bg px-3 py-2 text-sm font-semibold text-brand-deep">
                                {invoice.number}
                            </div>
                            <span class="text-xs text-brand-secondary/60 mt-0.5 block">Sequenza applicata: {sequenceLabel}</span>
                        </label>
                    {/if}

                    <label class="block col-span-2 sm:col-span-1">
                        <span class="text-sm font-medium text-brand-deep">Data *</span>
                        <DatePicker
                            class="w-full"
                            bind:value={form.date}
                            disabled={isReadOnly}
                        />
                        {#if displayErrors.date}
                                                    <span class="text-red-600 text-xs mt-0.5 block" role="alert">{displayErrors.date}</span>
                                                {/if}
                    </label>

                    <label class="block col-span-2 sm:col-span-1">
                        <span class="text-sm font-medium text-brand-deep">Scadenza</span>
                        <DatePicker
                            class="w-full"
                            bind:value={form.due_date}
                            disabled={isReadOnly}
                        />
                    </label>

                    <label class="block col-span-2 sm:col-span-1">
                        <span class="text-sm font-medium text-brand-deep">Tipo documento</span>
                        <Select useNative
                            class="mt-1 block w-full rounded-lg border border-brand-secondary/20 px-3 py-2 text-sm form-focus bg-white"
                            bind:value={form.document_type}
                            disabled={isReadOnly}
                        >
                            <option value="TD01">TD01 - Fattura</option>
                            <option value="TD02">TD02 - Acconto su fattura</option>
                            <option value="TD03">TD03 - Acconto su parcella</option>
                            <option value="TD06">TD06 - Parcella</option>
                            <option value="TD24">TD24 - Fattura differita</option>
                            <option value="TD25">TD25 - Fattura differita (triangolazione)</option>
                        </Select>
                    </label>
                    </div>
                {:else if activeDocumentTab === 'pagamento'}
                    <div class="grid grid-cols-2 gap-4">
                        <label class="block col-span-2 sm:col-span-1">
                            <span class="text-sm font-medium text-brand-deep">Metodo</span>
                            <Select useNative
                                class="mt-1 block w-full rounded-lg border border-brand-secondary/20 px-3 py-2 text-sm form-focus bg-white"
                                bind:value={form.payment_method}
                                disabled={isReadOnly}
                            >
                                <option value="">—</option>
                                {#each formData.payment_methods as method}
                                    <option value={method.id}>{method.name}</option>
                                {/each}
                            </Select>
                        </label>

                        <label class="block col-span-2 sm:col-span-1">
                            <span class="text-sm font-medium text-brand-deep">Termini</span>
                            <Select useNative
                                class="mt-1 block w-full rounded-lg border border-brand-secondary/20 px-3 py-2 text-sm form-focus bg-white"
                                bind:value={form.payment_terms}
                                disabled={isReadOnly}
                            >
                                <option value="">—</option>
                                {#each formData.payment_terms as term}
                                    <option value={term.id}>{term.name}</option>
                                {/each}
                            </Select>
                        </label>

                        <label class="block col-span-2 sm:col-span-1">
                            <span class="text-sm font-medium text-brand-deep">Banca</span>
                            <Input
                                class="mt-1 block w-full rounded-lg border border-brand-secondary/20 px-3 py-2 text-sm form-focus"
                                type="text"
                                bind:value={form.bank_name}
                                disabled={isReadOnly}
                            />
                        </label>

                        <label class="block col-span-2 sm:col-span-1">
                            <span class="text-sm font-medium text-brand-deep">IBAN</span>
                            <Input
                                class="mt-1 block w-full rounded-lg border border-brand-secondary/20 px-3 py-2 text-sm form-focus"
                                type="text"
                                bind:value={form.bank_iban}
                                disabled={isReadOnly}
                            />
                        </label>
                    </div>
                {:else}
                    <div>
                        <label class="block text-sm font-medium text-brand-deep mb-1">Note</label>
                        <Textarea
                            class="block w-full rounded-lg border border-brand-secondary/20 px-3 py-2 text-sm form-focus"
                            rows="4"
                            bind:value={form.notes}
                            disabled={isReadOnly}
                        ></Textarea>
                    </div>
                {/if}
                </div>
                </div>
            </div>

            <!-- Invoice lines -->
            <div class="card-brand p-6">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h2 class="text-base font-semibold text-brand-deep">Righe fattura</h2>
                        <p class="text-xs text-brand-secondary/80 mt-1">Inserisci descrizione, quantità, prezzo, sconto e IVA per ogni riga.</p>
                    </div>
                    {#if !isReadOnly}
                        <Button
                            class="btn-outline text-sm"
                            onclick={addLine}
                        >
                            Aggiungi riga
                        </Button>
                    {/if}
                </div>

                <div class="space-y-3">
                    {#each lines as line, index}
                        <div class="p-3 bg-brand-bg rounded-lg border border-border-light">
                            <div class="flex items-start justify-between gap-2 mb-3">
                                <p class="text-xs uppercase tracking-wide text-brand-secondary/70">Riga {index + 1}</p>
                                {#if !isReadOnly && lines.length > 1}
                                    <Button
                                        class="text-brand-secondary/50 hover:text-red-600 transition-colors text-xs"
                                        onclick={() => removeLine(index)}
                                    >
                                        Rimuovi
                                    </Button>
                                {/if}
                            </div>

                            <div class="mt-3 grid grid-cols-1 sm:grid-cols-[1fr_12rem] gap-2 items-end">
                                <div>
                                    <label class="text-xs text-brand-secondary/80">Descrizione</label>
                                    <Input
                                        class="mt-1 w-full rounded border border-brand-secondary/20 px-2 py-1.5 text-sm form-focus bg-white tabular-nums"
                                        type="text"
                                        placeholder="Descrizione"
                                        bind:value={line.description}
                                        disabled={isReadOnly}
                                    />
                                </div>
                                <div>
                                    <label class="text-xs text-brand-secondary/80">Totale riga</label>
                                    <div class="mt-1 h-[34px] rounded border border-brand-secondary/10 bg-white px-2 py-1.5 text-sm font-semibold text-brand-deep tabular-nums text-right">
                                        {formatCurrency(lineDiscountedTotal(line))}
                                    </div>
                                </div>
                            </div>

                            <div class="mt-2 grid grid-cols-1 sm:grid-cols-[12rem_1fr_10rem] gap-2 items-end">
                                <div>
                                    <label class="text-xs text-brand-secondary/80">Importo</label>
                                    <Input
                                        class="mt-1 w-full rounded border border-brand-secondary/20 px-2 py-1.5 text-sm text-right form-focus bg-white tabular-nums"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        placeholder="0,00"
                                        bind:value={line.unit_price}
                                        disabled={isReadOnly}
                                    />
                                </div>
                                <div>
                                    <label class="text-xs text-brand-secondary/80">IVA</label>
                                    <Select useNative
                                        class="mt-1 w-full rounded border border-brand-secondary/20 px-2 py-1.5 text-sm form-focus bg-white"
                                        bind:value={line.vat_rate}
                                        disabled={isReadOnly}
                                    >
                                        {#each formData.vat_rates as rate}
                                            <option value={rate.id}>{rate.name}</option>
                                        {/each}
                                    </Select>
                                </div>
                                <div class="flex items-end">
                                    <Button
                                        class="btn-outline text-xs w-full"
                                        onclick={() => line.details_enabled = !line.details_enabled}
                                        disabled={isReadOnly}
                                    >
                                        {line.details_enabled ? 'Nascondi dettagli' : 'Dettagli'}
                                    </Button>
                                </div>
                            </div>

                            {#if line.details_enabled}
                                <div class="mt-3 rounded-lg border border-border-light bg-white p-3">
                                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                                        <div>
                                            <label class="text-xs text-brand-secondary/80">Quantità</label>
                                            <Input
                                                class="mt-1 w-full rounded border border-brand-secondary/20 px-2 py-1.5 text-sm form-focus bg-white tabular-nums"
                                                type="number"
                                                min="0.01"
                                                step="0.01"
                                                bind:value={line.quantity}
                                                disabled={isReadOnly}
                                            />
                                        </div>
                                        <div>
                                            <label class="text-xs text-brand-secondary/80">UM</label>
                                            <Input
                                                class="mt-1 w-full rounded border border-brand-secondary/20 px-2 py-1.5 text-sm form-focus bg-white"
                                                type="text"
                                                placeholder="UM"
                                                bind:value={line.unit_of_measure}
                                                disabled={isReadOnly}
                                            />
                                        </div>
                                        <div>
                                            <div class="flex items-center justify-between">
                                                <label class="text-xs text-brand-secondary/80">Sconto %</label>
                                                <Switch
                                                    bind:checked={line.discount_enabled}
                                                    disabled={isReadOnly}
                                                    class="scale-75 origin-right"
                                                />
                                            </div>
                                            <Input
                                                class="mt-1 w-full rounded border border-brand-secondary/20 px-2 py-1.5 text-sm text-right form-focus bg-white tabular-nums disabled:bg-slate-100"
                                                type="number"
                                                min="0"
                                                max="100"
                                                step="0.01"
                                                placeholder="0"
                                                bind:value={line.discount_percent}
                                                disabled={isReadOnly || !line.discount_enabled}
                                            />
                                        </div>
                                        <div class="flex items-end">
                                            <p class="text-xs text-brand-secondary/80">Modifica quantità, UM o sconto solo quando serve.</p>
                                        </div>
                                    </div>
                                </div>
                            {/if}
                        </div>
                    {/each}
                </div>
                {#if displayErrors.lines}
                                    <span class="text-red-600 text-xs mt-2 block" role="alert">{displayErrors.lines}</span>
                                {/if}
            </div>

        </div>

        <!-- Sidebar: totals -->
        <div class="lg:col-span-1">
            <div class="space-y-6 sticky top-24">
                <div class="card-brand p-6">
                <h2 class="text-base font-semibold text-brand-deep mb-4">Riepilogo</h2>

                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-brand-secondary/60">Totale netto</span>
                        <span class="font-semibold tabular-nums">{formatCurrency(totalNet)}</span>
                    </div>

                    {#if fundEnabled}
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

                    <div class="flex justify-between">
                        <span class="text-brand-deep font-medium">Totale lordo</span>
                        <span class="font-bold tabular-nums text-brand-deep">{formatCurrency(totalGross)}</span>
                    </div>

                    {#if stampDutyApplied}
                        <div class="flex justify-between">
                            <span class="text-brand-secondary/60">Marca da bollo</span>
                            <span class="font-semibold tabular-nums">€2,00</span>
                        </div>
                    {/if}

                    {#if withholdingTaxEnabled}
                        <div class="flex justify-between">
                            <span class="text-brand-secondary/60">Rit. acconto ({withholdingTaxPercent}%)</span>
                            <span class="font-semibold tabular-nums text-red-600">-{formatCurrency(withholdingTaxAmount)}</span>
                        </div>
                    {/if}

                    {#if splitPayment}
                        <div class="flex justify-between">
                            <span class="text-brand-secondary/60">IVA split payment</span>
                            <span class="font-semibold tabular-nums text-red-600">-{formatCurrency(totalVat - fundVatAmount)}</span>
                        </div>
                    {/if}

                    <hr class="border-brand-secondary/10" />

                    <div class="flex justify-between text-base">
                        <span class="text-brand-deep font-semibold">Netto a pagare</span>
                        <span class="font-bold tabular-nums text-brand-deep">{formatCurrency(netDue)}</span>
                    </div>
                </div>
                </div>

                <div class="card-brand p-6">
                    <h2 class="text-base font-semibold text-brand-deep mb-1">Opzioni fiscali</h2>
                    <p class="text-xs text-brand-secondary/80 mb-4">Sempre visibili durante la compilazione delle righe.</p>

                    <div class="space-y-3">
                        <div class="rounded-lg border border-border-light p-3">
                            <label class="flex items-center justify-between gap-3">
                                <span class="text-sm font-medium text-brand-deep">Ritenuta d'acconto</span>
                                <Switch bind:checked={withholdingTaxEnabled} disabled={isReadOnly} />
                            </label>
                            {#if withholdingTaxEnabled}
                                <div class="mt-3 grid grid-cols-[5rem_1fr] items-center gap-2">
                                    <span class="text-xs text-brand-secondary/80">Percentuale</span>
                                    <div class="relative w-28 justify-self-start">
                                        <Input
                                            id="withholding-percent"
                                            class="w-full rounded-lg border border-brand-secondary/20 pr-8 px-3 py-1.5 text-sm text-right form-focus bg-white"
                                            type="number"
                                            min="0"
                                            max="100"
                                            step="0.01"
                                            bind:value={withholdingTaxPercent}
                                            disabled={isReadOnly}
                                        />
                                        <span class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-xs text-brand-secondary/70">%</span>
                                    </div>
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
                                    <label for="fund-type" class="text-xs text-brand-secondary/80">Tipo cassa</label>
                                    <Select useNative
                                        id="fund-type"
                                        class="rounded-lg border border-brand-secondary/20 px-2 py-1.5 text-sm form-focus bg-white"
                                        bind:value={fundType}
                                        disabled={isReadOnly}
                                    >
                                        <option value="">Seleziona cassa...</option>
                                        {#each (formData.fund_types ?? []) as type}
                                            <option value={type.id}>{type.name}</option>
                                        {/each}
                                    </Select>

                                    <div class="grid grid-cols-2 gap-2">
                                        <div>
                                            <label for="fund-percent" class="text-xs text-brand-secondary/80">Percentuale</label>
                                            <div class="relative mt-1">
                                                <Input
                                                    id="fund-percent"
                                                    class="w-full rounded-lg border border-brand-secondary/20 pr-8 px-3 py-1.5 text-sm text-right form-focus bg-white"
                                                    type="number"
                                                    min="0"
                                                    max="100"
                                                    step="0.01"
                                                    bind:value={fundPercent}
                                                    disabled={isReadOnly}
                                                />
                                                <span class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-xs text-brand-secondary/70">%</span>
                                            </div>
                                        </div>
                                        <div>
                                            <label for="fund-vat-rate" class="text-xs text-brand-secondary/80">IVA cassa</label>
                                            <Select useNative
                                                id="fund-vat-rate"
                                                class="mt-1 rounded-lg border border-brand-secondary/20 px-2 py-1.5 text-sm form-focus bg-white"
                                                bind:value={fundVatRate}
                                                disabled={isReadOnly}
                                            >
                                                <option value="">Seleziona IVA...</option>
                                                <option value="R22">22%</option>
                                                <option value="R10">10%</option>
                                                <option value="R5">5%</option>
                                                <option value="R4">4%</option>
                                                <option value="N1">0% N1</option>
                                            </Select>
                                        </div>
                                    </div>
                                </div>
                            {/if}
                        </div>

                        <div class="rounded-lg border border-border-light p-3">
                            <label class="flex items-center justify-between gap-3">
                                <span class="text-sm font-medium text-brand-deep">Marca da bollo (€2,00)</span>
                                <Switch bind:checked={stampDutyApplied} disabled={isReadOnly} />
                            </label>
                        </div>

                        <div class="rounded-lg border border-border-light p-3">
                            <label class="flex items-center justify-between gap-3">
                                <span class="text-sm font-medium text-brand-deep">Split payment</span>
                                <Switch bind:checked={splitPayment} disabled={isReadOnly} />
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="fixed inset-x-0 bottom-0 z-30 border-t border-border bg-white/95 p-3 backdrop-blur sm:hidden">
        <div class="flex items-center gap-2">
            <a href="/sell-invoices" class="btn-outline text-sm text-center flex-1">Annulla</a>
            {#if !isReadOnly}
                <Button
                    class="btn-brand text-sm flex-1"
                    onclick={handleSubmit}
                    disabled={isEdit ? form.processing : isSubmittingCreate}
                >
                    {(isEdit ? form.processing : isSubmittingCreate) ? 'Salvataggio...' : (isEdit ? 'Aggiorna' : 'Crea')}
                </Button>
            {/if}
        </div>
    </div>
</div>
