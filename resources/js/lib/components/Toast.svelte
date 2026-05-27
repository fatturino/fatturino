<script>
    import { toast } from '$lib/toast.js'

    let current = $state(null)

    toast.subscribe(v => current = v)
</script>

{#if current}
    <div class="fixed bottom-6 right-6 z-50 transition-opacity transition-transform duration-300 border rounded-xl px-4 py-3 shadow-lg text-sm max-w-sm min-w-72 {current.type === 'error' ? 'bg-red-50 border-red-200 text-red-800' : 'bg-emerald-50 border-emerald-200 text-emerald-800'}" role="status" aria-live="polite">
        <p class="font-semibold">{current.title}</p>
        <p class="mt-0.5">{current.message}</p>
        {#if current.action}
            <a class="mt-2 inline-flex text-xs font-semibold underline" href={current.action.href}>{current.action.label}</a>
        {/if}
    </div>
{/if}
