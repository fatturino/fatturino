<script>
    import Authenticated from '$layouts/Authenticated.svelte'
    import { page } from '@inertiajs/svelte'
    import Button from '$lib/components/ui/Button.svelte'
    import Input from '$lib/components/ui/Input.svelte'
    import Checkbox from '$lib/components/ui/Checkbox.svelte'
    import { FileText, Download, ArrowsClockwise, UserPlus, Buildings, Cloud } from 'phosphor-svelte'
    import Dialog from '$lib/components/ui/Dialog.svelte'

    let { importResult = null, errors = {} } = $props()

    let dialogOpen = $state(false)
    let importType = $state('')
    let updateExisting = $state(false)

    const importTypes = {
        xml_sales: { title: 'Fatture di vendita', desc: 'Importa XML di fatture elettroniche emesse (TD01).', accept: '.xml,.p7m,.zip', fileLabel: 'File XML o ZIP' },
        xml_purchase: { title: 'Fatture di acquisto', desc: 'Importa XML di fatture elettroniche ricevute (TD01).', accept: '.xml,.p7m,.zip', fileLabel: 'File XML o ZIP' },
        xml_self_invoice: { title: 'Autofatture', desc: 'Importa XML di autofatture (TD17-TD29).', accept: '.xml,.p7m,.zip', fileLabel: 'File XML o ZIP' },
        fattura24_contacts: { title: 'Contatti Fattura24', desc: 'Importa contatti da esportazione CSV di Fattura24.', accept: '.csv,.txt', fileLabel: 'File CSV' },
    }
    const selfInvoiceImportEnabled = $derived(page.props.selfInvoiceImportEnabled ?? true)
    const xmlImportTypes = $derived(selfInvoiceImportEnabled
        ? ['xml_sales', 'xml_purchase', 'xml_self_invoice']
        : ['xml_sales', 'xml_purchase'])
    const csrfToken = $derived(
        typeof document !== 'undefined'
            ? (document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '')
            : ''
    )

    function openModal(type) {
        importType = type
        updateExisting = false
        dialogOpen = true
    }

    function typeLabel(t) {
        switch (t) {
            case 'xml_sales': return 'Fatture di vendita'
            case 'xml_purchase': return 'Fatture di acquisto'
            case 'xml_self_invoice': return 'Autofatture'
            case 'fattura24_contacts': return 'Contatti Fattura24'
            default: return t
        }
    }
</script>

