<script>
    import Authenticated from '$layouts/Authenticated.svelte'
    import { useForm } from '@inertiajs/svelte'
    import Button from '$lib/components/ui/Button.svelte'
    import Input from '$lib/components/ui/Input.svelte'
    import Select from '$lib/components/ui/Select.svelte'
    import Textarea from '$lib/components/ui/Textarea.svelte'
    import Switch from '$lib/components/ui/Switch.svelte'
    import { showToast } from '$lib/toast.js'

    let { settings = {}, sequences = [], vatRates = [], paymentMethods = [], paymentTerms = [], fundTypes = [], vatPayabilityOptions = [], fiscalRegime = 'RF01', errors = {} } = $props()
    const isRf19 = $derived(fiscalRegime === 'RF19')

    let withholdingTaxEnabled = $state(settings.withholding_tax_enabled ?? false)
    let fundEnabled = $state(settings.fund_enabled ?? false)
    let autoStampDuty = $state(settings.auto_stamp_duty ?? false)
    let splitPayment = $state(settings.default_split_payment ?? false)
    const form = useForm({
        default_sequence_sales: settings.default_sequence_sales ?? '',
        default_vat_rate: settings.default_vat_rate?.value ?? settings.default_vat_rate ?? '',
        withholding_tax_enabled: withholdingTaxEnabled,
        withholding_tax_percent: settings.withholding_tax_percent ?? '20.00',
        fund_enabled: fundEnabled,
        fund_type: settings.fund_type ?? '',
        fund_percent: settings.fund_percent ?? '4.00',
        fund_vat_rate: settings.fund_vat_rate?.value ?? settings.fund_vat_rate ?? '',
        fund_has_deduction: settings.fund_has_deduction ?? false,
        auto_stamp_duty: autoStampDuty,
        stamp_duty_threshold: settings.stamp_duty_threshold ?? '77.47',
        default_payment_method: settings.default_payment_method ?? '',
        default_payment_terms: settings.default_payment_terms ?? '',
        default_bank_name: settings.default_bank_name ?? '',
        default_bank_iban: settings.default_bank_iban ?? '',
        default_vat_payability: settings.default_vat_payability ?? 'I',
        default_split_payment: splitPayment,
        default_notes: settings.default_notes ?? '',
    })

    if (isRf19) {
        withholdingTaxEnabled = false
        splitPayment = false
        form.withholding_tax_enabled = false
        form.default_split_payment = false
        form.default_vat_payability = 'I'
    }

    function handleSubmit() {
        form.withholding_tax_enabled = withholdingTaxEnabled
        form.fund_enabled = fundEnabled
        form.auto_stamp_duty = autoStampDuty
        form.default_split_payment = splitPayment
        form.put('/invoice-settings', { preserveScroll: true, onSuccess: () => showToast('Impostazioni fatture salvate.') })
    }
</script>

