<script>
    import { useForm } from '@inertiajs/svelte'
    import Button from '$lib/components/ui/Button.svelte'
    import Input from '$lib/components/ui/Input.svelte'
    import Select from '$lib/components/ui/Select.svelte'
    import Switch from '$lib/components/ui/Switch.svelte'
    import { page } from '@inertiajs/svelte'

    const props = $props()
    const appName = props.appName ?? page.props.appName
    const fiscalRegimes = props.fiscalRegimes ?? []
    const setupBootstrap = {
        prefill: props.prefill ?? {},
        initialErrors: props.errors ?? {},
        initialStep: props.step ?? 1,
    }
    let errors = $state(setupBootstrap.initialErrors ?? {})
    let submitting = $state(false)

    const form = useForm({
        step: setupBootstrap.initialStep ?? 1,
        name: setupBootstrap.prefill?.name || '',
        email: setupBootstrap.prefill?.email || '',
        password: '',
        password_confirmation: '',
        company_name: setupBootstrap.prefill?.company_name || '',
        company_vat_number: setupBootstrap.prefill?.company_vat_number || '',
        company_tax_code: setupBootstrap.prefill?.company_tax_code || '',
        company_fiscal_regime: setupBootstrap.prefill?.company_fiscal_regime || 'RF01',
        withholding_tax_enabled: !!setupBootstrap.prefill?.withholding_tax_enabled,
        auto_stamp_duty: !!setupBootstrap.prefill?.auto_stamp_duty,
        company_address: setupBootstrap.prefill?.company_address || '',
        company_city: setupBootstrap.prefill?.company_city || '',
        company_postal_code: setupBootstrap.prefill?.company_postal_code || '',
        company_province: setupBootstrap.prefill?.company_province || '',
        company_country: setupBootstrap.prefill?.company_country || 'IT',
        company_pec: setupBootstrap.prefill?.company_pec || '',
        company_sdi_code: setupBootstrap.prefill?.company_sdi_code || '0000000',
    })

    let step = $derived(form.step)

    const steps = [
        { title: 'Account' },
        { title: 'Azienda & Fatture' },
        { title: 'Indirizzo & Fatturazione Elettronica' },
    ]

    function nextStep() {
        return submitStep()
    }

    function handleSubmit() {
        return submitStep()
    }

    async function onSubmit(event) {
        event.preventDefault()
        await submitStep()
    }

    function getCsrfToken() {
        const meta = document.querySelector('meta[name="csrf-token"]')
        return meta?.getAttribute('content') || ''
    }

    async function submitStep() {
        submitting = true
        errors = {}
        try {
            const response = await fetch('/setup/step', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    ...form.data(),
                    step,
                }),
            })

            if (response.status === 422) {
                const payload = await response.json()
                errors = payload.errors || {}
                return
            }

            if (!response.ok) {
                errors = { step: 'Errore inatteso durante il salvataggio dello step.' }
                return
            }

            const payload = await response.json()
            if (payload.redirect) {
                window.location.assign(payload.redirect)
                return
            }
            if (payload.step) {
                form.step = payload.step
            }
        } finally {
            submitting = false
        }
    }

    function canGoBack() {
        return step > 1
    }

    function goBack() {
        if (step > 1) {
            form.step = step - 1
            errors = {}
        }
    }

    function canProceed() {
        return !submitting
    }

    function buttonLabel() {
        if (step < 3) {
            return submitting ? '...' : 'Avanti'
        }
        return submitting ? '...' : 'Completa Setup'
    }

    // When fiscal regime changes, update defaults (mirrors Wizard.php logic)
    function onFiscalRegimeChange() {
        if (form.company_fiscal_regime === 'RF19') {
            form.auto_stamp_duty = true
            form.withholding_tax_enabled = false
        } else if (form.company_fiscal_regime === 'RF01') {
            form.withholding_tax_enabled = true
            form.auto_stamp_duty = false
        }
    }

    onFiscalRegimeChange()

    // When name changes, auto-fill company name
    function onNameChange() {
        if (!form.company_name) {
            form.company_name = form.name
        }
    }

    function errorId(field) {
        return errors[field] ? `${field}-error` : undefined
    }
</script>

