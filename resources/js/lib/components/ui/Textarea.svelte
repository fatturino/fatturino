<script lang="ts">
    let {
        class: className = "",
        value = $bindable(""),
        state = "default" as "default" | "error",
        isDisabled = false,
        ariaLabel = undefined as string | undefined,
        ...restProps
    } = $props();
</script>

<textarea class={`input-field textarea-field min-h-36 resize-y ${state === "error" ? "input-error" : ""} ${className}`.trim()} bind:value disabled={isDisabled} aria-label={ariaLabel} {...restProps}></textarea>
