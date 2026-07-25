@blaze

<aside class="fixed inset-y-0 left-0 z-50 flex w-64 -translate-x-full flex-col border-r border-indigo/40 bg-ink text-white transition-transform duration-300 lg:translate-x-0" :class="{ 'translate-x-0': sidebarOpen }" aria-label="Navigazione principale">
    <div class="flex h-16 items-center justify-between border-b border-white/10 px-5">
        <a href="{{ route('dashboard') }}" class="flex items-center gap-3"><img src="{{ asset('brand/logo-white.svg') }}" alt="" class="h-8 w-auto"><span class="font-bold">{{ config('app.name') }}</span></a>
        <button type="button" class="cursor-pointer lg:hidden" @click="sidebarOpen = false" aria-label="Chiudi menu">×</button>
    </div>
    @php($sections = ['Anagrafiche' => [['Contatti', 'contacts.index']], 'Vendite' => [['Fatture', 'sell-invoices.index'], ['Proforma', 'proforma.index'], ['Note di credito', 'credit-notes.index']], 'Acquisti' => [['Fatture', 'purchase-invoices.index'], ['Autofatture', 'self-invoices.index']], 'Impostazioni' => [['Sequenze', 'sequences.index'], ['Import', 'imports.index'], ['Azienda', 'settings.company'], ['Fatture', 'settings.invoice'], ['Fatturazione elettronica', 'settings.openapi'], ['Email', 'settings.email'], ['Servizi', 'settings.services'], ['Avanzate', 'settings.advanced']]])
    <nav class="flex-1 overflow-y-auto p-4">
        <a href="{{ route('dashboard') }}" class="mb-4 block rounded-md px-3 py-2 text-sm font-semibold {{ request()->routeIs('dashboard') ? 'bg-teal' : 'text-white/80 hover:bg-white/10' }}">Dashboard</a>
        @foreach ($sections as $label => $items)
            <p class="px-3 pb-2 pt-4 text-[11px] font-bold uppercase tracking-[0.14em] text-aqua/60">{{ $label }}</p>
            @foreach ($items as [$item, $route])
                <a href="{{ route($route) }}" class="block rounded-md px-3 py-2 text-sm font-medium {{ request()->routeIs($route) ? 'bg-white/15 text-white' : 'text-white/75 hover:bg-white/10 hover:text-white' }}">{{ $item }}</a>
            @endforeach
        @endforeach
    </nav>
</aside>
