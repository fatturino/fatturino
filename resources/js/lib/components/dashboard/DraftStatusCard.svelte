<script>
    let {
        draftCount = 0,
        readyForSdiCount = 0,
    } = $props()
</script>

{#if draftCount > 0 || readyForSdiCount > 0}
    <div class="mt-8 card-brand p-5 flex items-center gap-6">
        {#if draftCount > 0}
            <a href="/sell-invoices?status=draft" class="text-sm text-brand-secondary hover:underline">
                <span class="font-semibold text-brand-deep">{draftCount}</span> bozze da completare
            </a>
        {/if}
        {#if readyForSdiCount > 0}
            <a href="/sell-invoices?status=ready" class="text-sm text-brand-secondary hover:underline">
                <span class="font-semibold text-brand-deep">{readyForSdiCount}</span> pronte per l'invio SDI
            </a>
        {/if}
    </div>
{/if}
