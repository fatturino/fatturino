<script>
    import Authenticated from '$layouts/Authenticated.svelte'
    import Button from '$lib/components/ui/Button.svelte'

    let { contacts = { data: [], current_page: 1, last_page: 1, from: 0, to: 0, total: 0, links: [] }, search: initialSearch = '' } = $props()

    let searchValue = $state(initialSearch)

    function submitSearch() {
        const url = new URL(window.location.href)
        if (searchValue) {
            url.searchParams.set('search', searchValue)
        } else {
            url.searchParams.delete('search')
        }
        window.location.href = url.toString()
    }
</script>

<Authenticated>
    {#snippet headerActions()}
        <a href="/contacts/create" class="btn-brand text-sm">Nuovo contatto</a>
    {/snippet}

    <div class="page-shell pb-24 sm:pb-6 w-full">
        <section class="card-brand p-4 sm:p-5 mb-6">
            <div class="flex flex-col gap-3 sm:flex-row">
                <div class="flex-1 min-w-0">
                    <label class="sr-only" for="contact-search">Cerca contatti</label>
                    <input
                        id="contact-search"
                        type="text"
                        class="block w-full rounded-lg border border-border px-3 py-2 text-sm"
                        placeholder="Cerca per nome, P.IVA o email"
                        bind:value={searchValue}
                        onkeydown={(e) => { if (e.key === 'Enter') submitSearch() }}
                    />
                </div>
                <div class="flex items-center gap-2">
                    <Button class="btn-outline text-sm" onclick={submitSearch}>Cerca</Button>
                </div>
            </div>
        </section>

        <div class="card-brand overflow-hidden hidden md:block">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-border-light bg-surface-muted text-left">
                        <th class="px-4 py-3 font-semibold text-brand-secondary text-xs uppercase tracking-wider w-10">#</th>
                        <th class="px-4 py-3 font-semibold text-brand-secondary text-xs uppercase tracking-wider">Nome</th>
                        <th class="px-4 py-3 font-semibold text-brand-secondary text-xs uppercase tracking-wider">P.IVA</th>
                        <th class="px-4 py-3 font-semibold text-brand-secondary text-xs uppercase tracking-wider">Email</th>
                        <th class="px-4 py-3 font-semibold text-brand-secondary text-xs uppercase tracking-wider">Città</th>
                        <th class="px-4 py-3 font-semibold text-brand-secondary text-xs uppercase tracking-wider text-right">Azioni</th>
                    </tr>
                </thead>
                <tbody>
                    {#each contacts.data as contact}
                        <tr class="border-b border-border-light hover:bg-surface-muted/70 transition-colors">
                            <td class="px-4 py-3 text-brand-secondary/60 tabular-nums">{contact.id}</td>
                            <td class="px-4 py-3 font-medium text-brand-deep max-w-48 truncate">{contact.name}</td>
                            <td class="px-4 py-3 text-brand-secondary whitespace-nowrap">{contact.vat_number ?? '—'}</td>
                            <td class="px-4 py-3 text-brand-secondary">{contact.email ?? '—'}</td>
                            <td class="px-4 py-3 text-brand-secondary">{contact.city ?? '—'}</td>
                            <td class="px-4 py-3 text-right">
                                <a href="/contacts/{contact.id}/edit" class="text-xs font-medium text-brand-accent hover:underline">Modifica</a>
                            </td>
                        </tr>
                    {:else}
                        <tr>
                            <td colspan="6" class="px-4 py-12 text-center text-brand-secondary/60">
                                {initialSearch ? 'Nessun contatto trovato.' : 'Nessun contatto ancora creato.'}
                            </td>
                        </tr>
                    {/each}
                </tbody>
            </table>
        </div>

        <div class="md:hidden space-y-3">
            {#each contacts.data as contact}
                <article class="card-brand p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-brand-deep truncate">{contact.name}</p>
                            <p class="mt-1 text-xs text-brand-secondary">#{contact.id}</p>
                            <p class="mt-1 text-xs text-brand-secondary">{contact.vat_number ?? 'P.IVA non inserita'}</p>
                            <p class="mt-1 text-xs text-brand-secondary truncate">{contact.email ?? 'Email non inserita'}</p>
                            <p class="mt-1 text-xs text-brand-secondary">{contact.city ?? 'Città non inserita'}</p>
                        </div>
                        <a href="/contacts/{contact.id}/edit" class="text-xs font-medium text-brand-accent hover:underline">Modifica</a>
                    </div>
                </article>
            {:else}
                <div class="card-brand p-6 text-center text-sm text-brand-secondary/60">
                    {initialSearch ? 'Nessun contatto trovato.' : 'Nessun contatto ancora creato.'}
                </div>
            {/each}
        </div>

        {#if contacts.last_page > 1}
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between mt-4 text-sm">
                <p class="text-brand-secondary/80">
                    {contacts.from}–{contacts.to} di {contacts.total} contatti
                </p>
                <div class="flex gap-1 flex-wrap">
                    {#each contacts.links as link}
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
    </div>
</Authenticated>
