<script>
    import Authenticated from '$layouts/Authenticated.svelte'
    import { useForm } from '@inertiajs/svelte'
    import Button from '$lib/components/ui/Button.svelte'
    import Input from '$lib/components/ui/Input.svelte'
    import Select from '$lib/components/ui/Select.svelte'
    import Switch from '$lib/components/ui/Switch.svelte'
    import { showToast } from '$lib/toast.js'

    let { backup = {}, backupManagedByEnv = false, frequencyOptions = [], errors = {} } = $props()

    let backupEnabled = $state(backup.enabled ?? false)
    let backupFrequency = $state(backup.frequency ?? 'daily')
    let pathStyle = $state(backup.aws_use_path_style_endpoint ?? false)

    const backupForm = useForm({
        enabled: backupEnabled,
        frequency: backup.frequency ?? 'daily',
        time: backup.time ?? '02:00',
        day_of_week: backup.day_of_week ?? 1,
        day_of_month: backup.day_of_month ?? 1,
        aws_access_key_id: backup.aws_access_key_id ?? '',
        aws_secret_access_key: backup.aws_secret_access_key ?? '',
        aws_default_region: backup.aws_default_region ?? '',
        aws_bucket: backup.aws_bucket ?? '',
        aws_endpoint: backup.aws_endpoint ?? '',
        aws_use_path_style_endpoint: pathStyle,
    })

    function saveBackup() {
        backupForm.enabled = backupEnabled
        backupForm.frequency = backupFrequency
        backupForm.aws_use_path_style_endpoint = pathStyle
        backupForm.put('/services/backup', { preserveScroll: true, onSuccess: () => showToast('Impostazioni backup salvate.') })
    }

    function testConnection() {
        backupForm.post('/services/test-connection', { preserveScroll: true, onSuccess: () => showToast('Connessione S3 riuscita.') })
    }
</script>

<Authenticated>
    <div class="page-shell pb-24 sm:pb-6 w-full">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Backup -->
            <div class="card-brand p-4 sm:p-5">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-base font-semibold text-brand-deep">Backup</h2>
                    {#if !backupManagedByEnv}
                        <Button class="btn-brand text-sm" onclick={saveBackup} disabled={backupForm.processing}>
                            {backupForm.processing ? 'Salvataggio...' : 'Salva backup'}
                        </Button>
                    {/if}
                </div>

                {#if backupManagedByEnv}
                    <div class="opacity-50 pointer-events-none">
                        <div class="space-y-4">
                            <p class="text-sm text-brand-secondary/40">Gestito dall'infrastruttura.</p>
                        </div>
                    </div>
                {:else}
                    <div class="space-y-4">
                        <label class="flex items-center gap-2"><Switch bind:checked={backupEnabled} /><span class="text-sm text-brand-deep">Abilita backup automatico</span></label>

                        {#if backupEnabled}
                            <div class="grid grid-cols-2 gap-4">
                                <label class="block"><span class="text-sm font-medium text-brand-deep">Frequenza</span>
                                    <Select useNative class="mt-1 block w-full rounded-lg border border-border-light bg-white px-3 py-2 text-sm form-focus" bind:value={backupFrequency}>
                                        {#each frequencyOptions as o}<option value={o.value}>{o.label}</option>{/each}
                                    </Select>
                                </label>
                                <label class="block"><span class="text-sm font-medium text-brand-deep">Orario</span>
                                    <Input class="mt-1 block w-full rounded-lg border border-border-light bg-white px-3 py-2 text-sm form-focus" type="time" bind:value={backupForm.time} />
                                </label>
                            </div>

                            {#if backupFrequency === 'weekly'}
                                <label class="block"><span class="text-sm font-medium text-brand-deep">Giorno della settimana (0=domenica)</span>
                                    <Input class="mt-1 block w-24 rounded-lg border border-border-light bg-white px-3 py-2 text-sm form-focus" type="number" min="0" max="6" bind:value={backupForm.day_of_week} />
                                </label>
                            {:else if backupFrequency === 'monthly'}
                                <label class="block"><span class="text-sm font-medium text-brand-deep">Giorno del mese (1-28)</span>
                                    <Input class="mt-1 block w-24 rounded-lg border border-border-light bg-white px-3 py-2 text-sm form-focus" type="number" min="1" max="28" bind:value={backupForm.day_of_month} />
                                </label>
                            {/if}

                            <hr class="border-brand-secondary/10" />
                            <p class="text-xs font-semibold text-brand-secondary/60 uppercase tracking-wide">Configurazione S3</p>

                            <div class="grid grid-cols-2 gap-4">
                                <label class="block"><span class="text-sm font-medium text-brand-deep">Access Key ID *</span>
                                    <Input class="mt-1 block w-full rounded-lg border border-border-light bg-white px-3 py-2 text-sm form-focus" type="text" bind:value={backupForm.aws_access_key_id} />
                                </label>
                                <label class="block"><span class="text-sm font-medium text-brand-deep">Secret Access Key *</span>
                                    <Input class="mt-1 block w-full rounded-lg border border-border-light bg-white px-3 py-2 text-sm form-focus" type="password" bind:value={backupForm.aws_secret_access_key} />
                                </label>
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <label class="block"><span class="text-sm font-medium text-brand-deep">Region *</span>
                                    <Input class="mt-1 block w-full rounded-lg border border-border-light bg-white px-3 py-2 text-sm form-focus" type="text" bind:value={backupForm.aws_default_region} />
                                </label>
                                <label class="block"><span class="text-sm font-medium text-brand-deep">Bucket *</span>
                                    <Input class="mt-1 block w-full rounded-lg border border-border-light bg-white px-3 py-2 text-sm form-focus" type="text" bind:value={backupForm.aws_bucket} />
                                </label>
                            </div>
                            <label class="block"><span class="text-sm font-medium text-brand-deep">Endpoint (opzionale)</span>
                                <Input class="mt-1 block w-full rounded-lg border border-border-light bg-white px-3 py-2 text-sm form-focus" type="text" bind:value={backupForm.aws_endpoint} />
                            </label>
                            <label class="flex items-center gap-2"><Switch bind:checked={pathStyle} /><span class="text-sm text-brand-deep">Path-style endpoint</span></label>

                            <Button class="btn-outline text-sm" onclick={testConnection} disabled={backupForm.processing}>Test connessione</Button>
                            {#if errors.s3}<span class="text-red-600 text-xs block">{errors.s3}</span>{/if}
                        {/if}
                    </div>
                {/if}
            </div>

        </div>
    </div>
</Authenticated>
