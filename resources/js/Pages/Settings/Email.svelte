<script>
    import Authenticated from '$layouts/Authenticated.svelte'
    import { useForm } from '@inertiajs/svelte'
    import Button from '$lib/components/ui/Button.svelte'
    import Input from '$lib/components/ui/Input.svelte'
    import Select from '$lib/components/ui/Select.svelte'
    import Textarea from '$lib/components/ui/Textarea.svelte'
    import Switch from '$lib/components/ui/Switch.svelte'
    import { showToast } from '$lib/toast.js'

    let { settings = {}, smtpManagedByEnv = false, encryptionOptions = [], errors = {} } = $props()

    let autoSendSales = $state(settings.auto_send_sales ?? false)
    let autoSendProforma = $state(settings.auto_send_proforma ?? false)

    const form = useForm({
        smtp_host: settings.smtp_host ?? '',
        smtp_port: settings.smtp_port ?? '',
        smtp_username: settings.smtp_username ?? '',
        smtp_password: settings.smtp_password ?? '',
        smtp_encryption: settings.smtp_encryption ?? '',
        from_address: settings.from_address ?? '',
        from_name: settings.from_name ?? '',
        template_sales_subject: settings.template_sales_subject ?? '',
        template_sales_body: settings.template_sales_body ?? '',
        auto_send_sales: autoSendSales,
        template_proforma_subject: settings.template_proforma_subject ?? '',
        template_proforma_body: settings.template_proforma_body ?? '',
        auto_send_proforma: autoSendProforma,
    })

    function handleSubmit() {
        form.auto_send_sales = autoSendSales
        form.auto_send_proforma = autoSendProforma
        form.put('/email-settings', { preserveScroll: true, onSuccess: () => showToast('Impostazioni email salvate.') })
    }

    function handleTest() {
        form.post('/email-settings/test', { preserveScroll: true, onSuccess: () => showToast('Connessione SMTP riuscita.') })
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
            <!-- SMTP -->
            <div class="card-brand p-4 sm:p-5">
                <h2 class="text-base font-semibold text-brand-deep mb-4">Configurazione SMTP</h2>
                {#if smtpManagedByEnv}
                    <p class="text-sm text-brand-secondary/60">Configurato tramite variabili d'ambiente.</p>
                {:else}
                    <div class="space-y-4">
                        <label class="block"><span class="text-sm font-medium text-brand-deep">Host</span>
                            <Input class="mt-1 block w-full rounded-lg border border-border-light bg-white px-3 py-2 text-sm form-focus" type="text" bind:value={form.smtp_host} />
                        </label>
                        <div class="grid grid-cols-2 gap-4">
                            <label class="block"><span class="text-sm font-medium text-brand-deep">Porta</span>
                                <Input class="mt-1 block w-full rounded-lg border border-border-light bg-white px-3 py-2 text-sm form-focus" type="text" bind:value={form.smtp_port} />
                            </label>
                            <label class="block"><span class="text-sm font-medium text-brand-deep">Crittografia</span>
                                <Select useNative class="mt-1 block w-full rounded-lg border border-border-light bg-white px-3 py-2 text-sm form-focus" bind:value={form.smtp_encryption}>
                                    {#each encryptionOptions as o}<option value={o.value}>{o.label}</option>{/each}
                                </Select>
                            </label>
                        </div>
                        <label class="block"><span class="text-sm font-medium text-brand-deep">Username</span>
                            <Input class="mt-1 block w-full rounded-lg border border-border-light bg-white px-3 py-2 text-sm form-focus" type="text" bind:value={form.smtp_username} />
                        </label>
                        <label class="block"><span class="text-sm font-medium text-brand-deep">Password</span>
                            <Input class="mt-1 block w-full rounded-lg border border-border-light bg-white px-3 py-2 text-sm form-focus" type="password" bind:value={form.smtp_password} />
                        </label>
                    </div>
                {/if}
            </div>

            <!-- Mittente -->
            <div class="card-brand p-4 sm:p-5">
                <h2 class="text-base font-semibold text-brand-deep mb-4">Mittente</h2>
                <div class="space-y-4">
                    <label class="block"><span class="text-sm font-medium text-brand-deep">Indirizzo email</span>
                        <Input class="mt-1 block w-full rounded-lg border border-border-light bg-white px-3 py-2 text-sm form-focus" type="email" bind:value={form.from_address} />
                    </label>
                    <label class="block"><span class="text-sm font-medium text-brand-deep">Nome visualizzato</span>
                        <Input class="mt-1 block w-full rounded-lg border border-border-light bg-white px-3 py-2 text-sm form-focus" type="text" bind:value={form.from_name} />
                    </label>
                    {#if !smtpManagedByEnv}
                        <Button class="btn-outline text-sm" onclick={handleTest} disabled={form.processing}>
                            Test connessione
                        </Button>
                    {/if}
                    {#if errors.smtp}<span class="text-red-600 text-xs block">{errors.smtp}</span>{/if}
                </div>
            </div>

            <!-- Template Fatture -->
            <div class="card-brand p-4 sm:p-5">
                <h2 class="text-base font-semibold text-brand-deep mb-4">Template Fatture di Vendita</h2>
                <div class="space-y-4">
                    <label class="block"><span class="text-sm font-medium text-brand-deep">Oggetto</span>
                        <Input class="mt-1 block w-full rounded-lg border border-border-light bg-white px-3 py-2 text-sm form-focus" type="text" bind:value={form.template_sales_subject} />
                    </label>
                    <label class="block"><span class="text-sm font-medium text-brand-deep">Corpo</span>
                        <Textarea class="mt-1 block w-full min-h-64 resize-y rounded-lg border border-border-light bg-white px-3 py-2 text-sm form-focus" rows="10" bind:value={form.template_sales_body}></Textarea>
                    </label>
                    <label class="flex items-center gap-2"><Switch bind:checked={autoSendSales} /><span class="text-sm text-brand-deep">Invio automatico</span></label>
                </div>
            </div>

            <!-- Template Proforma -->
            <div class="card-brand p-4 sm:p-5">
                <h2 class="text-base font-semibold text-brand-deep mb-4">Template Proforma</h2>
                <div class="space-y-4">
                    <label class="block"><span class="text-sm font-medium text-brand-deep">Oggetto</span>
                        <Input class="mt-1 block w-full rounded-lg border border-border-light bg-white px-3 py-2 text-sm form-focus" type="text" bind:value={form.template_proforma_subject} />
                    </label>
                    <label class="block"><span class="text-sm font-medium text-brand-deep">Corpo</span>
                        <Textarea class="mt-1 block w-full min-h-64 resize-y rounded-lg border border-border-light bg-white px-3 py-2 text-sm form-focus" rows="10" bind:value={form.template_proforma_body}></Textarea>
                    </label>
                    <label class="flex items-center gap-2"><Switch bind:checked={autoSendProforma} /><span class="text-sm text-brand-deep">Invio automatico</span></label>
                </div>
            </div>
        </div>
    </div>
</Authenticated>
