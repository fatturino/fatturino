import { writable } from 'svelte/store'

export const toast = writable(null)

function normalizeToastPayload(payloadOrMessage, type = 'success', duration = 3000) {
    if (typeof payloadOrMessage === 'string') {
        return {
            id: Date.now(),
            type,
            title: type === 'error' ? 'Errore' : 'Operazione completata',
            message: payloadOrMessage,
            action: null,
            duration,
        }
    }

    return {
        id: payloadOrMessage?.id ?? Date.now(),
        type: payloadOrMessage?.type ?? type,
        title: payloadOrMessage?.title ?? (payloadOrMessage?.type === 'error' ? 'Errore' : 'Operazione completata'),
        message: payloadOrMessage?.message ?? '',
        action: payloadOrMessage?.action ?? null,
        duration: payloadOrMessage?.duration ?? duration,
    }
}

export function showToast(payloadOrMessage, type = 'success', duration = 3000) {
    const payload = normalizeToastPayload(payloadOrMessage, type, duration)
    toast.set(payload)
    setTimeout(() => toast.set(null), duration)
}
