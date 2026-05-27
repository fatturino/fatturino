<script lang="ts">
    import { Switch as SwitchPrimitive } from "bits-ui";

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

<SwitchPrimitive.Root
    bind:checked
    disabled={disabled || isDisabled}
    aria-label={ariaLabel}
    {name}
    {value}
    class={`inline-flex h-7 w-12 shrink-0 cursor-pointer items-center rounded-full border border-transparent bg-slate-300 p-0.5 transition-colors data-[state=checked]:bg-brand data-[disabled]:cursor-not-allowed data-[disabled]:opacity-50 ${className}`.trim()}
    {...restProps}
>
    <SwitchPrimitive.Thumb
        class="pointer-events-none block h-5 w-5 rounded-full bg-white shadow-sm ring-0 transition-transform data-[state=checked]:translate-x-5 data-[state=unchecked]:translate-x-0"
    />
</SwitchPrimitive.Root>
