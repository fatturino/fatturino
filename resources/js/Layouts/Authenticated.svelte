<script>
    import { page } from '@inertiajs/svelte'
    import Toast from '$lib/components/Toast.svelte'
    import Sidebar from '$lib/components/Sidebar.svelte'
    import Button from '$lib/components/ui/Button.svelte'
    import { headerActionsStore } from '$lib/stores/header-actions.js'
    import List from 'phosphor-svelte/lib/List'

    let { children, headerActions } = $props()

    const currentPath = $derived(page.url)
    const pageTitle = $derived(page.props.title ?? prettifyComponentName(page.component))
    const breadcrumbs = $derived(page.props.breadcrumbs ?? [])
    const user = $derived(page.props.auth?.user ?? null)
    const fiscalYear = $derived(page.props.fiscalYear ?? null)
    const availableYears = $derived(page.props.availableYears ?? [])
    const fiscalRegime = $derived(page.props.fiscalRegime ?? null)
    const rf19SelfInvoicesEnabled = $derived(!!page.props.rf19SelfInvoicesEnabled)
    const menuSections = $derived(buildSidebarSections(fiscalRegime, rf19SelfInvoicesEnabled))
    const headerActionsState = $derived($headerActionsStore)

    let sidebarOpen = $state(false)

    function prettifyComponentName(componentName) {
        if (!componentName) return 'Dashboard'
        const lastSegment = componentName.split('/').pop() ?? componentName
        if (lastSegment === 'Index') {
            const parent = componentName.split('/').slice(-2, -1)[0]
            return parent ? parent.replace(/([a-z])([A-Z])/g, '$1 $2') : 'Dashboard'
        }
        return lastSegment.replace(/([a-z])([A-Z])/g, '$1 $2')
    }

    function buildSidebarSections(regime, rf19Enabled) {
        const hideSelfInvoices = regime === 'RF19' && !rf19Enabled
        const allSections = [
            {
                id: 'registry',
                title: 'Anagrafiche',
                items: [
                    { label: 'Contatti', path: '/contacts', icon: 'AddressBook' },
                ],
            },
            {
                id: 'sells',
                title: 'Vendite',
                items: [
                    { label: 'Fatture', path: '/sell-invoices', icon: 'FileText' },
                    { label: 'Proforma', path: '/proforma', icon: 'FileDashed' },
                    { label: 'Note di credito', path: '/credit-notes', icon: 'ArrowUUpLeft' },
                ]
            },
            {
                id: 'purchases',
                title: 'Acquisti',
                items: [
                    { label: 'Fatture', path: '/purchase-invoices', icon: 'ShoppingCart' },
                    { label: 'Autofatture', path: '/self-invoices', icon: 'ArrowsClockwise', hidden: hideSelfInvoices },
                ],
            },
            {
                id: 'settings',
                title: 'Impostazioni',
                items: [
                    { label: 'Sequenze', path: '/sequences', icon: 'ListNumbers' },
                    { label: 'Import', path: '/imports', icon: 'DownloadSimple' },
                    { label: 'Azienda', path: '/company-settings', icon: 'Buildings' },
                    { label: 'Fatture', path: '/invoice-settings', icon: 'Gear' },
                    { label: 'Fatturazione elettronica', path: '/electronic-invoice-settings', icon: 'Lightning' },
                    { label: 'Email', path: '/email-settings', icon: 'Envelope' },
                    { label: 'Servizi', path: '/services', icon: 'Briefcase' }
                ],
            },
        ]

        return allSections
            .map((section) => ({
                ...section,
                items: section.items.filter((item) => !item.hidden && !item.hideForRegimes?.includes(regime)),
            }))
            .filter((section) => section.items.length > 0)
    }

    // Close sidebar on route change (mobile)
    $effect(() => {
        if (currentPath) sidebarOpen = false
    })
</script>

<div class="min-h-screen bg-brand-bg">
    <!-- Skip to content -->
    <a href="#main-content" class="sr-only focus:not-sr-only focus:absolute focus:top-3 focus:left-3 focus:z-50 focus:rounded-lg focus:bg-brand-accent focus:text-brand-deep focus:px-4 focus:py-2 focus:font-semibold focus:text-sm">Salta al contenuto</a>

    <Sidebar
        bind:sidebarOpen={sidebarOpen}
        currentPath={currentPath}
        {user}
        {fiscalYear}
        {availableYears}
        {menuSections}
    />

    <!-- Main content area -->
    <div class="min-h-screen min-w-0 flex flex-col lg:pl-60">
        <header class="fatturino-header sticky top-0 z-30 px-4 sm:px-6 py-3 flex items-center gap-3">
            <!-- Mobile hamburger -->
            <button
                class="lg:hidden rounded-lg p-1.5 text-brand-deep/60 hover:text-brand-deep hover:bg-surface-muted transition-colors -ml-1 shrink-0"
                onclick={() => sidebarOpen = true}
                aria-label="Apri menu"
            >
                <List class="size-5" />
            </button>

            <div class="min-w-0 flex-1">
                {#if breadcrumbs.length > 0}
                    <nav class="flex items-center gap-1.5 mb-0.5" aria-label="Breadcrumb">
                        {#each breadcrumbs as crumb, i}
                            {#if i > 0}
                                <span class="text-brand-deep/20 text-xs" aria-hidden="true">/</span>
                            {/if}
                            {#if crumb.url && i < breadcrumbs.length - 1}
                                <a href={crumb.url} class="text-xs text-brand-secondary/60 hover:text-brand-deep transition-colors">{crumb.label}</a>
                            {:else}
                                <span class="text-xs text-brand-deep/40">{crumb.label}</span>
                            {/if}
                        {/each}
                    </nav>
                {/if}
                <div class="flex items-center gap-2 min-w-0">
                    <h1 class="text-xl font-semibold text-brand-deep truncate">{pageTitle}</h1>
                    {#if fiscalYear}
                        <span class="hidden sm:inline-flex shrink-0 items-center rounded-md border border-border-light bg-surface-muted px-2 py-0.5 text-[11px] font-medium text-brand-secondary">
                            FY {fiscalYear}
                        </span>
                    {/if}
                </div>
            </div>

            <div class="flex items-center gap-2 shrink-0">
                {#if headerActions}
                    {@render headerActions()}
                {:else if headerActionsState}
                    <div class="hidden sm:flex items-center gap-2">
                        <a href={headerActionsState.indexPath} class="btn-outline text-sm">Indietro</a>
                        {#if !headerActionsState.isReadOnly}
                            <Button
                                class="text-sm"
                                variant={headerActionsState.variant ?? 'brand'}
                                onclick={headerActionsState.onSubmit}
                                disabled={headerActionsState.processing || headerActionsState.isDisabled}
                                isLoading={headerActionsState.processing}
                                ariaLabel={headerActionsState.ariaLabel ?? 'Salva modifiche'}
                            >
                                {headerActionsState.submitLabel}
                            </Button>
                        {/if}
                    </div>
                {/if}
            </div>
        </header>

        <!-- Page content -->
        <main class="flex-1" id="main-content">
            {@render children?.()}
        </main>
    </div>

    <Toast />
</div>
