<script>
    import Authenticated from '$layouts/Authenticated.svelte'
    import Dialog from '$lib/components/ui/Dialog.svelte'

    let { plugins = [] } = $props()
    let confirmOpen = $state(false)
    let pluginToDeactivate = $state(null)

    function toggle(plugin) {
        const url = plugin.active ? `/plugins/${plugin.id}/deactivate` : `/plugins/${plugin.id}/activate`
        const form = document.createElement('form')
        form.method = 'POST'; form.action = url
        document.body.appendChild(form); form.submit()
    }

    function requestToggle(plugin) {
        if (!plugin.active) {
            toggle(plugin)
            return
        }

        pluginToDeactivate = plugin
        confirmOpen = true
    }

    function confirmDeactivate() {
        if (!pluginToDeactivate) return
        toggle(pluginToDeactivate)
    }
</script>

<Dialog
    bind:open={confirmOpen}
    title="Conferma disattivazione plugin"
    description={pluginToDeactivate ? `Disattivare il plugin \"${pluginToDeactivate.name}\"?` : 'Disattivare questo plugin?'}
    confirmText="Disattiva"
    variant="danger"
    onConfirm={confirmDeactivate}
/>

<Authenticated>
    <div class="p-6 w-full">
        <div class="mb-6">
            <p class="text-sm text-brand-secondary/60 mt-1">Gestisci i plugin installati nel sistema.</p>
        </div>

        {#if plugins.length === 0}
            <div class="card-brand p-12 text-center">
                <p class="text-brand-secondary/40 text-sm">Nessun plugin installato.</p>
            </div>
        {:else}
            <div class="mb-4 bg-sky-50 border border-sky-200 rounded-xl p-3 text-sm text-sky-800">
                Riavvia il server dopo aver attivato o disattivato un plugin.
            </div>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                {#each plugins as plugin}
                    <div class="card-brand p-5">
                        <div class="flex items-start justify-between mb-2">
                            <div>
                                <h3 class="text-sm font-semibold text-brand-deep">{plugin.name}</h3>
                                {#if plugin.version}<span class="text-xs text-brand-secondary/40">v{plugin.version}</span>{/if}
                            </div>
                            <span class="inline-block px-2 py-0.5 rounded-full text-xs font-medium {plugin.active ? 'badge-sent' : 'bg-brand-secondary/5 text-brand-secondary/60'}">
                                {plugin.active ? 'Attivo' : 'Inattivo'}
                            </span>
                        </div>
                        {#if plugin.description}<p class="text-xs text-brand-secondary/60 mb-3">{plugin.description}</p>{/if}
                        {#if plugin.author}<p class="text-xs text-brand-secondary/40 mb-3">di {plugin.author}</p>{/if}
                        {#if plugin.locked}
                            <span class="text-xs text-amber-600 font-medium">Bloccato</span>
                        {:else}
                            <button class="rounded-lg px-4 py-1.5 text-xs font-medium transition-colors {plugin.active ? 'bg-red-50 text-red-700 hover:bg-red-100' : 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100'}" onclick={() => requestToggle(plugin)}>
                                {plugin.active ? 'Disattiva' : 'Attiva'}
                            </button>
                        {/if}
                    </div>
                {/each}
            </div>
        {/if}
    </div>
</Authenticated>
