<script>
    import { useForm } from '@inertiajs/svelte'
    import Button from '$lib/components/ui/Button.svelte'
    import Input from '$lib/components/ui/Input.svelte'
    import { page } from '@inertiajs/svelte'

    let { appName = page.props.appName, prefillEmail = '', prefillPassword = '', errors = {} } = $props()

    const form = useForm({
        email: prefillEmail,
        password: prefillPassword,
        remember: false,
    })

    function handleSubmit() {
        form.post('/login', {
            preserveScroll: true,
        })
    }
</script>

<div class="guest-shell">
    <div class="guest-panel guest-login-panel w-full max-w-5xl">
        <aside class="guest-hero guest-login-hero">
            <img src="/brand/logo-white.svg" alt="Fatturino" class="guest-login-logo" />
            <p class="guest-kicker guest-login-kicker">Fatturazione senza attrito</p>
            <h1 class="guest-title">{appName}</h1>
            <p class="guest-copy guest-login-copy">Accedi e riprendi subito clienti, incassi e fatture elettroniche.</p>
            <div class="guest-pills guest-login-pills">
                <span>SDI pronto</span>
                <span>Dashboard in tempo reale</span>
                <span>Workflow guidato</span>
            </div>
        </aside>

        <section class="guest-card guest-login-card">
            <div class="mb-6">
                <img src="/brand/logo-dark.svg" alt="Fatturino" class="guest-login-logo-mark" />
                <p class="text-sm font-semibold tracking-wide text-brand-accent mt-4">BENTORNATO</p>
                <h2 class="text-2xl font-semibold text-brand-deep mt-1">Accedi al tuo account</h2>
            </div>

            <label class="block mb-4">
                <span class="text-sm font-medium text-brand-deep">Email</span>
                <Input
                    class="mt-1 w-full input-field"
                    type="email"
                    bind:value={form.email}
                    required
                    autofocus
                />
                {#if errors.email}
                    <span class="text-error-red text-xs mt-0.5 block" role="alert">{errors.email}</span>
                {/if}
            </label>

            <label class="block mb-4">
                <span class="text-sm font-medium text-brand-deep">Password</span>
                <Input
                    class="mt-1 w-full input-field"
                    type="password"
                    bind:value={form.password}
                    required
                />
                {#if errors.password}
                    <span class="text-error-red text-xs mt-0.5 block" role="alert">{errors.password}</span>
                {/if}
            </label>

            <label class="flex items-center gap-2 text-sm text-brand-deep/70 mb-6">
                <Input
                    type="checkbox"
                    bind:checked={form.remember}
                    class="rounded border-border"
                />
                Ricordami
            </label>

            <Button
                class="btn-brand w-full py-2.5 guest-submit"
                onclick={handleSubmit}
                disabled={form.processing}
            >
                {form.processing ? '...' : 'Accedi'}
            </Button>
        </section>
    </div>
</div>
