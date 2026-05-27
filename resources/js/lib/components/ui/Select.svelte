<script lang="ts">
    import { Select as SelectPrimitive } from "bits-ui";
    import CaretDown from "phosphor-svelte/lib/CaretDown";
    import Check from "phosphor-svelte/lib/Check";

    type Option = { value: string | number; label: string; disabled?: boolean };

    let {
        value = $bindable(""),
        options = [] as Option[],
        placeholder = "Seleziona...",
        disabled = false,
        isDisabled = undefined as boolean | undefined,
        state = "default" as "default" | "error",
        ariaLabel = undefined as string | undefined,
        useNative = false,
        class: className = "",
        contentClass = "",
        children,
    } = $props();

    const resolvedDisabled = $derived(Boolean(disabled || isDisabled));
    const triggerClass = $derived(`mt-1 flex w-full items-center justify-between rounded-lg border bg-white px-3 py-2 text-sm min-h-11 form-focus ${state === "error" ? "border-red-600" : "border-brand-secondary/20"} ${className}`.trim());
</script>

{#if useNative}
    <select bind:value disabled={resolvedDisabled} aria-label={ariaLabel} class={`mt-1 block w-full rounded-lg border bg-white px-3 py-2 text-sm min-h-11 form-focus ${state === "error" ? "border-red-600" : "border-brand-secondary/20"} ${className}`.trim()}>
        {@render children?.()}
    </select>
{:else}
    <SelectPrimitive.Root type="single" bind:value disabled={resolvedDisabled}>
        <SelectPrimitive.Trigger class={triggerClass} aria-label={ariaLabel}>
            <SelectPrimitive.Value {placeholder} />
            <CaretDown class="size-4 text-brand-secondary/60" />
        </SelectPrimitive.Trigger>
        <SelectPrimitive.Portal>
            <SelectPrimitive.Content class={`z-50 min-w-[var(--bits-select-anchor-width)] rounded-lg border border-brand-secondary/20 bg-white p-1 shadow-lg ${contentClass}`}>
                <SelectPrimitive.Viewport>
                    {#each options as option}
                        <SelectPrimitive.Item
                            value={String(option.value)}
                            label={option.label}
                            disabled={option.disabled}
                            class="flex cursor-pointer items-center justify-between rounded px-2 py-1.5 text-sm outline-none hover:bg-brand-secondary/5 data-[highlighted]:bg-brand-secondary/5 data-[disabled]:cursor-not-allowed data-[disabled]:opacity-50"
                        >
                            {option.label}
                            <Check class="size-3.5 text-brand opacity-0 data-[selected]:opacity-100" />
                        </SelectPrimitive.Item>
                    {/each}
                </SelectPrimitive.Viewport>
            </SelectPrimitive.Content>
        </SelectPrimitive.Portal>
    </SelectPrimitive.Root>
{/if}
