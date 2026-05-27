<script lang="ts">
    import { Button as ButtonPrimitive } from "bits-ui";

    let {
        class: className = "",
        type = "button",
        disabled = false,
        isDisabled = undefined as boolean | undefined,
        isLoading = false,
        href = undefined as string | undefined,
        variant = "plain" as "plain" | "brand" | "outline" | "ghost" | "danger",
        size = "md" as "sm" | "md" | "lg",
        tone = "default" as "default" | "neutral",
        state = "default" as "default" | "loading",
        ariaLabel = undefined as string | undefined,
        children,
        ...restProps
    } = $props();

    const resolvedDisabled = $derived(Boolean(disabled || isDisabled || isLoading || state === "loading"));

    const variantClass = $derived(
        variant === "brand"
            ? "btn-brand"
            : variant === "outline"
              ? "btn-outline"
              : variant === "ghost"
                ? "btn-ghost"
                : variant === "danger"
                  ? "btn-danger"
                  : ""
    );

    const sizeClass = $derived(
        size === "sm"
            ? "px-3 py-2 text-sm min-h-11"
            : size === "lg"
              ? "px-5 py-3 text-base min-h-11"
              : "px-4 py-2.5 text-sm min-h-11"
    );

    const toneClass = $derived(tone === "neutral" ? "font-medium" : "");
</script>

<ButtonPrimitive.Root
    {href}
    {type}
    disabled={resolvedDisabled}
    aria-label={ariaLabel}
    aria-busy={isLoading || state === "loading"}
    class={`${variantClass} ${sizeClass} ${toneClass} ${className}`.trim()}
    {...restProps}
>
    {#if isLoading || state === "loading"}
        <span class="inline-flex items-center gap-2">
            <span class="size-3.5 animate-spin rounded-full border-2 border-current border-t-transparent" aria-hidden="true"></span>
            <span>Caricamento...</span>
        </span>
    {:else}
        {@render children?.()}
    {/if}
</ButtonPrimitive.Root>
