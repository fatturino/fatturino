<script>
    import { router } from '@inertiajs/svelte'

    let {
        invoices = [],
        contactLabel = 'Cliente',
        sort = 'created_at',
        direction = 'desc',
        hasActiveFilters = false,
        emptyMessage = '',
        emptyFilteredMessage = '',
        desktopColspan = 3,
        desktopHeaders,
        desktopRow,
        mobileRow,
    } = $props()

    function toggleSort(field) {
        const url = new URL(window.location.href)
        const nextDirection = sort === field ? (direction === 'asc' ? 'desc' : 'asc') : (field === 'created_at' ? 'desc' : 'asc')
        url.searchParams.set('sort', field)
        url.searchParams.set('direction', nextDirection)
        url.searchParams.delete('page')
        router.get(`${url.pathname}${url.search}`)
    }

    function sortIndicator(field) {
        if (sort !== field) return ''
        return direction === 'asc' ? '↑' : '↓'
    }

    function formatDate(dateStr) {
        if (!dateStr) return '—'
        return new Date(dateStr).toLocaleDateString('it-IT')
    }
</script>

<section class="card-brand overflow-hidden hidden md:block">
    <table class="w-full text-sm">
        <thead>
            <tr class="border-b border-border-light bg-surface-muted text-left">
                <th class="px-4 py-3 font-semibold text-brand-secondary text-xs uppercase tracking-wider"><button type="button" class="inline-flex items-center gap-1" onclick={() => toggleSort('number')}>Numero <span>{sortIndicator('number')}</span></button></th>
                <th class="px-4 py-3 font-semibold text-brand-secondary text-xs uppercase tracking-wider"><button type="button" class="inline-flex items-center gap-1" onclick={() => toggleSort('created_at')}>Data creazione <span>{sortIndicator('created_at')}</span></button></th>
                <th class="px-4 py-3 font-semibold text-brand-secondary text-xs uppercase tracking-wider"><button type="button" class="inline-flex items-center gap-1" onclick={() => toggleSort('contact')}>{contactLabel} <span>{sortIndicator('contact')}</span></button></th>
                {@render desktopHeaders?.()}
            </tr>
        </thead>
        <tbody>
            {#each invoices as invoice}
                {@render desktopRow?.({ invoice, formatDate })}
            {:else}
                <tr>
                    <td colspan={desktopColspan} class="px-4 py-12 text-center text-brand-secondary/60">
                        {hasActiveFilters ? emptyFilteredMessage : emptyMessage}
                    </td>
                </tr>
            {/each}
        </tbody>
    </table>
</section>

<section class="md:hidden space-y-3">
    {#each invoices as invoice}
        {@render mobileRow?.({ invoice, formatDate })}
    {:else}
        <div class="card-brand p-8 text-center text-sm text-brand-secondary/60">
            {hasActiveFilters ? emptyFilteredMessage : emptyMessage}
        </div>
    {/each}
</section>
