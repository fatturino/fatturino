<script>
    import Authenticated from '$layouts/Authenticated.svelte'
    import { router } from '@inertiajs/svelte'
    import { showToast } from '$lib/toast.js'
    import Dialog from '$lib/components/ui/Dialog.svelte'
    import Button from '$lib/components/ui/Button.svelte'
    import Input from '$lib/components/ui/Input.svelte'
    import Switch from '$lib/components/ui/Switch.svelte'

    const props = $props()
    const openApiManagedByEnv = props.openApiManagedByEnv ?? false
    const isDemoMode = props.isDemoMode ?? false
    const managedService = openApiManagedByEnv || isDemoMode
    const initialToken = props.apiToken ?? ''
    const webhookCallbackUrl = props.webhookCallbackUrl ?? ''
    let conservationAcknowledged = $state(props.conservationAcknowledged ?? false)
    const bootstrap = {
        initialSandbox: props.sandbox ?? true,
        initialSdi: props.companySdiCode ?? '',
        activated: props.activated ?? false,
        hasWebhookSecret: props.hasWebhookSecret ?? false,
    }

    let activeTab = $state('service')
    let apiToken = $state('')
    let sandbox = $state(bootstrap.initialSandbox)
    let sdiCode = $state(bootstrap.initialSdi)
    let webhookUrl = $state('')
    let loading = $state(false)
    let active = $state(bootstrap.activated)
    let hasWebhook = $state(bootstrap.hasWebhookSecret)
    let simulationType = $state('supplier-invoice')
    let simulationNotificationType = $state('NS')
    let simulationInvoiceUuid = $state('')
    let dialogOpen = $state(false)
    let dialogData = $state({ title: '', description: '', url: '', successMsg: '' })

    function token() {
        const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/)
        return match ? decodeURIComponent(match[1]) : ''
    }

    const isServiceActive = $derived(isDemoMode || active)

    async function execAction(url, msg, body = null) {
        loading = true
        try {
            const res = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-XSRF-TOKEN': token(),
                },
                body: body ? JSON.stringify(body) : null,
            })
            const data = await res.json().catch(() => ({}))
            if (data.success) {
                if (data.activated !== undefined) active = data.activated
                if (data.hasWebhookSecret !== undefined) hasWebhook = data.hasWebhookSecret
                if (data.conservationAcknowledged !== undefined) conservationAcknowledged = data.conservationAcknowledged

                // Keep server-backed status in sync without full page reload.
                router.reload({
                    only: ['activated', 'hasWebhookSecret', 'conservationAcknowledged', 'webhookCallbackUrl'],
                    preserveScroll: true,
                    preserveState: true,
                })
                showToast(msg)
            } else {
                const validationMessage = data.errors
                    ? Object.values(data.errors).flat().join(' ')
                    : null
                showToast(validationMessage || data.error || data.message || 'Errore', 'error')
            }
        } finally {
            loading = false
        }
    }

    function postData(url, msg) {
        execAction(url, msg, { api_token: apiToken, sandbox, company_sdi_code: sdiCode, webhook_url: webhookUrl })
    }

    function askConfirm(title, description, url, successMsg) {
        dialogData = { title, description, url, successMsg }
        dialogOpen = true
    }

    function handleConfirm() {
        postData(dialogData.url, dialogData.successMsg)
    }

    function simulateWebhook() {
        execAction('/api/openapi/simulate-webhook', 'Webhook simulato inviato.', {
            type: simulationType,
            notification_type: simulationNotificationType,
            invoice_uuid: simulationInvoiceUuid,
        })
    }
</script>

