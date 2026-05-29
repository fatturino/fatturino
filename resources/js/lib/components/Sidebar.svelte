<script>
    import { router } from '@inertiajs/svelte'
    import House from 'phosphor-svelte/lib/House'
    import AddressBook from 'phosphor-svelte/lib/AddressBook'
    import FileText from 'phosphor-svelte/lib/FileText'
    import ShoppingCart from 'phosphor-svelte/lib/ShoppingCart'
    import ArrowsClockwise from 'phosphor-svelte/lib/ArrowsClockwise'
    import ArrowUUpLeft from 'phosphor-svelte/lib/ArrowUUpLeft'
    import FileDashed from 'phosphor-svelte/lib/FileDashed'
    import ListNumbers from 'phosphor-svelte/lib/ListNumbers'
    import DownloadSimple from 'phosphor-svelte/lib/DownloadSimple'
    import Buildings from 'phosphor-svelte/lib/Buildings'
    import Gear from 'phosphor-svelte/lib/Gear'
    import Lightning from 'phosphor-svelte/lib/Lightning'
    import Envelope from 'phosphor-svelte/lib/Envelope'
    import Briefcase from 'phosphor-svelte/lib/Briefcase'
    import ClipboardText from 'phosphor-svelte/lib/ClipboardText'
    import SignOut from 'phosphor-svelte/lib/SignOut'
    import X from 'phosphor-svelte/lib/X'
    import UserCircle from 'phosphor-svelte/lib/UserCircle'
    import CaretDown from 'phosphor-svelte/lib/CaretDown'
    import BookOpenText from 'phosphor-svelte/lib/BookOpenText'
    import TerminalWindow from 'phosphor-svelte/lib/TerminalWindow'

    let {
        sidebarOpen = $bindable(false),
        currentPath = '',
        user = null,
        fiscalYear = null,
        availableYears = [],
        menuSections = []
    } = $props()

    const iconMap = {
        House,
        AddressBook,
        FileText,
        ShoppingCart,
        ArrowsClockwise,
        ArrowUUpLeft,
        FileDashed,
        ListNumbers,
        DownloadSimple,
        Buildings,
        Gear,
        Lightning,
        Envelope,
        Briefcase,
        ClipboardText,
        TerminalWindow,
    }

    let openSections = $state({})

    function isActive(path) {
        return currentPath.startsWith(path)
    }

    function navClass(path) {
        const base = 'flex items-center gap-2.5 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors'
        return isActive(path)
            ? `${base} active-nav`
            : `${base} text-white/60 hover:bg-white/6 hover:text-white`
    }

    function handleFiscalYearChange(event) {
        const year = parseInt(event.target.value)
        router.post('/fiscal-year', { year }, {
            preserveState: true,
            preserveScroll: true,
        })
    }

    function sectionHasActiveItem(section) {
        return (section.items ?? []).some((item) => isActive(item.path))
    }

    function toggleSection(sectionId) {
        openSections[sectionId] = !openSections[sectionId]
    }

    function isSectionOpen(section) {
        if (openSections[section.id] !== undefined) {
            return openSections[section.id]
        }

        return sectionHasActiveItem(section)
    }
</script>

<!-- Mobile overlay -->
{#if sidebarOpen}
    <div
        class="fixed inset-0 z-40 bg-brand-deep/30 backdrop-blur-sm lg:hidden"
        onclick={() => sidebarOpen = false}
        aria-hidden="true"
    ></div>
{/if}

<aside
    class="w-60 fatturino-sidebar flex flex-col shrink-0
           fixed inset-y-0 left-0 z-50
           transition-transform duration-300 ease-out
           {sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'}"
    aria-label="Navigazione principale"
>
    <!-- Brand -->
    <div class="p-4 border-b border-white/8 flex items-center justify-between">
        <a href="/dashboard" class="flex items-center">
            <img src="/brand/logo-white.svg" alt="Fatturino" class="h-7 w-auto" />
        </a>
        <button
            class="lg:hidden rounded-lg p-1 text-white/60 hover:text-white hover:bg-white/6 transition-colors"
            onclick={() => sidebarOpen = false}
            aria-label="Chiudi menu"
        >
            <X class="size-5" />
        </button>
    </div>

    <!-- User info -->
    {#if user}
        <div class="px-4 py-3 border-b border-white/8">
            <div class="flex items-center gap-3">
                <div class="size-9 rounded-full bg-white/12 flex items-center justify-center shrink-0 border border-white/12">
                    <UserCircle class="size-5 text-white/60" />
                </div>
                <div class="min-w-0">
                    <p class="text-sm font-medium text-white/80 truncate">{user.name ?? 'Utente'}</p>
                    <p class="text-xs text-white/40 truncate">{user.email ?? ''}</p>
                </div>
            </div>
        </div>
    {/if}

    <!-- Fiscal year selector -->
    <div class="px-3 py-2 border-b border-white/8">
        <select
            value={fiscalYear ?? new Date().getFullYear()}
            onchange={handleFiscalYearChange}
            class="w-full rounded-lg border border-white/12 px-2.5 py-2 text-sm bg-white/8 text-white/80 focus:border-white/40 focus:outline-none focus:ring-2 focus:ring-white/20"
        >
            {#each (availableYears ?? []) as year}
                <option value={year} class="bg-brand-deep text-white">{year}</option>
            {/each}
        </select>
    </div>

    <!-- Navigation -->
    <nav class="flex-1 p-3 space-y-2 overflow-y-auto" aria-label="Menu principale">
        <a href="/dashboard" class={navClass('/dashboard')}>
            <House class="size-4.5" />
            Dashboard
        </a>

        <a href="https://fatturino.it/docs" class="flex items-center gap-2.5 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors text-white/60 hover:bg-white/6 hover:text-white">
            <BookOpenText class="size-4.5" />
            Documentazione
        </a>

        {#each menuSections as section}
            <div>
                <button
                    type="button"
                    class="w-full nav-section-label px-3 py-2.5 text-left flex items-center justify-between"
                    onclick={() => toggleSection(section.id)}
                    aria-expanded={isSectionOpen(section)}
                    aria-controls={`section-${section.id}`}
                >
                    <span>{section.title}</span>
                    <CaretDown class="size-4 transition-transform {isSectionOpen(section) ? 'rotate-180' : ''}" />
                </button>

                {#if isSectionOpen(section)}
                    <div id={`section-${section.id}`} class="mt-1 space-y-0.5">
                        {#each section.items as item}
                            {@const Icon = iconMap[item.icon]}
                            <a href={item.path} class={navClass(item.path)}>
                                {#if Icon}
                                    <Icon class="size-4.5" />
                                {/if}
                                {item.label}
                            </a>
                        {/each}
                    </div>
                {/if}
            </div>
        {/each}
    </nav>

    <!-- Logout footer -->
    <div class="p-3 border-t border-white/8">
        <form method="POST" action="/logout">
            <button class="w-full flex items-center gap-2.5 text-left px-3 py-2.5 rounded-lg text-sm font-medium text-white/50 hover:bg-white/6 hover:text-white/80 transition-colors">
                <SignOut class="size-4.5" />
                Esci
            </button>
        </form>
    </div>
</aside>
