import { writable } from 'svelte/store'

export const headerActionsStore = writable(null)

export function setHeaderActions(config) {
    headerActionsStore.set({
        indexPath: config?.indexPath ?? null,
        onSubmit: config?.onSubmit ?? null,
        submitLabel: config?.submitLabel ?? 'Salva',
        processing: Boolean(config?.processing),
        isReadOnly: Boolean(config?.isReadOnly),
        isDisabled: Boolean(config?.isDisabled),
        variant: config?.variant ?? 'brand',
        ariaLabel: config?.ariaLabel ?? 'Salva modifiche',
    })
}

export function clearHeaderActions() {
    headerActionsStore.set(null)
}