<Authenticated>
    {#snippet headerActions()}
        <Button class="btn-brand text-sm" onclick={handleSubmit} disabled={form.processing}>
            {form.processing ? 'Salvataggio...' : 'Salva impostazioni'}
        </Button>
    {/snippet}

    <div class="page-shell pb-24 sm:pb-6 w-full">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Predefiniti -->
            <div class="card-brand p-4 sm:p-5">
                <h2 class="text-base font-semibold text-brand-deep mb-4">Predefiniti</h2>
                <div class="space-y-4">
                    <label class="block"><span class="text-sm font-medium text-brand-deep">Sezionale vendite</span>
                        <Select useNative class="mt-1 block w-full rounded-lg border border-border-light bg-white px-3 py-2 text-sm form-focus" bind:value={form.default_sequence_sales}>
                            <option value="">—</option>
                            {#each sequences as s}<option value={s.id}>{s.name}</option>{/each}
                        </Select>
                    </label>
                    <label class="block"><span class="text-sm font-medium text-brand-deep">Aliquota IVA predefinita</span>
                        <Select useNative class="mt-1 block w-full rounded-lg border border-border-light bg-white px-3 py-2 text-sm form-focus" bind:value={form.default_vat_rate}>
                            <option value="">—</option>
                            {#each vatRates as r}<option value={r.id}>{r.name}</option>{/each}
                        </Select>
                    </label>
                </div>
            </div>

            {#if !isRf19}
                <!-- Ritenuta d'Acconto -->
                <div class="card-brand p-4 sm:p-5">
                    <h2 class="text-base font-semibold text-brand-deep mb-4">Ritenuta d'Acconto</h2>
                    <div class="space-y-4">
                        <label class="flex items-center gap-2"><Switch bind:checked={withholdingTaxEnabled} /><span class="text-sm text-brand-deep">Abilita ritenuta d'acconto</span></label>
                        {#if withholdingTaxEnabled}
                            <label class="block"><span class="text-sm font-medium text-brand-deep">Percentuale predefinita</span>
                                <div class="mt-1 flex items-center gap-1"><Input class="w-24 rounded-lg border border-border-light bg-white px-3 py-2 text-sm text-right form-focus" type="number" min="0" max="100" step="0.01" bind:value={form.withholding_tax_percent} /><span class="text-sm text-brand-secondary/60">%</span></div>
                            </label>
                        {/if}
                    </div>
                </div>
            {/if}

            <!-- Cassa Previdenziale -->
            <div class="card-brand p-4 sm:p-5">
                <h2 class="text-base font-semibold text-brand-deep mb-4">Cassa Previdenziale</h2>
                <div class="space-y-4">
                    <label class="flex items-center gap-2"><Switch bind:checked={fundEnabled} /><span class="text-sm text-brand-deep">Abilita cassa previdenziale</span></label>
                    {#if fundEnabled}
                        <label class="block"><span class="text-sm font-medium text-brand-deep">Tipo cassa</span>
                            <Select useNative class="mt-1 block w-full rounded-lg border border-border-light bg-white px-3 py-2 text-sm form-focus" bind:value={form.fund_type}>
                                <option value="">—</option>
                                {#each fundTypes as f}<option value={f.id}>{f.name}</option>{/each}
                            </Select>
                        </label>
                        <label class="block"><span class="text-sm font-medium text-brand-deep">Percentuale rivalsa</span>
                            <div class="mt-1 flex items-center gap-1"><Input class="w-24 rounded-lg border border-border-light bg-white px-3 py-2 text-sm text-right form-focus" type="number" min="0" max="100" step="0.01" bind:value={form.fund_percent} /><span class="text-sm text-brand-secondary/60">%</span></div>
                        </label>
                        <label class="block"><span class="text-sm font-medium text-brand-deep">Aliquota IVA rivalsa</span>
                            <Select useNative class="mt-1 block w-full rounded-lg border border-border-light bg-white px-3 py-2 text-sm form-focus" bind:value={form.fund_vat_rate}>
                                <option value="">—</option>
                                <option value="R22">22%</option><option value="R10">10%</option><option value="R5">5%</option><option value="R4">4%</option><option value="N1">0% N1</option>
                            </Select>
                        </label>
                        <label class="flex items-center gap-2"><Switch bind:checked={form.fund_has_deduction} /><span class="text-sm text-brand-deep">Rivalsa con deduzione</span></label>
                    {/if}
                </div>
            </div>

            <!-- Bollo Virtuale -->
            <div class="card-brand p-4 sm:p-5">
                <h2 class="text-base font-semibold text-brand-deep mb-4">Bollo Virtuale</h2>
                <div class="space-y-4">
                    <label class="flex items-center gap-2"><Switch bind:checked={autoStampDuty} /><span class="text-sm text-brand-deep">Applica automaticamente marca da bollo (€2,00)</span></label>
                    {#if autoStampDuty}
                        <label class="block"><span class="text-sm font-medium text-brand-deep">Soglia imponibile (€)</span>
                            <Input class="mt-1 block w-32 rounded-lg border border-border-light bg-white px-3 py-2 text-sm form-focus" type="number" min="0" step="0.01" bind:value={form.stamp_duty_threshold} />
                        </label>
                    {/if}
                </div>
            </div>

            <!-- Pagamenti -->
            <div class="card-brand p-4 sm:p-5">
                <h2 class="text-base font-semibold text-brand-deep mb-4">Pagamenti</h2>
                <div class="space-y-4">
                    <label class="block"><span class="text-sm font-medium text-brand-deep">Metodo predefinito</span>
                        <Select useNative class="mt-1 block w-full rounded-lg border border-border-light bg-white px-3 py-2 text-sm form-focus" bind:value={form.default_payment_method}>
                            <option value="">—</option>
                            {#each paymentMethods as m}<option value={m.id}>{m.name}</option>{/each}
                        </Select>
                    </label>
                    <label class="block"><span class="text-sm font-medium text-brand-deep">Termini predefiniti</span>
                        <Select useNative class="mt-1 block w-full rounded-lg border border-border-light bg-white px-3 py-2 text-sm form-focus" bind:value={form.default_payment_terms}>
                            <option value="">—</option>
                            {#each paymentTerms as t}<option value={t.id}>{t.name}</option>{/each}
                        </Select>
                    </label>
                    <label class="block"><span class="text-sm font-medium text-brand-deep">Banca predefinita</span>
                        <Input class="mt-1 block w-full rounded-lg border border-border-light bg-white px-3 py-2 text-sm form-focus" type="text" bind:value={form.default_bank_name} />
                    </label>
                    <label class="block"><span class="text-sm font-medium text-brand-deep">IBAN predefinito</span>
                        <Input class="mt-1 block w-full rounded-lg border border-border-light bg-white px-3 py-2 text-sm form-focus" type="text" bind:value={form.default_bank_iban} />
                    </label>
                </div>
            </div>

            <!-- IVA + Altro -->
            <div class="space-y-6">
                <div class="card-brand p-4 sm:p-5">
                    <h2 class="text-base font-semibold text-brand-deep mb-4">IVA</h2>
                    <div class="space-y-4">
                        <label class="block"><span class="text-sm font-medium text-brand-deep">Esigibilità IVA</span>
                            <Select useNative class="mt-1 block w-full rounded-lg border border-border-light bg-white px-3 py-2 text-sm form-focus" bind:value={form.default_vat_payability} disabled={isRf19}>
                                {#each vatPayabilityOptions as o}<option value={o.id}>{o.name}</option>{/each}
                            </Select>
                        </label>
                        {#if !isRf19}
                            <label class="flex items-center gap-2"><Switch bind:checked={splitPayment} /><span class="text-sm text-brand-deep">Split payment predefinito</span></label>
                        {/if}
                    </div>
                </div>

                <div class="card-brand p-4 sm:p-5">
                    <h2 class="text-base font-semibold text-brand-deep mb-4">Altro</h2>
                    <label class="block"><span class="text-sm font-medium text-brand-deep">Note / Causale predefinite</span>
                        <Textarea class="mt-1 block w-full rounded-lg border border-border-light bg-white px-3 py-2 text-sm form-focus" rows="3" bind:value={form.default_notes}></Textarea>
                    </label>
                </div>
            </div>
        </div>
    </div>
</Authenticated>
