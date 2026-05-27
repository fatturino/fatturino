<script lang="ts">
    let { class: className = "", value = $bindable<number | null>(null), ...restProps } = $props();
</script>

<input class={className} type="number" bind:value {...restProps} />
