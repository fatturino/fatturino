<script>
    import Authenticated from '$layouts/Authenticated.svelte'
    import Button from '$lib/components/ui/Button.svelte'
    import Input from '$lib/components/ui/Input.svelte'
    import Select from '$lib/components/ui/Select.svelte'
    import Dialog from '$lib/components/ui/Dialog.svelte'
    import { useForm } from '@inertiajs/svelte'
    import { showToast } from '$lib/toast.js'

    let {
        sequences = { data: [], current_page: 1, last_page: 1, from: 0, to: 0, total: 0, links: [] },
        typeOptions = [],
        errors = {},
    } = $props()

    let dialogOpen = $state(false)
    let isEditing = $state(false)
    let editingId = $state(null)
    let deleteDialogOpen = $state(false)
    let sequenceToDelete = $state(null)

    const form = useForm({
        name: '',
        type: 'electronic_invoice',
        pattern: '{SEQ}',
    })

    function openCreate() {
        form.reset()
        form.clearErrors()
        form.type = 'electronic_invoice'
        form.pattern = '{SEQ}'
        isEditing = false
        editingId = null
        dialogOpen = true
    }

    function openEdit(seq) {
        form.reset()
        form.clearErrors()
        form.name = seq.name
        form.type = seq.type
        form.pattern = seq.pattern
        isEditing = true
        editingId = seq.id
        dialogOpen = true
    }

    function closeModal() {
        dialogOpen = false
        editingId = null
    }

    function handleSubmit() {
        if (isEditing) {
            form.put(`/sequences/${editingId}`, {
                preserveScroll: true,
                onSuccess: () => {
                    closeModal()
                    showToast('Sezionale aggiornato.')
                },
            })
            return
        }

        form.post('/sequences', {
            preserveScroll: true,
            onSuccess: () => {
                closeModal()
                showToast('Sezionale creato.')
            },
        })
    }

    function handleDelete(seq) {
        sequenceToDelete = seq
        deleteDialogOpen = true
    }

    function confirmDelete() {
        if (!sequenceToDelete) return
        const deleteForm = useForm({})
        deleteForm.delete(`/sequences/${sequenceToDelete.id}`, {
            preserveScroll: true,
            onSuccess: () => showToast('Sezionale eliminato.'),
        })
        sequenceToDelete = null
    }

    function typeLabel(value) {
        const opt = typeOptions.find((option) => option.value === value)
        return opt ? opt.label : value
    }

    function fieldError(name) {
        return form.errors?.[name] ?? errors?.[name]
    }
</script>