<div class="guest-shell">
    <div class="guest-panel guest-login-panel w-full max-w-6xl">
        <aside class="guest-hero guest-login-hero">
            <img src="/brand/logo-white.svg" alt="Fatturino" class="guest-login-logo" />
            <p class="guest-kicker guest-login-kicker">Prima configurazione</p>
            <h1 class="guest-title">{appName} Setup</h1>
            <p class="guest-copy guest-login-copy">Tre passaggi guidati per partire con account, anagrafica e fatturazione elettronica.</p>
            <div class="guest-pills guest-login-pills">
                <span>3 step</span>
                <span>Dati validati</span>
                <span>Pronto per emettere</span>
            </div>
        </aside>

        <section class="guest-card guest-card-wide guest-login-card">
        <img src="/brand/logo-dark.svg" alt="Fatturino" class="guest-login-logo-mark mb-6" />

        <!-- Stepper indicator -->
        <div class="flex items-center gap-2 mb-8 overflow-x-auto pb-1">
            {#each steps as item, index}
                {@const stepIndex = index + 1}
                <div class="flex items-center gap-2">
                    <Button
                        type="button"
                        class="flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-medium transition-colors whitespace-nowrap
                            {stepIndex === step
                                ? 'bg-brand text-white shadow-md'
                                : stepIndex < step
                                    ? 'text-brand-secondary/80'
                                    : 'text-brand-secondary/50'}"
                        onclick={() => {
                            if (stepIndex < step) {
                                form.step = stepIndex
                            }
                        }}
                    >
                        <span
                            class="flex items-center justify-center w-5 h-5 rounded-full text-xs font-bold transition-colors
                                {stepIndex === step
                                    ? 'bg-white text-brand-deep'
                                    : stepIndex < step
                                        ? 'bg-brand-bg0 text-white'
                                        : 'bg-brand-secondary/10 text-brand-secondary/60'}"
                        >
                            {stepIndex}
                        </span>
                        {item.title}
                    </Button>
                    {#if index < steps.length - 1}
                        <div class="w-6 h-px bg-brand-secondary/20"></div>
                    {/if}
                </div>
            {/each}
        </div>

        <form onsubmit={onSubmit}>
            <p class="mb-4 text-xs text-brand-secondary/70">I campi contrassegnati con * sono obbligatori.</p>

            {#if errors.step}
                <div class="mb-4 rounded-lg border border-error-red/30 bg-error-red/5 px-3 py-2 text-sm text-error-red" role="alert">
                    {errors.step}
                </div>
            {/if}

            <!-- Step content -->
            <div class="min-h-[300px]">
                {#if step === 1}
                    <div class="space-y-4">
                        <p class="text-brand-secondary/70 text-sm">Crea il tuo account amministratore.</p>

                        <label class="block" for="setup-name">
                            <span class="text-sm font-medium text-brand-deep">Nome *</span>
                            <Input
                                id="setup-name"
                                name="name"
                                class="mt-1 w-full input-field"
                                type="text"
                                bind:value={form.name}
                                oninput={onNameChange}
                                autocomplete="name"
                                aria-invalid={Boolean(errors.name)}
                                aria-describedby={errorId('name')}
                                required
                            />
                            {#if errors.name}
                                <span id="name-error" class="text-error-red text-xs mt-0.5 block" role="alert">{errors.name}</span>
                            {/if}
                        </label>

                        <label class="block" for="setup-email">
                            <span class="text-sm font-medium text-brand-deep">Email *</span>
                            <Input
                                id="setup-email"
                                name="email"
                                class="mt-1 w-full input-field"
                                type="email"
                                bind:value={form.email}
                                autocomplete="email"
                                inputmode="email"
                                aria-invalid={Boolean(errors.email)}
                                aria-describedby={errorId('email')}
                                required
                            />
                            {#if errors.email}
                                <span id="email-error" class="text-error-red text-xs mt-0.5 block" role="alert">{errors.email}</span>
                            {/if}
                        </label>

                        <label class="block" for="setup-password">
                            <span class="text-sm font-medium text-brand-deep">Password *</span>
                            <Input
                                id="setup-password"
                                name="password"
                                class="mt-1 w-full input-field"
                                type="password"
                                bind:value={form.password}
                                autocomplete="new-password"
                                aria-invalid={Boolean(errors.password)}
                                aria-describedby={errorId('password')}
                                required
                            />
                            {#if errors.password}
                                <span id="password-error" class="text-error-red text-xs mt-0.5 block" role="alert">{errors.password}</span>
                            {/if}
                        </label>

                        <label class="block" for="setup-password-confirmation">
                            <span class="text-sm font-medium text-brand-deep">Conferma Password *</span>
                            <Input
                                id="setup-password-confirmation"
                                name="password_confirmation"
                                class="mt-1 w-full input-field"
                                type="password"
                                bind:value={form.password_confirmation}
                                autocomplete="new-password"
                                aria-invalid={Boolean(errors.password_confirmation)}
                                aria-describedby={errorId('password_confirmation')}
                                required
                            />
                            {#if errors.password_confirmation}
                                <span id="password_confirmation-error" class="text-error-red text-xs mt-0.5 block" role="alert">{errors.password_confirmation}</span>
                            {/if}
                        </label>
                    </div>

                {:else if step === 2}
                    <div class="space-y-4">
                        <p class="text-brand-secondary/70 text-sm">Informazioni sulla tua azienda e impostazioni predefinite per le fatture.</p>

                        <label class="block" for="setup-company-name">
                            <span class="text-sm font-medium text-brand-deep">Nome Azienda *</span>
                            <Input
                                id="setup-company-name"
                                name="company_name"
                                class="mt-1 w-full input-field"
                                type="text"
                                bind:value={form.company_name}
                                autocomplete="organization"
                                aria-invalid={Boolean(errors.company_name)}
                                aria-describedby={errorId('company_name')}
                                required
                            />
                            {#if errors.company_name}
                                <span id="company_name-error" class="text-error-red text-xs mt-0.5 block" role="alert">{errors.company_name}</span>
                            {/if}
                        </label>

                        <div class="grid grid-cols-2 gap-4">
                            <label class="block" for="setup-company-vat-number">
                                <span class="text-sm font-medium text-brand-deep">Partita IVA *</span>
                                <Input
                                    id="setup-company-vat-number"
                                    name="company_vat_number"
                                    class="mt-1 w-full input-field"
                                    type="text"
                                    bind:value={form.company_vat_number}
                                    inputmode="numeric"
                                    aria-invalid={Boolean(errors.company_vat_number)}
                                    aria-describedby={errorId('company_vat_number')}
                                    required
                                />
                                {#if errors.company_vat_number}
                                    <span id="company_vat_number-error" class="text-error-red text-xs mt-0.5 block" role="alert">{errors.company_vat_number}</span>
                                {/if}
                            </label>
                            <label class="block" for="setup-company-tax-code">
                                <span class="text-sm font-medium text-brand-deep">Codice Fiscale *</span>
                                <Input
                                    id="setup-company-tax-code"
                                    name="company_tax_code"
                                    class="mt-1 w-full input-field"
                                    type="text"
                                    bind:value={form.company_tax_code}
                                    aria-invalid={Boolean(errors.company_tax_code)}
                                    aria-describedby={errorId('company_tax_code')}
                                    required
                                />
                                {#if errors.company_tax_code}
                                    <span id="company_tax_code-error" class="text-error-red text-xs mt-0.5 block" role="alert">{errors.company_tax_code}</span>
                                {/if}
                            </label>
                        </div>

                        <label class="block" for="setup-company-fiscal-regime">
                            <span class="text-sm font-medium text-brand-deep">Regime Fiscale *</span>
                            <Select useNative
                                id="setup-company-fiscal-regime"
                                name="company_fiscal_regime"
                                class="mt-1 w-full input-field bg-white"
                                bind:value={form.company_fiscal_regime}
                                onchange={onFiscalRegimeChange}
                                aria-invalid={Boolean(errors.company_fiscal_regime)}
                                aria-describedby={errorId('company_fiscal_regime')}
                                required
                            >
                                {#each fiscalRegimes as regime}
                                    <option value={regime.id}>{regime.name}</option>
                                {/each}
                            </Select>
                            {#if errors.company_fiscal_regime}
                                <span id="company_fiscal_regime-error" class="text-error-red text-xs mt-0.5 block" role="alert">{errors.company_fiscal_regime}</span>
                            {/if}
                        </label>

                    </div>

                {:else if step === 3}
                    <div class="space-y-4">
                        <p class="text-brand-secondary/70 text-sm">Indirizzo della sede legale e dati per la fatturazione elettronica.</p>

                        <label class="block" for="setup-company-address">
                            <span class="text-sm font-medium text-brand-deep">Indirizzo *</span>
                            <Input
                                id="setup-company-address"
                                name="company_address"
                                class="mt-1 w-full input-field"
                                type="text"
                                bind:value={form.company_address}
                                autocomplete="street-address"
                                aria-invalid={Boolean(errors.company_address)}
                                aria-describedby={errorId('company_address')}
                                required
                            />
                            {#if errors.company_address}
                                <span id="company_address-error" class="text-error-red text-xs mt-0.5 block" role="alert">{errors.company_address}</span>
                            {/if}
                        </label>

                        <div class="grid grid-cols-3 gap-4">
                            <label class="block" for="setup-company-postal-code">
                                <span class="text-sm font-medium text-brand-deep">CAP *</span>
                                <Input
                                    id="setup-company-postal-code"
                                    name="company_postal_code"
                                    class="mt-1 w-full input-field"
                                    type="text"
                                    bind:value={form.company_postal_code}
                                    autocomplete="postal-code"
                                    inputmode="numeric"
                                    aria-invalid={Boolean(errors.company_postal_code)}
                                    aria-describedby={errorId('company_postal_code')}
                                    required
                                />
                                {#if errors.company_postal_code}
                                    <span id="company_postal_code-error" class="text-error-red text-xs mt-0.5 block" role="alert">{errors.company_postal_code}</span>
                                {/if}
                            </label>
                            <label class="block" for="setup-company-city">
                                <span class="text-sm font-medium text-brand-deep">Città *</span>
                                <Input
                                    id="setup-company-city"
                                    name="company_city"
                                    class="mt-1 w-full input-field"
                                    type="text"
                                    bind:value={form.company_city}
                                    autocomplete="address-level2"
                                    aria-invalid={Boolean(errors.company_city)}
                                    aria-describedby={errorId('company_city')}
                                    required
                                />
                                {#if errors.company_city}
                                    <span id="company_city-error" class="text-error-red text-xs mt-0.5 block" role="alert">{errors.company_city}</span>
                                {/if}
                            </label>
                            <label class="block" for="setup-company-province">
                                <span class="text-sm font-medium text-brand-deep">Provincia *</span>
                                <Input
                                    id="setup-company-province"
                                    name="company_province"
                                    class="mt-1 w-full input-field"
                                    type="text"
                                    maxlength="2"
                                    bind:value={form.company_province}
                                    autocomplete="address-level1"
                                    aria-invalid={Boolean(errors.company_province)}
                                    aria-describedby={errorId('company_province')}
                                    required
                                />
                                {#if errors.company_province}
                                    <span id="company_province-error" class="text-error-red text-xs mt-0.5 block" role="alert">{errors.company_province}</span>
                                {/if}
                            </label>
                        </div>

                        <label class="block" for="setup-company-country">
                            <span class="text-sm font-medium text-brand-deep">Nazione *</span>
                            <Input
                                id="setup-company-country"
                                name="company_country"
                                class="mt-1 w-full input-field"
                                type="text"
                                maxlength="2"
                                bind:value={form.company_country}
                                autocomplete="country"
                                aria-invalid={Boolean(errors.company_country)}
                                aria-describedby={errorId('company_country')}
                                required
                            />
                            {#if errors.company_country}
                                <span id="company_country-error" class="text-error-red text-xs mt-0.5 block" role="alert">{errors.company_country}</span>
                            {/if}
                        </label>

                        <label class="block" for="setup-company-pec">
                            <span class="text-sm font-medium text-brand-deep">PEC *</span>
                            <Input
                                id="setup-company-pec"
                                name="company_pec"
                                class="mt-1 w-full input-field"
                                type="email"
                                bind:value={form.company_pec}
                                autocomplete="email"
                                inputmode="email"
                                aria-invalid={Boolean(errors.company_pec)}
                                aria-describedby={errorId('company_pec')}
                                required
                            />
                            {#if errors.company_pec}
                                <span id="company_pec-error" class="text-error-red text-xs mt-0.5 block" role="alert">{errors.company_pec}</span>
                            {/if}
                        </label>

                        <label class="block" for="setup-company-sdi-code">
                            <span class="text-sm font-medium text-brand-deep">Codice SDI *</span>
                            <Input
                                id="setup-company-sdi-code"
                                name="company_sdi_code"
                                class="mt-1 w-full input-field"
                                type="text"
                                maxlength="7"
                                bind:value={form.company_sdi_code}
                                aria-invalid={Boolean(errors.company_sdi_code)}
                                aria-describedby={errorId('company_sdi_code')}
                                required
                            />
                            {#if errors.company_sdi_code}
                                <span id="company_sdi_code-error" class="text-error-red text-xs mt-0.5 block" role="alert">{errors.company_sdi_code}</span>
                            {/if}
                        </label>

                        <fieldset class="space-y-3 border border-border-light rounded-lg p-4">
                            <legend class="text-sm font-medium text-brand-deep px-1">Impostazioni Fatture</legend>
                            <label class="flex items-center gap-2 text-sm text-brand-deep">
                                <Switch bind:checked={form.withholding_tax_enabled} />
                                Ritenuta d'acconto
                            </label>
                            <label class="flex items-center gap-2 text-sm text-brand-deep">
                                <Switch bind:checked={form.auto_stamp_duty} />
                                Marca da bollo automatica
                            </label>
                        </fieldset>
                    </div>
                {/if}
            </div>

            <!-- Navigation -->
            <div class="flex justify-between mt-8">
            {#if canGoBack()}
                <Button
                    type="button"
                    class="btn-ghost rounded-lg px-4 py-2 text-sm"
                    onclick={goBack}
                >
                    Indietro
                </Button>
                {:else}
                    <div></div>
                {/if}

                {#if step < 3}
                    <Button
                        type="submit"
                        class="btn-brand px-6 py-2 guest-submit text-sm"
                        disabled={!canProceed()}
                    >
                        {buttonLabel()}
                    </Button>
                {:else}
                    <Button
                        type="submit"
                        class="btn-brand px-6 py-2 guest-submit text-sm"
                        disabled={!canProceed()}
                    >
                        {buttonLabel()}
                    </Button>
                {/if}
            </div>
        </form>
        </section>
    </div>
</div>
