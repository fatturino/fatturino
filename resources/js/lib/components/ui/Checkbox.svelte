<script lang="ts">
    import { Checkbox as CheckboxPrimitive } from "bits-ui";
    import Check from "phosphor-svelte/lib/Check";

    let {
        checked = $bindable(false),
        disabled = false,
        isDisabled = undefined as boolean | undefined,
        ariaLabel = undefined as string | undefined,
        class: className = "",
        name = undefined as string | undefined,
        value = "on",
        ...restProps
    } = $props();
</script>

<CheckboxPrimitive.Root
    bind:checked
    disabled={disabled || isDisabled}
    aria-label={ariaLabel}
    {name}
    {value}
    class={`inline-flex size-5 min-h-5 min-w-5 items-center justify-center rounded border border-brand-secondary/30 bg-white data-[state=checked]:bg-brand data-[state=checked]:border-brand ${className}`.trim()}
    {...restProps}
>
    <Check class="size-3 text-white" />
</CheckboxPrimitive.Root>
