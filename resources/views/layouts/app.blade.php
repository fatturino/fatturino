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
        <div x-data="{ sidebarOpen: false }" class="min-h-dvh lg:pl-64">
            <div x-cloak x-show="sidebarOpen" class="fixed inset-0 z-40 bg-ink/40 lg:hidden" @click="sidebarOpen = false"></div>
            <x-shell.sidebar />
            <header class="sticky top-0 z-30 flex h-16 items-center border-b border-border-light bg-white/95 px-4 backdrop-blur lg:px-8">
                <button type="button" class="inline-flex size-9 cursor-pointer items-center justify-center rounded-md border border-border text-content lg:hidden" @click="sidebarOpen = true" aria-label="Apri menu">☰</button>
                <div class="flex-1">@isset($header){{ $header }}@endisset</div>
                <x-shell.user-menu />
            </header>
            <main id="main-content" class="mx-auto w-full max-w-10xl p-4 lg:p-8">{{ $slot }}</main>
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
