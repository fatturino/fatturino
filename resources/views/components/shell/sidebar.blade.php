@blaze

<aside class="fixed inset-y-0 left-0 z-50 flex w-64 -translate-x-full flex-col border-r border-white/8 bg-ink text-white transition-transform duration-300 lg:translate-x-0" :class="{ 'translate-x-0': sidebarOpen }" aria-label="Navigazione principale">
    <div class="flex h-16 items-center justify-between border-b border-white/10 px-5">
        <x-app-link :href="route('dashboard')" class="flex items-center"><img src="{{ asset('brand/logo-white.svg') }}" alt="Fatturino" class="h-8 w-auto"></x-app-link>
        <button type="button" class="inline-flex size-8 cursor-pointer items-center justify-center rounded-md text-white/70 transition hover:bg-white/10 hover:text-white focus:outline-none focus:ring-2 focus:ring-indigo-300/40 lg:hidden" @click="sidebarOpen = false" aria-label="Chiudi menu">×</button>
    </div>
    @php($sections = ['Anagrafiche' => [['Contatti', 'contacts.index', 'o-users']], 'Vendite' => [['Fatture', 'sell-invoices.index', 'o-document-text'], ['Proforma', 'proforma.index', 'o-clipboard-document'], ['Note di credito', 'credit-notes.index', 'o-receipt-refund']], 'Acquisti' => [['Fatture', 'purchase-invoices.index', 'o-document-text'], ['Autofatture', 'self-invoices.index', 'o-receipt-percent']], 'Impostazioni' => [['Sequenze', 'sequences.index', 'o-list-bullet'], ['Import', 'imports.index', 'o-arrow-up-tray'], ['Azienda', 'settings.company', 'o-building-office'], ['Fatture', 'settings.invoice', 'o-document-text'], ['Fatturazione elettronica', 'settings.openapi', 'o-paper-airplane'], ['Email', 'settings.email', 'o-envelope'], ['Servizi', 'settings.services', 'o-puzzle-piece'], ['Avanzate', 'settings.advanced', 'o-adjustments-horizontal']]])
    <nav class="flex-1 overflow-y-auto p-4">
        <x-app-link :href="route('dashboard')" class="mb-4 flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium {{ request()->routeIs('dashboard') ? 'bg-sidebar-active text-sidebar-text shadow-[inset_2px_0_0_var(--color-indigo-300)]' : 'text-sidebar-muted hover:bg-white/8 hover:text-sidebar-text' }}"><x-icon name="o-home" class="size-5 shrink-0" /><span>Dashboard</span></x-app-link>
        @foreach ($sections as $label => $items)
            <p class="px-3 pb-2 pt-4 text-xs font-medium text-sidebar-muted">{{ $label }}</p>
            @foreach ($items as [$item, $route, $icon])
                <x-app-link :href="route($route)" class="flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium {{ request()->routeIs($route) ? 'bg-sidebar-active text-sidebar-text shadow-[inset_2px_0_0_var(--color-indigo-300)]' : 'text-sidebar-muted hover:bg-white/8 hover:text-sidebar-text' }}"><x-icon :name="$icon" class="size-5 shrink-0" /><span>{{ $item }}</span></x-app-link>
            @endforeach
        @endforeach
    </nav>
</aside>
