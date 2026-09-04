<!DOCTYPE html>
<html lang="it">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="robots" content="noindex, nofollow, noarchive">
        <title>{{ $title ?? config('app.name') }}</title>
        <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=outfit:400,500,600,700,800&display=swap" rel="stylesheet">
        @vite(['resources/css/fatturino.css', 'resources/js/posthog.js'])
        @livewireStyles
    </head>
    <body class="bg-canvas font-sans text-content antialiased">
        <!-- THESIS: Operational Ledger makes the day’s financial work legible before the data density begins. OWN-WORLD: cool white canvas, navy navigation, indigo actions, fine borders and measured two-level modules. STORY: users scan the fiscal context, act on priorities, then drill into documents. FIRST VIEWPORT: compact header, persistent navigation, decisive title/actions and financial summary without decorative chrome. FORM: Tailkit application navigation, page heading, alternate statistics and table patterns; seed bfc96bf1. FINISH: unreviewed and undocumented is unfinished; this build ends with the finish review, the verdict, DESIGN.md, and every shipping raster carrying its provenance -->
        <x-app-link href="#main-content" :full-reload="true" class="sr-only fixed left-4 top-4 z-[60] rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white focus:not-sr-only focus:outline-none focus:ring-3 focus:ring-primary/20">Salta al contenuto principale</x-app-link>
        <div
            x-data="{
                sidebarOpen: false,
                isDesktop: false,
                openSidebar() {
                    this.sidebarOpen = true;
                    this.$nextTick(() => this.$refs.sidebarClose?.focus());
                },
                closeSidebar(returnFocus = false) {
                    this.sidebarOpen = false;

                    if (returnFocus) {
                        this.$nextTick(() => this.$refs.menuTrigger?.focus());
                    }
                },
                syncViewport() {
                    this.isDesktop = window.matchMedia('(min-width: 1024px)').matches;

                    if (this.isDesktop) {
                        this.sidebarOpen = false;
                    }
                },
            }"
            x-init="syncViewport()"
            @resize.window.debounce.100ms="syncViewport()"
            @keydown.escape.window="if (sidebarOpen && !isDesktop) closeSidebar(true)"
            class="app-frame min-h-dvh lg:pl-72"
        >
            <div x-cloak x-show="sidebarOpen && !isDesktop" x-transition.opacity class="fixed inset-0 z-40 bg-ink/40 lg:hidden" @click="closeSidebar(true)" aria-hidden="true"></div>
            <x-shell.sidebar />
            <header class="fatturino-header sticky top-0 z-30 flex min-h-[4.5rem] items-center gap-3 px-4 lg:px-8">
                <button x-ref="menuTrigger" type="button" class="inline-flex size-11 shrink-0 cursor-pointer items-center justify-center rounded-lg text-content transition hover:bg-surface-muted focus:outline-none focus:ring-2 focus:ring-primary/20 lg:hidden" @click="openSidebar()" aria-label="Apri menu" aria-controls="app-sidebar" :aria-expanded="sidebarOpen.toString()">☰</button>
                <div class="min-w-0 flex-1">@isset($header){{ $header }}@endisset</div>
                <x-shell.user-menu />
            </header>
            <main id="main-content" tabindex="-1" class="app-content mx-auto w-full max-w-[96rem] p-4 sm:p-6 lg:p-8">{{ $slot }}</main>
        </div>
        @livewireScripts
        @wirechartsScripts
        @auth
            <script data-navigate-once>
                window.FatturinoPostHog = @json(app(\App\Services\PostHogTelemetryService::class)->browserContext(auth()->user()));
            </script>
        @endauth
    </body>
</html>
