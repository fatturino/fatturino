@php
    $company = app(\App\Settings\CompanySettings::class);
    $fiscalRegime = \App\Enums\FiscalRegime::tryFrom($company->company_fiscal_regime)?->label()
        ?? 'Regime fiscale non configurato';
@endphp

<div x-data="{ open: false }" class="relative inline-block">
    <button
        type="button"
        class="inline-flex max-w-64 items-center gap-2.5 rounded-lg px-2 py-1.5 text-left text-sm text-content transition hover:bg-surface-muted focus:outline-none focus:ring-2 focus:ring-primary/20"
        aria-label="Apri menu azienda"
        aria-haspopup="true"
        :aria-expanded="open"
        @click="open = !open"
    >
        <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-primary-subtle text-primary">
            <x-icon name="o-building-office-2" class="size-5" />
        </span>
        <span class="min-w-0 flex-1">
            <span class="block truncate text-sm font-medium text-content">{{ $company->company_name }}</span>
            <span class="block truncate text-xs text-content-muted">{{ $fiscalRegime }}</span>
        </span>
        <x-icon name="o-chevron-down" class="size-4 shrink-0 text-content-muted" />
    </button>

    <div
        x-cloak
        x-show="open"
        x-transition:enter="transition ease-out duration-100"
        x-transition:enter-start="opacity-0 scale-90"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-75"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-90"
        @click.away="open = false"
        role="menu"
        class="absolute right-0 z-50 mt-2 w-64 origin-top-right rounded-xl border border-border bg-white shadow-[var(--shadow-elevated)]"
    >
        <div class="divide-y divide-border">
            <div class="space-y-1 p-2.5">
                <x-app-link :href="route('settings.company')" role="menuitem" class="group flex items-center gap-2 rounded-lg border border-transparent px-2.5 py-2 text-sm font-medium text-content-muted transition hover:bg-primary-subtle hover:text-primary">
                    <x-icon name="o-cog-6-tooth" class="size-5 shrink-0 opacity-40 transition group-hover:opacity-70" />
                    <span class="grow">Impostazioni</span>
                </x-app-link>
            </div>
            <div class="space-y-1 p-2.5">
                <form method="POST" action="{{ route('logout') }}" data-posthog-logout>
                    @csrf
                    <button type="submit" role="menuitem" class="group flex w-full items-center gap-2 rounded-lg border border-transparent px-2.5 py-2 text-left text-sm font-medium text-danger transition hover:bg-danger/5">
                        <x-icon name="o-arrow-right-start-on-rectangle" class="size-5 shrink-0 opacity-50 transition group-hover:opacity-80" />
                        <span class="grow">Esci</span>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