<Authenticated>
    {#snippet headerActions()}
        <a href="/sell-invoices" class="btn-outline text-sm">Vai alle fatture</a>
        <a href="/contacts/create" class="btn-brand text-sm">Nuovo contatto</a>
    {/snippet}

    <div class="page-shell pb-24 sm:pb-6 w-full">
        <section class="card-brand p-4 sm:p-6 mb-6">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <p class="text-xs uppercase tracking-wide text-brand-secondary/70">Import data</p>
                    <h2 class="mt-1 text-2xl font-semibold text-brand-deep">Importa documenti e contatti</h2>
                    <p class="mt-1 text-sm text-brand-secondary/80">Carica XML o CSV per allineare rapidamente l'anagrafica e le fatture.</p>
                </div>
                <div class="grid grid-cols-2 gap-3 lg:w-auto lg:grid-cols-2">
                    <article class="rounded-xl border border-border-light bg-surface-muted p-4">
                        <p class="text-xs uppercase tracking-wide text-brand-secondary/70">Import XML</p>
                        <p class="mt-2 text-xl font-semibold text-brand-deep">{xmlImportTypes.length}</p>
                        <p class="text-xs text-brand-secondary/80">{selfInvoiceImportEnabled ? 'Vendita, acquisto, autofatture' : 'Vendita, acquisto'}</p>
                    </article>
                    <article class="rounded-xl border border-border-light bg-surface-muted p-4">
                        <p class="text-xs uppercase tracking-wide text-brand-secondary/70">Import piattaforme</p>
                        <p class="mt-2 text-xl font-semibold text-brand-deep">1</p>
                        <p class="text-xs text-brand-secondary/80">Fattura24 disponibile</p>
                    </article>
                </div>
            </div>
        </section>

        <!-- Result -->
        {#if importResult}
            <section class="card-brand p-4 sm:p-5 mb-6 {importResult.errors && importResult.errors.length > 0 ? 'border-amber-200 bg-amber-50' : 'border-emerald-200 bg-emerald-50'}">
                <p class="text-sm font-semibold mb-2">
                    {importResult.errors && importResult.errors.length > 0 ? 'Import completato con errori' : 'Import completato'}
                    <span class="font-normal text-brand-secondary/60"> - {typeLabel(importResult.type)}</span>
                </p>
                <div class="flex gap-4 text-sm flex-wrap">
                    {#if importResult.stats?.invoices_imported}
                        <span class="font-medium">{importResult.stats.invoices_imported} fatture</span>
                    {/if}
                    {#if importResult.stats?.lines_imported}
                        <span class="font-medium">{importResult.stats.lines_imported} righe</span>
                    {/if}
                    {#if importResult.stats?.contacts_imported}
                        <span class="font-medium">{importResult.stats.contacts_imported} contatti</span>
                    {/if}
                    {#if importResult.stats?.updated}
                        <span class="font-medium">{importResult.stats.updated} aggiornati</span>
                    {/if}
                </div>
                {#if importResult.errors && importResult.errors.length > 0}
                    <div class="mt-3 text-sm text-red-700 space-y-1">
                        {#each importResult.errors as error}
                            <p>• {error}</p>
                        {/each}
                    </div>
                {/if}
            </section>
        {/if}

        {#if !selfInvoiceImportEnabled}
            <section class="card-brand p-4 sm:p-5 mb-6 border-amber-200 bg-amber-50">
                <p class="text-sm font-semibold text-amber-900">Autofatture disabilitate per RF19</p>
                <p class="mt-1 text-sm text-amber-800">Puoi riattivarle da Dati Azienda se hai operazioni estero che richiedono TD17/TD18/TD19.</p>
            </section>
        {/if}

        <section class="card-brand p-4 sm:p-5 mb-6">
            <div class="mb-3 grid grid-cols-2 gap-2 lg:grid-cols-4">
                <button
                    type="button"
                    class="rounded-lg border border-brand-deep bg-brand-deep px-3 py-2 text-left text-sm text-white"
                >
                    <span class="font-medium">Import XML</span>
                    <span class="ml-2 text-xs opacity-80">3 opzioni</span>
                </button>
                <button
                    type="button"
                    class="rounded-lg border border-border-light bg-white px-3 py-2 text-left text-sm text-brand-deep hover:bg-surface-muted"
                >
                    <span class="font-medium">Piattaforme</span>
                    <span class="ml-2 text-xs opacity-70">1 disponibile</span>
                </button>
            </div>

            <div class="hidden md:block overflow-hidden rounded-xl border border-border-light">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-border-light bg-surface-muted text-left">
                            <th class="px-4 py-3 font-semibold text-brand-secondary text-xs uppercase tracking-wider">Tipo import</th>
                            <th class="px-4 py-3 font-semibold text-brand-secondary text-xs uppercase tracking-wider">Descrizione</th>
                            <th class="px-4 py-3 font-semibold text-brand-secondary text-xs uppercase tracking-wider">Formato</th>
                            <th class="px-4 py-3 font-semibold text-brand-secondary text-xs uppercase tracking-wider text-right">Azione</th>
                        </tr>
                    </thead>
                    <tbody>
                        {#each xmlImportTypes as type}
                            <tr class="border-b border-border-light hover:bg-surface-muted/70 transition-colors">
                                <td class="px-4 py-3 font-semibold text-brand-deep">{importTypes[type].title}</td>
                                <td class="px-4 py-3 text-brand-secondary">{importTypes[type].desc}</td>
                                <td class="px-4 py-3 text-brand-secondary">{importTypes[type].accept}</td>
                                <td class="px-4 py-3 text-right">
                                    <Button class="btn-brand text-xs" onclick={() => openModal(type)}>Importa</Button>
                                </td>
                            </tr>
                        {/each}
                    </tbody>
                </table>
            </div>

            <div class="space-y-3 md:hidden">
                {#each xmlImportTypes as type}
                    <article class="rounded-xl border border-border-light p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold text-brand-deep">{importTypes[type].title}</p>
                                <p class="mt-1 text-xs text-brand-secondary/80">{importTypes[type].desc}</p>
                            </div>
                            <span class="text-brand-secondary/50">
                                {#if type === 'xml_sales'}
                                    <FileText size={22} />
                                {:else if type === 'xml_purchase'}
                                    <Download size={22} />
                                {:else}
                                    <ArrowsClockwise size={22} />
                                {/if}
                            </span>
                        </div>
                        <p class="mt-3 text-xs text-brand-secondary/70">Formati: {importTypes[type].accept}</p>
                        <div class="mt-3">
                            <Button class="btn-brand text-xs" onclick={() => openModal(type)}>Importa</Button>
                        </div>
                    </article>
                {/each}
            </div>
        </section>

        <section class="card-brand p-4 sm:p-5">
            <h2 class="text-sm font-semibold text-brand-secondary/60 uppercase tracking-wide mb-4">Piattaforme</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="rounded-xl border border-border-light p-5 flex flex-col items-center text-center">
                    <span class="text-brand-secondary/40 mb-3">
                        <UserPlus size={32} />
                    </span>
                    <h3 class="text-sm font-semibold text-brand-deep mb-1">Contatti Fattura24</h3>
                    <p class="text-xs text-brand-secondary/60 mb-4">Importa contatti da esportazione CSV di Fattura24.</p>
                    <Button class="btn-brand text-sm w-full" onclick={() => openModal('fattura24_contacts')}>Importa</Button>
                </div>
                <div class="rounded-xl border border-border-light p-5 flex flex-col items-center text-center opacity-50">
                    <span class="text-brand-secondary/40 mb-3">
                        <Buildings size={32} />
                    </span>
                    <h3 class="text-sm font-semibold text-brand-deep mb-1">Aruba</h3>
                    <p class="text-xs text-brand-secondary/60 mb-4">Prossimamente.</p>
                    <Button class="rounded-lg bg-brand-secondary/20 px-4 py-2 text-sm font-medium text-brand-secondary/60 w-full cursor-not-allowed" disabled>Prossimamente</Button>
                </div>
                <div class="rounded-xl border border-border-light p-5 flex flex-col items-center text-center opacity-50">
                    <span class="text-brand-secondary/40 mb-3">
                        <Cloud size={32} />
                    </span>
                    <h3 class="text-sm font-semibold text-brand-deep mb-1">Fatture in Cloud</h3>
                    <p class="text-xs text-brand-secondary/60 mb-4">Prossimamente.</p>
                    <Button class="rounded-lg bg-brand-secondary/20 px-4 py-2 text-sm font-medium text-brand-secondary/60 w-full cursor-not-allowed" disabled>Prossimamente</Button>
                </div>
            </div>
        </section>

        <!-- Modal -->
        <Dialog
            bind:open={dialogOpen}
            title={importTypes[importType]?.title ?? 'Import'}
        >
            <div class="mb-4 text-sm text-brand-secondary/60 space-y-1">
                {#if importType === 'xml_sales'}
                    <p>Importa una o più fatture elettroniche di vendita.</p>
                    <p>Formati accettati: <strong>.xml</strong> (singola fattura), <strong>.p7m</strong> (firma digitale), <strong>.zip</strong> (archivio con più file XML).</p>
                {:else if importType === 'xml_purchase'}
                    <p>Importa una o più fatture elettroniche di acquisto ricevute.</p>
                    <p>Formati accettati: <strong>.xml</strong> (singola fattura), <strong>.p7m</strong> (firma digitale), <strong>.zip</strong> (archivio con più file XML).</p>
                {:else if importType === 'xml_self_invoice'}
                    <p>Importa una o più autofatture elettroniche.</p>
                    <p>Formati accettati: <strong>.xml</strong> (singola fattura), <strong>.p7m</strong> (firma digitale), <strong>.zip</strong> (archivio con più file XML).</p>
                {:else if importType === 'fattura24_contacts'}
                    <p>Importa i contatti da un file CSV esportato da Fattura24.</p>
                    <p>Formato accettato: <strong>.csv</strong> con delimitatore punto e virgola.</p>
                {/if}
            </div>

            <form action="/imports" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="_token" value={csrfToken} />
                <Input type="hidden" name="import_type" value={importType} />

                <label class="block mb-4">
                    <span class="text-sm font-medium text-brand-deep">{importTypes[importType]?.fileLabel ?? 'File'}</span>
                    <Input class="mt-1 block w-full rounded-lg border border-brand-secondary/20 px-3 py-2 text-sm form-focus file:mr-3 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-brand-secondary/5 file:text-brand-deep hover:file:bg-brand-secondary/10"
                        type="file" name={importType.startsWith('xml') ? 'xml_file' : 'csv_file'} accept={importTypes[importType]?.accept ?? ''} required />
                    {#if errors.xml_file}<span class="text-red-600 text-xs mt-0.5 block">{errors.xml_file}</span>{/if}
                    {#if errors.csv_file}<span class="text-red-600 text-xs mt-0.5 block">{errors.csv_file}</span>{/if}
                </label>

                {#if importType === 'fattura24_contacts'}
                    <label class="flex items-center gap-2 mb-4">
                        <Checkbox name="update_existing" value="1" class="rounded border-brand-secondary/20" bind:checked={updateExisting} />
                        <span class="text-sm text-brand-deep">Aggiorna contatti esistenti</span>
                    </label>
                {/if}

                <div class="flex justify-end">
                    <Button type="submit" class="rounded-lg bg-brand px-4 py-2 text-sm font-medium text-white hover:bg-brand/90 transition-colors">Avvia import</Button>
                </div>
            </form>
        </Dialog>
    </div>
</Authenticated>