<Authenticated>
    {#snippet headerActions()}
        <Button class="btn-brand text-sm" onclick={openCreate}>Nuovo sezionale</Button>
    {/snippet}

    <div class="page-shell pb-24 sm:pb-6 w-full">
        <section class="card-brand p-4 sm:p-5 mb-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-xs uppercase tracking-wide text-brand-secondary/70">Numerazione documenti</p>
                    <h2 class="mt-1 text-xl font-semibold text-brand-deep">Gestione sequenze</h2>
                    <p class="mt-1 text-sm text-brand-secondary/80">Definisci i formati progressivi per tipo documento (esempio: <code>{'{ANNO}'}/{'{SEQ}'}</code>).</p>
                </div>
                <div class="rounded-lg border border-border-light bg-surface-muted px-3 py-2 text-xs text-brand-secondary/80">
                    Totale sequenze: <span class="font-semibold text-brand-deep">{sequences.total}</span>
                </div>
            </div>
        </section>

        <section class="card-brand overflow-hidden hidden md:block">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-border-light bg-surface-muted text-left">
                        <th class="px-4 py-3 font-semibold text-brand-secondary text-xs uppercase tracking-wider">Nome</th>
                        <th class="px-4 py-3 font-semibold text-brand-secondary text-xs uppercase tracking-wider">Formato</th>
                        <th class="px-4 py-3 font-semibold text-brand-secondary text-xs uppercase tracking-wider">Tipo</th>
                        <th class="px-4 py-3 font-semibold text-brand-secondary text-xs uppercase tracking-wider text-right">Azioni</th>
                    </tr>
                </thead>
                <tbody>
                    {#each sequences.data as seq}
                        <tr class="border-b border-border-light hover:bg-surface-muted/70 transition-colors">
                            <td class="px-4 py-3 font-medium text-brand-deep">
                                {seq.name}
                                {#if seq.is_system}
                                    <span class="ml-2 inline-block rounded-full bg-brand-secondary/10 px-2 py-0.5 text-[11px] font-medium text-brand-secondary">Sistema</span>
                                {/if}
                            </td>
                            <td class="px-4 py-3 font-mono text-brand-secondary">{seq.pattern}</td>
                            <td class="px-4 py-3">
                                <span class="inline-block rounded-full bg-surface-muted px-2 py-0.5 text-xs font-medium text-brand-secondary">
                                    {typeLabel(seq.type)}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-3">
                                    <Button class="text-xs font-medium text-brand-secondary hover:text-brand-deep" onclick={() => openEdit(seq)}>
                                        Modifica
                                    </Button>
                                    {#if !seq.is_system}
                                        <Button class="text-xs font-medium text-red-700 hover:text-red-800" onclick={() => handleDelete(seq)}>
                                            Elimina
                                        </Button>
                                    {/if}
                                </div>
                            </td>
                        </tr>
                    {:else}
                        <tr>
                            <td colspan="4" class="px-4 py-12 text-center text-brand-secondary/70">
                                Nessun sezionale ancora creato.
                            </td>
                        </tr>
                    {/each}
                </tbody>
            </table>
        </section>

        <section class="md:hidden space-y-3">
            {#each sequences.data as seq}
                <article class="card-brand p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-brand-deep truncate">{seq.name}</p>
                            <p class="mt-1 text-xs font-mono text-brand-secondary break-all">{seq.pattern}</p>
                            <p class="mt-2 text-xs text-brand-secondary">{typeLabel(seq.type)}</p>
                            {#if seq.is_system}
                                <p class="mt-1 text-[11px] text-brand-secondary/80">Sequenza di sistema</p>
                            {/if}
                        </div>
                        <div class="flex flex-col items-end gap-1.5">
                            <Button class="text-xs font-medium text-brand-accent hover:underline" onclick={() => openEdit(seq)}>
                                Modifica
                            </Button>
                            {#if !seq.is_system}
                                <Button class="text-xs font-medium text-red-700 hover:text-red-800" onclick={() => handleDelete(seq)}>
                                    Elimina
                                </Button>
                            {/if}
                        </div>
                    </div>
                </article>
            {:else}
                <div class="card-brand p-6 text-center text-sm text-brand-secondary/70">
                    Nessun sezionale ancora creato.
                </div>
            {/each}
        </section>

        {#if sequences.last_page > 1}
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between mt-4 text-sm">
                <p class="text-brand-secondary/80">
                    {sequences.from}–{sequences.to} di {sequences.total} sequenze
                </p>
                <div class="flex gap-1 flex-wrap">
                    {#each sequences.links as link}
                        {#if link.url}
                            <a
                                href={link.url}
                                class="px-3 py-1.5 rounded-lg text-sm font-medium transition-colors {link.active ? 'bg-brand-deep text-white' : 'text-brand-secondary hover:bg-surface-muted'}"
                            >
                                {@html link.label}
                            </a>
                        {/if}
                    {/each}
                </div>
            </div>
        {/if}

        <Dialog bind:open={dialogOpen} title={isEditing ? 'Modifica sezionale' : 'Nuovo sezionale'}>
            <div class="space-y-4">
                <label class="block">
                    <span class="text-sm font-medium text-brand-deep">Nome</span>
                    <Input
                        class="mt-1 block w-full rounded-lg border border-border px-3 py-2 text-sm"
                        type="text"
                        bind:value={form.name}
                    />
                    {#if fieldError('name')}
                        <span class="text-red-600 text-xs mt-0.5 block">{fieldError('name')}</span>
                    {/if}
                </label>

                <label class="block">
                    <span class="text-sm font-medium text-brand-deep">Tipo</span>
                    <Select
                        useNative
                        class="mt-1 block w-full rounded-lg border border-border px-3 py-2 text-sm bg-white"
                        bind:value={form.type}
                        disabled={isEditing && editingId}
                    >
                        {#each typeOptions as opt}
                            <option value={opt.value}>{opt.label}</option>
                        {/each}
                    </Select>
                    {#if fieldError('type')}
                        <span class="text-red-600 text-xs mt-0.5 block">{fieldError('type')}</span>
                    {/if}
                </label>

                <label class="block">
                    <span class="text-sm font-medium text-brand-deep">Formato</span>
                    <Input
                        class="mt-1 block w-full rounded-lg border border-border px-3 py-2 text-sm font-mono"
                        type="text"
                        bind:value={form.pattern}
                    />
                    <span class="text-xs text-brand-secondary/80 mt-0.5 block">Usa {'{SEQ}'} per il progressivo, {'{ANNO}'} per l'anno.</span>
                    {#if fieldError('pattern')}
                        <span class="text-red-600 text-xs mt-0.5 block">{fieldError('pattern')}</span>
                    {/if}
                </label>
            </div>

            <div class="flex justify-end mt-6">
                <Button class="btn-brand text-sm" onclick={handleSubmit} disabled={form.processing}>
                    {form.processing ? 'Salvataggio...' : isEditing ? 'Aggiorna' : 'Crea'}
                </Button>
            </div>
        </Dialog>

        <Dialog
            bind:open={deleteDialogOpen}
            title="Conferma eliminazione"
            description={sequenceToDelete ? `Eliminare il sezionale \"${sequenceToDelete.name}\"?` : ''}
            confirmText="Elimina"
            variant="danger"
            onConfirm={confirmDelete}
        />
    </div>
</Authenticated>
