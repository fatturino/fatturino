<script lang="ts">
    let {
        label = "",
        hint = "",
        error = "",
        required = false,
        forId = "",
        ariaLive = "polite" as "off" | "polite" | "assertive",
        class: className = "",
        children,
    } = $props();
</script>

<label class={className} for={forId}>
    {#if label}
        <span class="text-sm font-medium text-brand-deep">{label}{required ? " *" : ""}</span>
    {/if}
    {@render children?.()}
    {#if error}
        <span class="text-red-600 text-xs mt-0.5 block" role="alert" aria-live={ariaLive}>{error}</span>
    {:else if hint}
        <span class="text-xs text-brand-secondary/40 mt-0.5 block">{hint}</span>
    {/if}
</label>
