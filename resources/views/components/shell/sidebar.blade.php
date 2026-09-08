@blaze

<aside
    id="app-sidebar"
    x-trap.inert.noscroll="sidebarOpen && !isDesktop"
    :inert="!isDesktop && !sidebarOpen"
    :aria-hidden="(!isDesktop && !sidebarOpen).toString()"
    class="fatturino-sidebar fixed inset-y-0 left-0 z-50 flex w-72 -translate-x-full flex-col text-white transition-transform duration-300 lg:translate-x-0"
    :class="{ 'translate-x-0': sidebarOpen }"
    aria-label="{{ __('app.nav.main_navigation') }}"
>
    <div class="flex h-[4.5rem] items-center justify-between border-b border-white/10 px-6">
        <x-app-link :href="route('dashboard')" class="flex items-center"><img src="{{ asset('brand/logo-white.svg') }}" alt="Fatturino" class="h-8 w-auto"></x-app-link>
        <button x-ref="sidebarClose" type="button" class="inline-flex size-11 cursor-pointer items-center justify-center rounded-lg text-white/70 transition hover:bg-white/10 hover:text-white focus:outline-none focus:ring-2 focus:ring-indigo-300/40 lg:hidden" @click="closeSidebar(true)" aria-label="{{ __('app.nav.close_menu') }}">×</button>
    </div>
    @php($sections = [
        __('app.nav.contacts_section') => [[__('app.nav.contacts'), 'contacts.index', 'o-users']],
        __('app.nav.sales') => [[__('app.nav.sell_invoices'), 'sell-invoices.index', 'o-document-text'], [__('app.nav.proforma'), 'proforma.index', 'o-clipboard-document'], [__('app.nav.credit_notes'), 'credit-notes.index', 'o-receipt-refund']],
        __('app.nav.purchases') => [[__('app.nav.purchase_invoices'), 'purchase-invoices.index', 'o-document-text'], [__('app.nav.self_invoices'), 'self-invoices.index', 'o-receipt-percent']],
        __('app.nav.settings') => [[__('app.nav.sequences'), 'sequences.index', 'o-list-bullet'], [__('app.nav.imports'), 'imports.index', 'o-arrow-up-tray'], [__('app.nav.company'), 'settings.company', 'o-building-office'], [__('app.nav.invoices'), 'settings.invoice', 'o-document-text'], [__('app.nav.electronic_invoicing'), 'settings.openapi', 'o-paper-airplane'], [__('app.nav.email'), 'settings.email', 'o-envelope'], [__('app.nav.services'), 'settings.services', 'o-puzzle-piece'], [__('app.nav.advanced'), 'settings.advanced', 'o-adjustments-horizontal']],
    ])
    <nav class="flex-1 overflow-y-auto px-4 py-5">
        <x-app-link :href="route('dashboard')" :aria-current="request()->routeIs('dashboard') ? 'page' : null" @click="if (!isDesktop) closeSidebar()" class="mb-5 flex min-h-11 items-center gap-3 rounded-lg px-3 py-2 text-sm font-semibold {{ request()->routeIs('dashboard') ? 'bg-white/12 text-sidebar-text ring-1 ring-white/10' : 'text-sidebar-muted hover:bg-white/8 hover:text-sidebar-text' }}"><x-icon name="o-home" class="size-5 shrink-0" /><span>{{ __('app.nav.overview') }}</span></x-app-link>
        @foreach ($sections as $label => $items)
            <p class="px-3 pb-2 pt-5 text-[0.6875rem] font-semibold uppercase tracking-[0.12em] text-sidebar-muted">{{ $label }}</p>
            @foreach ($items as [$item, $route, $icon])
                <x-app-link :href="route($route)" :aria-current="request()->routeIs($route) ? 'page' : null" @click="if (!isDesktop) closeSidebar()" class="flex min-h-11 items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium {{ request()->routeIs($route) ? 'bg-white/12 text-sidebar-text ring-1 ring-white/10' : 'text-sidebar-muted hover:bg-white/8 hover:text-sidebar-text' }}"><x-icon :name="$icon" class="size-5 shrink-0" /><span>{{ $item }}</span></x-app-link>
            @endforeach
        @endforeach
    </nav>
</aside>
