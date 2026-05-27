<script>
    import Authenticated from '$layouts/Authenticated.svelte'
    import Button from '$lib/components/ui/Button.svelte'

    let { providerName = '', conservationAcknowledged = false } = $props()

    function acknowledge() {
        const form = document.createElement('form')
        form.method = 'POST'
        form.action = '/electronic-invoice-settings/acknowledge-conservation'
        document.body.appendChild(form)
        form.submit()
    }
</script>

<Authenticated>
    <div class="page-shell pb-24 sm:pb-6 w-full space-y-4">
        <section class="card-brand p-2 sm:p-3">
            <div class="grid grid-cols-2 gap-2">
                <a href="#conservazione" class="rounded-lg border border-border-light bg-white px-3 py-2 text-left text-sm font-medium text-brand-deep hover:bg-surface-muted">
                    Conservazione
                </a>
                <a href="#provider" class="rounded-lg border border-border-light bg-white px-3 py-2 text-left text-sm font-medium text-brand-deep hover:bg-surface-muted">
                    Provider SDI
                </a>
            </div>
        </section>

        <!-- Conservation Banner -->
        <section id="conservazione">
        {#if conservationAcknowledged}
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3 sm:p-4 flex items-start gap-3">
                <span class="text-emerald-600 mt-0.5">&#10003;</span>
                <div>
                    <p class="text-sm font-medium text-emerald-800">Obbligo di conservazione - Preso visione</p>
                    <p class="text-xs text-emerald-700 mt-0.5">Hai dichiarato di aver preso visione degli obblighi di conservazione delle fatture elettroniche.</p>
                </div>
            </div>
        {:else}
            <div class="card-brand p-3 sm:p-4">
                <p class="text-sm font-medium text-brand-deep mb-2">Obbligo di conservazione</p>
                <p class="text-xs text-brand-secondary/80 mb-3">Le fatture elettroniche devono essere conservate per 10 anni ai sensi dell'art. 39 del DPR 633/1972. La conservazione è a carico del contribuente.</p>
                <div class="flex flex-wrap items-center gap-2">
                    <a href="https://ivaservizi.agenziaentrate.gov.it" target="_blank" class="btn-outline text-xs">
                        Apri Agenzia Entrate &nearr;
                    </a>
                    <Button class="btn-brand text-xs" onclick={acknowledge}>
                        Ho preso visione
                    </Button>
                </div>
            </div>
        {/if}
        </section>

        <!-- No provider -->
        <section id="provider" class="card-brand p-5 sm:p-6 text-center">
            <p class="text-lg font-semibold text-brand-deep mb-2">Nessun provider SDI configurato</p>
            <p class="text-sm text-brand-secondary/60">Installa un plugin per la fatturazione elettronica per configurare l'invio e la ricezione delle fatture tramite SDI.</p>
        </section>
    </div>
</Authenticated>
