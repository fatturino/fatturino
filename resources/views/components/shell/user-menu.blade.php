@php
    $company = app(\App\Settings\CompanySettings::class);
    $fiscalRegime = \App\Enums\FiscalRegime::tryFrom($company->company_fiscal_regime)?->label()
        ?? 'Regime fiscale non configurato';
@endphp

<x-dropdown right>
    <x-slot:trigger>
        <button
            type="button"
            class="flex max-w-64 items-center gap-3 rounded-lg px-2 py-1.5 text-left transition-colors hover:bg-surface-muted focus:outline-none focus:ring-2 focus:ring-primary/20"
            aria-label="Apri menu azienda"
        >
            <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary">
                <x-icon name="o-building-office-2" class="size-5" />
            </span>
            <span class="min-w-0 flex-1">
                <span class="block truncate text-sm font-semibold text-content">{{ $company->company_name }}</span>
                <span class="block truncate text-xs text-content-muted">{{ $fiscalRegime }}</span>
            </span>
            <x-icon name="o-chevron-down" class="size-4 shrink-0 text-content-muted" />
        </button>
    </x-slot:trigger>

    <x-app-link :href="route('settings.company')" class="flex items-center gap-3 rounded-lg px-4 py-2.5 text-sm text-content/70 transition-colors hover:bg-content/5 hover:text-content">
        <x-icon name="o-cog-6-tooth" class="size-5 shrink-0" />
        <span>Impostazioni</span>
    </x-app-link>
    <hr class="my-1 border-base-200">
    <form method="POST" action="{{ route('logout') }}" data-posthog-logout>
        @csrf
        <button type="submit" class="flex w-full items-center gap-3 rounded-lg px-4 py-2.5 text-sm text-danger transition-colors hover:bg-danger/5">
            <x-icon name="o-arrow-right-start-on-rectangle" class="size-5 shrink-0" />
            <span>Esci</span>
        </button>
    </form>
</x-dropdown>
