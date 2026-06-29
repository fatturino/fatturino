<script>
    import { toast } from '$lib/toast.js'

    let current = $state(null)
    const isError = $derived(current?.type === 'error')

    toast.subscribe(v => current = v)
</script>

{#if current}
    <div
        class="fixed top-5 right-5 z-70 transition-transforn duration-300 border-2 rounded-xl px-4 py-3 shadow-2xl text-sm max-w-sm min-w-72 {isError ? 'bg-red-50 border-red-300 text-red-900' : 'bg-emerald-50 border-emerald-300 text-emerald-900'}"
        role={isError ? 'alert' : 'status'}
        aria-live={isError ? 'assertive' : 'polite'}
        aria-atomic="true"
    >
        <p class="font-semibold">{current.title}</p>
        <p class="mt-0.5">{current.message}</p>
        {#if current.action}
            <a class="mt-2 inline-flex text-xs font-semibold underline" href={current.action.href}>{current.action.label}</a>
        {/if}
    </div>
{/if}
