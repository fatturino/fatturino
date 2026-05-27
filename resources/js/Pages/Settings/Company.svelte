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
    let atecoResults = $state([])
    let atecoOpen = $state(false)
    let atecoLoading = $state(false)
    let removeLogo = $state(false)
    let atecoTimer = null

    async function searchAteco() {
        const q = atecoSearchQuery.trim()
        if (q.length < 2) { atecoResults = []; return }
        atecoLoading = true
        const res = await fetch(`/ateco/search?q=${encodeURIComponent(q)}`)
        const data = await res.json()
        atecoResults = data.filter(r => !atecoCodesState.some(c => c.code === r.id))
        atecoLoading = false
    }

    function onAtecoInput() {
        atecoOpen = true
        clearTimeout(atecoTimer)
        atecoTimer = setTimeout(searchAteco, 200)
    }

    function selectAteco(code, label) {
        atecoCodesState = [...atecoCodesState, { code, label }]
        atecoSearchQuery = ''
        atecoResults = []
        atecoOpen = false
    }

    function removeAtecoCode(code) {
        atecoCodesState = atecoCodesState.filter(c => c.code !== code)
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
                    <div class="relative mb-3">
                        <Input class="w-full rounded-lg border border-border-light bg-white px-3 py-2 text-sm form-focus" type="text" placeholder="Cerca codice ATECO..." bind:value={atecoSearchQuery} oninput={onAtecoInput} onfocus={() => { if (atecoResults.length > 0) atecoOpen = true }} onblur={() => setTimeout(() => { atecoOpen = false }, 200)} />
                        {#if atecoOpen && (atecoResults.length > 0 || atecoLoading)}
                            <div class="absolute z-10 mt-1 w-full bg-white border border-border-light rounded-lg shadow-lg max-h-60 overflow-y-auto">
                                {#if atecoLoading}
                                    <p class="px-3 py-2 text-sm text-brand-secondary/60">Caricamento...</p>
                                {:else}
                                    {#each atecoResults as r}
                                        <Button type="button" class="w-full text-left px-3 py-2 text-sm hover:bg-surface-muted transition-colors border-b border-border-light last:border-0" onclick={() => selectAteco(r.id, r.name)}>{r.name}</Button>
                                    {/each}
                                {/if}
                            </div>
                        {/if}
                    </div>
                    <div class="flex flex-wrap gap-2">
                        {#each atecoCodesState as c}
                            <span class="inline-flex items-center gap-1 px-2 py-1 rounded-lg bg-surface-muted text-xs font-medium text-brand-deep">
                                {c.label}
                                <Button class="text-brand-secondary/60 hover:text-red-600 transition-colors ml-0.5" onclick={() => removeAtecoCode(c.code)}>✕</Button>
                            </span>
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
