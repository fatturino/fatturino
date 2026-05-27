<script>
    import { ContextMenu } from 'bits-ui'

    let {
        actions = [],
        children,
    } = $props()

    function executeAction(action) {
        if (action.disabled) {
            return
        }

        if (typeof action.onSelect === 'function') {
            action.onSelect()
            return
        }

        if (action.href) {
            window.location.href = action.href
        }
    }
</script>

<ContextMenu.Root>
    <ContextMenu.Trigger>
        {#snippet child({ props })}
            {@render children?.({ triggerProps: props })}
        {/snippet}
    </ContextMenu.Trigger>
    <ContextMenu.Portal>
        <ContextMenu.Content class="z-30 min-w-[13rem] rounded-lg border border-border-light bg-white p-1 shadow-lg">
            {#each actions as action (action.id)}
                <ContextMenu.Item
                    disabled={action.disabled}
                    class="rounded-md px-3 py-2 text-xs font-medium text-brand-deep hover:bg-surface-muted data-[disabled]:text-brand-secondary/50"
                    onSelect={() => executeAction(action)}
                >
                    {action.label}
                </ContextMenu.Item>
            {/each}
        </ContextMenu.Content>
    </ContextMenu.Portal>
</ContextMenu.Root>