<Authenticated>
<div class="page-shell pb-24 sm:pb-6 w-full space-y-4">
    {#if conservationAcknowledged}
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3 sm:p-4">
            <p class="text-sm font-medium text-emerald-800">Obbligo di conservazione - Preso visione</p>
            <p class="text-xs text-emerald-700 mt-0.5">Hai dichiarato di aver preso visione degli obblighi di conservazione.</p>
        </div>
    {:else}
        <div class="card-brand p-3 sm:p-4">
            <p class="text-sm font-medium text-brand-deep mb-2">Obbligo di conservazione</p>
            <p class="text-xs text-brand-secondary/80 mb-3">Le fatture elettroniche devono essere conservate per 10 anni. La conservazione è a carico del contribuente.</p>
            <div class="flex flex-wrap items-center gap-2">
                <a href="https://ivaservizi.agenziaentrate.gov.it" target="_blank" class="btn-outline text-xs">Apri Agenzia Entrate</a>
                <Button class="btn-brand text-xs" onclick={() => execAction('/api/openapi/acknowledge-conservation', 'Obbligo di conservazione preso in carico.')}>Ho preso visione</Button>
            </div>
        </div>
    {/if}

    <section class="card-brand p-2 sm:p-3">
        <div class="grid grid-cols-2 gap-2">
            <Button class="rounded-lg border px-3 py-2 text-left text-sm transition-colors {activeTab === 'service' ? 'border-brand-deep bg-brand-deep text-white' : 'border-border-light bg-white text-brand-deep hover:bg-surface-muted'}" onclick={() => activeTab = 'service'}>Servizio</Button>
            <Button class="rounded-lg border px-3 py-2 text-left text-sm transition-colors {activeTab === 'webhook' ? 'border-brand-deep bg-brand-deep text-white' : 'border-border-light bg-white text-brand-deep hover:bg-surface-muted'}" onclick={() => activeTab = 'webhook'}>Webhook</Button>
        </div>
    </section>

    {#if activeTab === 'service'}
        <div class="flex items-center gap-3">
            {#if isServiceActive}
                <span class="inline-block px-2.5 py-1 rounded-full text-xs font-medium badge-sent">Attivo</span>
                {#if !isDemoMode}
                    <Button class="rounded-lg bg-red-50 px-3 py-1.5 text-xs font-medium text-red-700 hover:bg-red-100 transition-colors" onclick={() => askConfirm('Disattiva servizio', 'Sei sicuro di voler disattivare il servizio OpenAPI?', '/api/openapi/deactivate', 'Servizio disattivato.')}>Disattiva</Button>
                {/if}
            {:else}
                <span class="inline-block px-2.5 py-1 rounded-full text-xs font-medium badge-draft">Non attivo</span>
            {/if}
        </div>

        <div class="card-brand p-4 sm:p-5 max-w-2xl">
            {#if managedService}
                <p class="text-sm text-brand-secondary/70">
                    {#if isDemoMode}
                        Modalita demo - servizio sempre attivo e non disattivabile.
                    {:else}
                        Configurazione OpenAPI gestita via variabili environment.
                    {/if}
                </p>

                {#if !isServiceActive}
                    <div class="flex flex-wrap items-center gap-2 mt-4">
                        <Button class="btn-outline text-sm" onclick={() => postData('/api/openapi/check-connection', 'Connessione verificata.')}>Verifica connessione</Button>
                        <Button class="btn-brand text-sm" onclick={() => askConfirm('Attiva servizio', 'Sei sicuro di voler attivare il servizio OpenAPI?', '/api/openapi/activate', 'Servizio attivato.')}>Attiva</Button>
                    </div>
                {/if}
            {:else}
                <div class="space-y-4">
                    <label class="block"><span class="text-sm font-medium text-brand-deep">API Token</span>
                        <Input class="mt-1 block w-full rounded-lg border border-border-light bg-white px-3 py-2 text-sm form-focus" type="password" bind:value={apiToken} disabled={active} />
                        <span class="text-xs text-brand-secondary/60 mt-0.5 block">Token API fornito da OpenAPI.it</span>
                    </label>
                    <label class="flex items-center gap-2"><Switch bind:checked={sandbox} disabled={active} /><span class="text-sm text-brand-deep">Modalità sandbox</span></label>
                    <label class="block"><span class="text-sm font-medium text-brand-deep">Codice SDI</span>
                        <Input class="mt-1 block w-full rounded-lg border border-border-light bg-white px-3 py-2 text-sm form-focus" type="text" bind:value={sdiCode} disabled={active} />
                    </label>
                </div>

                {#if !active}
                <div class="flex flex-wrap items-center gap-2 mt-4">
                    <Button class="btn-outline text-sm" onclick={() => postData('/api/openapi/check-connection', 'Connessione verificata.')}>Verifica connessione</Button>
                    <Button class="btn-outline text-sm" onclick={() => postData('/api/openapi/save', 'Impostazioni salvate.')}>Salva</Button>
                    <Button class="btn-brand text-sm" onclick={() => askConfirm('Attiva servizio', 'Sei sicuro di voler attivare il servizio OpenAPI?', '/api/openapi/activate', 'Servizio attivato.')}>Attiva</Button>
                </div>
                {/if}
            {/if}
        </div>
    {:else}
        <div class="card-brand p-4 sm:p-5 max-w-2xl">
            <label class="block"><span class="text-sm font-medium text-brand-deep">URL Webhook</span>
                <Input class="mt-1 block w-full rounded-lg border border-border-light bg-white px-3 py-2 text-sm form-focus" type="text" bind:value={webhookUrl} placeholder="https://mio-dominio.com" disabled={openApiManagedByEnv} />
            </label>
            {#if !openApiManagedByEnv}
                <Button class="mt-4 btn-outline text-sm" onclick={() => postData('/api/openapi/save', 'Impostazioni salvate.')}>Salva</Button>
            {/if}
        </div>

        {#if active}
            <div class="card-brand p-4 sm:p-5 max-w-2xl">
                <h3 class="text-sm font-semibold text-brand-deep mb-3">Stato Webhook</h3>
                {#if hasWebhook}
                    <span class="inline-block px-2.5 py-1 rounded-full text-xs font-medium badge-sent mb-3">Configurato</span>
                    <div class="text-sm space-y-1 text-brand-secondary/70">
                        <p><span class="font-medium">URL:</span> <code class="bg-brand-bg px-1.5 py-0.5 rounded text-xs">{webhookCallbackUrl}</code></p>
                        <p><span class="font-medium">Eventi:</span> supplier-invoice, customer-notification, customer-invoice</p>
                    </div>
                {:else}
                    <span class="inline-block px-2.5 py-1 rounded-full text-xs font-medium badge-draft mb-3">Non configurato</span>
                {/if}
            </div>

            {#if sandbox}
                <div class="card-brand p-4 sm:p-5 max-w-2xl">
                    <h3 class="text-sm font-semibold text-brand-deep mb-3">Simulazione Webhook (Sandbox)</h3>
                    <div class="space-y-3">
                        <label class="block">
                            <span class="text-sm font-medium text-brand-deep">Tipo evento</span>
                            <select class="mt-1 block w-full rounded-lg border border-border-light bg-white px-3 py-2 text-sm form-focus" bind:value={simulationType}>
                                <option value="supplier-invoice">Supplier Invoice</option>
                                <option value="customer-notification">Customer Notification</option>
                                <option value="customer-invoice">Customer Invoice</option>
                            </select>
                        </label>

                        {#if simulationType === 'customer-notification'}
                            <label class="block">
                                <span class="text-sm font-medium text-brand-deep">Tipo notifica</span>
                                <select class="mt-1 block w-full rounded-lg border border-border-light bg-white px-3 py-2 text-sm form-focus" bind:value={simulationNotificationType}>
                                    <option value="NS">NS - Notifica di Scarto</option>
                                    <option value="RC">RC - Ricevuta di Consegna</option>
                                    <option value="MC">MC - Mancata Consegna</option>
                                    <option value="DT">DT - Decorrenza Termini</option>
                                    <option value="NE">NE - Esito Committente</option>
                                    <option value="AT">AT - Attestazione</option>
                                    <option value="EC">EC - Esito Cessionario</option>
                                </select>
                            </label>
                        {/if}

                        <label class="block">
                            <span class="text-sm font-medium text-brand-deep">Invoice UUID (opzionale)</span>
                            <Input class="mt-1 block w-full rounded-lg border border-border-light bg-white px-3 py-2 text-sm form-focus" type="text" bind:value={simulationInvoiceUuid} placeholder="new-uuid-to-import-5678" />
                        </label>

                        <Button class="btn-brand text-sm" onclick={simulateWebhook}>Invia simulazione</Button>
                    </div>
                </div>
            {/if}
        {/if}
    {/if}

    <div class="card-brand p-4 sm:p-5">
        <h2 class="text-sm font-semibold text-brand-deep mb-3">Istruzioni</h2>
        <ol class="list-decimal list-inside space-y-1 text-sm text-brand-secondary/70">
            <li>Registrati su <a href="https://openapi.it" target="_blank" class="text-brand-deep underline font-medium">OpenAPI.it</a></li>
            <li>Ottieni il tuo API Token dalla dashboard di OpenAPI</li>
            <li>Incolla il token qui sopra e clicca su "Attiva"</li>
        </ol>
    </div>
</div>
</Authenticated>

{#if loading}
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm">
        <div class="rounded-xl bg-white px-5 py-4 shadow-lg border border-border-light flex items-center gap-3">
            <span class="inline-block h-5 w-5 animate-spin rounded-full border-2 border-brand-deep/30 border-t-brand-deep"></span>
            <p class="text-sm font-medium text-brand-deep">Operazione in corso...</p>
        </div>
    </div>
{/if}

<Dialog
    bind:open={dialogOpen}
    title={dialogData.title}
    description={dialogData.description}
    onConfirm={handleConfirm}
/>
