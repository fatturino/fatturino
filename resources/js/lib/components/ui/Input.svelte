<script lang="ts">
    let {
        class: className = "",
        type = "text",
        value = $bindable(""),
        checked = $bindable(false),
        state = "default" as "default" | "error",
        isDisabled = undefined as boolean | undefined,
        ariaLabel = undefined as string | undefined,
        ...restProps
    } = $props();

    const resolvedClass = $derived(`input-field ${state === "error" ? "input-error" : ""} ${className}`.trim());
</script>

{#if type === "checkbox"}
    <input class={className} type="checkbox" bind:checked disabled={isDisabled} aria-label={ariaLabel} {...restProps} />
{:else if type === "file"}
    <input class={resolvedClass} {type} disabled={isDisabled} aria-label={ariaLabel} {...restProps} />
{:else}
    <input class={resolvedClass} {type} bind:value disabled={isDisabled} aria-label={ariaLabel} {...restProps} />
{/if}
