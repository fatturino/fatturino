<script>
    import Authenticated from '$layouts/Authenticated.svelte'
    import Button from '$lib/components/ui/Button.svelte'

    let { inboundLogs = [], outboundLogs = [] } = $props()

    let activeTab = $state('inbound')

    function formatDate(value) {
        if (!value) return '-'
        return new Date(value).toLocaleString('it-IT')
    }

    function inboundBadgeClass(status) {
        switch (status) {
            case 'processed': return 'badge-sent'
            case 'failed': return 'badge-draft'
            default: return 'badge-neutral'
        }
    }

    function outboundBadgeClass(status) {
        switch (status) {
            case 'delivered':
            case 'accepted':
            case 'expired':
                return 'badge-sent'
            case 'not_delivered':
                return 'badge-overdue'
            case 'rejected':
            case 'refused':
            case 'error':
                return 'badge-draft'
            default:
                return 'badge-neutral'
        }
    }
</script>

<Authenticated>
    <div class="page-shell pb-24 sm:pb-6 w-full space-y-4">
        <section class="card-brand p-2 sm:p-3">
            <div class="grid grid-cols-2 gap-2">
                <Button class="rounded-lg border px-3 py-2 text-left text-sm transition-colors {activeTab === 'inbound' ? 'border-brand-deep bg-brand-deep text-white' : 'border-border-light bg-white text-brand-deep hover:bg-surface-muted'}" onclick={() => activeTab = 'inbound'}>
                    Inbound ({inboundLogs.length})
                </Button>
                <Button class="rounded-lg border px-3 py-2 text-left text-sm transition-colors {activeTab === 'outbound' ? 'border-brand-deep bg-brand-deep text-white' : 'border-border-light bg-white text-brand-deep hover:bg-surface-muted'}" onclick={() => activeTab = 'outbound'}>
                    Outbound ({outboundLogs.length})
                </Button>
            </div>
        </section>

        {#if activeTab === 'inbound'}
            <section class="card-brand overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-border-light">
                        <thead class="bg-surface-muted">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-brand-secondary/70">Data</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-brand-secondary/70">Evento</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-brand-secondary/70">UUID</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-brand-secondary/70">Notifica</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-brand-secondary/70">Stato</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-brand-secondary/70">Tentativi</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-brand-secondary/70">Doc</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-brand-secondary/70">Errore</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border-light bg-white">
                            {#each inboundLogs as log}
                                <tr>
                                    <td class="px-4 py-3 text-xs text-brand-secondary/80 whitespace-nowrap">{formatDate(log.created_at)}</td>
                                    <td class="px-4 py-3 text-sm text-brand-deep">{log.event_name}</td>
                                    <td class="px-4 py-3 text-xs text-brand-secondary/80 max-w-44 truncate">{log.source_uuid ?? '-'}</td>
                                    <td class="px-4 py-3 text-xs text-brand-secondary/80">{log.notification_type ?? '-'}</td>
                                    <td class="px-4 py-3"><span class="inline-block px-2 py-1 rounded-full text-xs font-medium {inboundBadgeClass(log.processing_status)}">{log.processing_status}</span></td>
                                    <td class="px-4 py-3 text-xs text-brand-secondary/80">{log.attempts}</td>
                                    <td class="px-4 py-3 text-xs text-brand-secondary/80">{log.linked_fiscal_document_id ?? '-'}</td>
                                    <td class="px-4 py-3 text-xs text-red-700 max-w-80 truncate">{log.error_message ?? '-'}</td>
                                </tr>
                            {:else}
                                <tr>
                                    <td colspan="8" class="px-4 py-6 text-sm text-brand-secondary/70">Nessun log inbound presente.</td>
                                </tr>
                            {/each}
                        </tbody>
                    </table>
                </div>
            </section>
        {:else}
            <section class="card-brand overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-border-light">
                        <thead class="bg-surface-muted">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-brand-secondary/70">Data</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-brand-secondary/70">Evento</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-brand-secondary/70">Stato SDI</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-brand-secondary/70">UUID</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-brand-secondary/70">Doc</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-brand-secondary/70">Messaggio</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border-light bg-white">
                            {#each outboundLogs as log}
                                <tr>
                                    <td class="px-4 py-3 text-xs text-brand-secondary/80 whitespace-nowrap">{formatDate(log.created_at)}</td>
                                    <td class="px-4 py-3 text-sm text-brand-deep">{log.event_type}</td>
                                    <td class="px-4 py-3"><span class="inline-block px-2 py-1 rounded-full text-xs font-medium {outboundBadgeClass(log.status)}">{log.status}</span></td>
                                    <td class="px-4 py-3 text-xs text-brand-secondary/80 max-w-44 truncate">{log.source_uuid ?? '-'}</td>
                                    <td class="px-4 py-3 text-xs text-brand-secondary/80">{log.fiscal_document_id}</td>
                                    <td class="px-4 py-3 text-xs text-brand-secondary/80 max-w-80 truncate">{log.message ?? '-'}</td>
                                </tr>
                            {:else}
                                <tr>
                                    <td colspan="6" class="px-4 py-6 text-sm text-brand-secondary/70">Nessun log outbound presente.</td>
                                </tr>
                            {/each}
                        </tbody>
                    </table>
                </div>
            </section>
        {/if}
    </div>
</Authenticated>
