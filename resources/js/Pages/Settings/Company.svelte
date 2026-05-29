<script>
    import Authenticated from '$layouts/Authenticated.svelte'
    import { useForm } from '@inertiajs/svelte'
    import Button from '$lib/components/ui/Button.svelte'
    import Input from '$lib/components/ui/Input.svelte'
    import Select from '$lib/components/ui/Select.svelte'
    import Switch from '$lib/components/ui/Switch.svelte'
    import { showToast } from '$lib/toast.js'

    let { company = {}, atecoCodes = [], fiscalRegimes = [], countries = [], errors = {} } = $props()

    let atecoCodesState = $state(atecoCodes.map(c => ({ code: c.code, label: c.label })))
    let atecoSearchQuery = $state('')
    let atecoInputValue = $state('')
    let atecoResults = $state([])
    let atecoLoading = $state(false)
    let removeLogo = $state(false)
    let atecoTimer = null

    async function searchAteco() {
        const q = atecoSearchQuery.trim()
        if (q.length < 2) { atecoResults = []; return }
        atecoLoading = true
        const res = await fetch(`/api/v1/ateco/search?q=${encodeURIComponent(q)}`)
        if (!res.ok) {
            atecoResults = []
            atecoLoading = false
            return
        }
        const data = await res.json()
        atecoResults = data.filter(r => !atecoCodesState.some(c => c.code === r.code))
        atecoLoading = false
    }

    function onAtecoInput() {
        atecoInputValue = atecoInputValue.trim()
        atecoSearchQuery = atecoInputValue
        clearTimeout(atecoTimer)
        if (atecoInputValue.length < 2) {
            atecoResults = []
            return
        }
        atecoTimer = setTimeout(searchAteco, 200)
    }

    function selectAteco(code, label) {
        if (atecoCodesState.some((current) => current.code === code)) {
            return
        }
        atecoCodesState = [...atecoCodesState, { code, label }]
        form.company_ateco_codes = atecoCodesState.map(c => c.code)
        atecoInputValue = ''
        atecoSearchQuery = ''
        atecoResults = []
    }

    function onAtecoChange() {
        const selectedCode = atecoInputValue.trim()
        if (selectedCode === '') {
            return
        }

        const selected = atecoResults.find((result) => result.code === selectedCode)
        if (selected) {
            selectAteco(selected.code, selected.description)
            return
        }

        const fallbackLabel = atecoCodes.find((code) => code.code === selectedCode)?.label
        if (fallbackLabel) {
            selectAteco(selectedCode, fallbackLabel)
            return
        }

        atecoInputValue = ''
    }

    function removeAtecoCode(code) {
        atecoCodesState = atecoCodesState.filter(c => c.code !== code)
        form.company_ateco_codes = atecoCodesState.map(c => c.code)
    }

    function handleSubmit() {
        form.company_ateco_codes = atecoCodesState.map(c => c.code)
        form.remove_logo = removeLogo
        form.transform((data) => ({
            ...data,
            _method: 'put',
        })).post('/company-settings', {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => { removeLogo = false; showToast('Impostazioni salvate.') },
        })
    }

    const form = useForm({
        company_name: company.company_name ?? '',
        company_vat_number: company.company_vat_number ?? '',
        company_tax_code: company.company_tax_code ?? '',
        company_address: company.company_address ?? '',
        company_postal_code: company.company_postal_code ?? '',
        company_city: company.company_city ?? '',
        company_province: company.company_province ?? '',
        company_country: company.company_country ?? 'IT',
        company_email: company.company_email ?? '',
        company_pec: company.company_pec ?? '',
        company_sdi_code: company.company_sdi_code ?? '',
        company_fiscal_regime: company.company_fiscal_regime ?? 'RF01',
        rf19_self_invoices_enabled: company.rf19_self_invoices_enabled ?? false,
        company_ateco_codes: atecoCodesState.map(c => c.code),
        company_logo: null,
        remove_logo: false,
    })

    function handleLogoSelect(e) {
        form.company_logo = e.target.files[0] || null
        removeLogo = false
    }

    function handleRemoveLogo() {
        removeLogo = true
        form.company_logo = null
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
            <div class="card-brand p-4 sm:p-5">
                <h2 class="text-base font-semibold text-brand-deep mb-4">Informazioni generali</h2>
                <div class="space-y-4">
                    <label class="block"><span class="text-sm font-medium text-brand-deep">Nome azienda *</span>
                        <Input class="mt-1 block w-full rounded-lg border border-border-light bg-white px-3 py-2 text-sm form-focus" type="text" bind:value={form.company_name} />
                        {#if errors.company_name}<span class="text-red-600 text-xs mt-0.5 block">{errors.company_name}</span>{/if}
                    </label>
                    <label class="block"><span class="text-sm font-medium text-brand-deep">Partita IVA</span>
                        <Input class="mt-1 block w-full rounded-lg border border-border-light bg-white px-3 py-2 text-sm form-focus" type="text" bind:value={form.company_vat_number} />
                    </label>
                    <label class="block"><span class="text-sm font-medium text-brand-deep">Codice fiscale</span>
                        <Input class="mt-1 block w-full rounded-lg border border-border-light bg-white px-3 py-2 text-sm form-focus" type="text" bind:value={form.company_tax_code} />
                    </label>
                </div>
            </div>

            <div class="card-brand p-4 sm:p-5">
                <h2 class="text-base font-semibold text-brand-deep mb-4">Indirizzo</h2>
                <div class="space-y-4">
                    <label class="block"><span class="text-sm font-medium text-brand-deep">Via</span>
                        <Input class="mt-1 block w-full rounded-lg border border-border-light bg-white px-3 py-2 text-sm form-focus" type="text" bind:value={form.company_address} />
                    </label>
                    <div class="grid grid-cols-2 gap-4">
                        <label class="block"><span class="text-sm font-medium text-brand-deep">CAP</span>
                            <Input class="mt-1 block w-full rounded-lg border border-border-light bg-white px-3 py-2 text-sm form-focus" type="text" bind:value={form.company_postal_code} />
                        </label>
                        <label class="block"><span class="text-sm font-medium text-brand-deep">Città</span>
                            <Input class="mt-1 block w-full rounded-lg border border-border-light bg-white px-3 py-2 text-sm form-focus" type="text" bind:value={form.company_city} />
                        </label>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <label class="block"><span class="text-sm font-medium text-brand-deep">Provincia</span>
                            <Input class="mt-1 block w-full rounded-lg border border-border-light bg-white px-3 py-2 text-sm form-focus" type="text" maxlength="2" bind:value={form.company_province} />
                        </label>
                        <label class="block"><span class="text-sm font-medium text-brand-deep">Paese *</span>
                            <Select useNative class="mt-1 block w-full rounded-lg border border-border-light bg-white px-3 py-2 text-sm form-focus" bind:value={form.company_country}>
                                {#each countries as c}<option value={c.value}>{c.label}</option>{/each}
                            </Select>
                        </label>
                    </div>
                </div>
            </div>

            <div class="card-brand p-4 sm:p-5">
                <h2 class="text-base font-semibold text-brand-deep mb-4">Fatturazione elettronica</h2>
                <div class="space-y-4">
                    <label class="block"><span class="text-sm font-medium text-brand-deep">Regime fiscale *</span>
                        <Select useNative class="mt-1 block w-full rounded-lg border border-border-light bg-white px-3 py-2 text-sm form-focus" bind:value={form.company_fiscal_regime}>
                            {#each fiscalRegimes as r}<option value={r.value}>{r.label}</option>{/each}
                        </Select>
                    </label>
                    <label class="block"><span class="text-sm font-medium text-brand-deep">Email</span>
                        <Input class="mt-1 block w-full rounded-lg border border-border-light bg-white px-3 py-2 text-sm form-focus" type="email" bind:value={form.company_email} />
                    </label>
                    <label class="block"><span class="text-sm font-medium text-brand-deep">PEC</span>
                        <Input class="mt-1 block w-full rounded-lg border border-border-light bg-white px-3 py-2 text-sm form-focus" type="text" bind:value={form.company_pec} />
                    </label>
                    <label class="block"><span class="text-sm font-medium text-brand-deep">Codice SDI</span>
                        <Input class="mt-1 block w-full rounded-lg border border-border-light bg-white px-3 py-2 text-sm form-focus" type="text" maxlength="7" bind:value={form.company_sdi_code} />
                    </label>
                    {#if form.company_fiscal_regime === 'RF19'}
                        <label class="flex items-center gap-2">
                            <Switch bind:checked={form.rf19_self_invoices_enabled} />
                            <span class="text-sm text-brand-deep">Abilita autofatture RF19 (solo per casi estero)</span>
                        </label>
                    {/if}
                </div>
            </div>

            <div class="space-y-6">
                <div class="card-brand p-4 sm:p-5">
                    <h2 class="text-base font-semibold text-brand-deep mb-4">Codici ATECO</h2>
                    <div class="mb-2">
                        <input
                            class="w-full rounded-lg border border-border-light bg-white px-3 py-2 text-sm form-focus"
                            type="text"
                            list="ateco-results"
                            placeholder="Cerca codice ATECO e seleziona il numero..."
                            bind:value={atecoInputValue}
                            oninput={onAtecoInput}
                            onchange={onAtecoChange}
                        />
                        <datalist id="ateco-results">
                            {#each atecoResults as result}
                                <option value={result.code} label={result.description}></option>
                            {/each}
                        </datalist>
                    </div>
                    {#if atecoLoading}
                        <p class="mb-3 text-xs text-brand-secondary/60">Caricamento codici...</p>
                    {/if}
                    <div class="mb-3">
                        <p class="text-xs text-brand-secondary/70">
                            In combobox selezioni solo il codice. Sotto trovi codice e descrizione completa.
                        </p>
                    </div>
                    <div class="space-y-2">
                        {#each atecoCodesState as c}
                            <div class="flex items-start justify-between gap-3 rounded-lg border border-border-light bg-surface-muted px-3 py-2">
                                <div class="min-w-0">
                                    <p class="text-xs font-semibold text-brand-deep">{c.code}</p>
                                    <p class="text-xs text-brand-secondary/80 break-words">{c.label}</p>
                                </div>
                                <Button class="text-xs text-brand-secondary/60 hover:text-red-600 transition-colors" onclick={() => removeAtecoCode(c.code)}>Rimuovi</Button>
                            </div>
                        {/each}
                        {#if atecoCodesState.length === 0}
                            <p class="text-xs text-brand-secondary/60">Nessun codice ATECO selezionato.</p>
                        {/if}
                    </div>
                </div>

                <div class="card-brand p-4 sm:p-5">
                    <h2 class="text-base font-semibold text-brand-deep mb-4">Logo</h2>
                    {#if company.company_logo_path && !removeLogo}
                        <div class="mb-3">
                            <img src="/storage/{company.company_logo_path}" alt="Logo" loading="lazy" class="h-16 object-contain rounded-lg border border-border-light bg-white p-2" />
                        </div>
                    {/if}
                    <div class="flex items-center gap-3">
                        <label class="btn-outline text-sm cursor-pointer">
                            {company.company_logo_path ? 'Cambia logo' : 'Carica logo'}
                            <Input type="file" accept="image/*" class="hidden" onchange={handleLogoSelect} />
                        </label>
                        {#if company.company_logo_path && !removeLogo}
                            <Button class="text-sm text-red-600 hover:text-red-700 transition-colors" onclick={handleRemoveLogo}>Rimuovi</Button>
                        {/if}
                    </div>
                    {#if form.company_logo}
                        <p class="text-xs text-brand-secondary/70 mt-2">{form.company_logo.name}</p>
                    {/if}
                </div>
            </div>
        </div>
    </div>
</Authenticated>
