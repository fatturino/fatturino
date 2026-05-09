<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ isset($title) ? $title.' - '.config('app.name') : config('app.name') }}</title>

    {{-- Font: Outfit via Bunny Fonts --}}
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=outfit:400,500,600,700&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen font-sans antialiased">

    {{-- Mobile brand strip --}}
    <div class="lg:hidden bg-primary py-6">
        <div class="flex items-center justify-center">
            <x-app-brand class="px-0 py-0" />
        </div>
    </div>

    <div class="min-h-screen flex">

        {{-- Left panel: brand (desktop only) --}}
        <div class="hidden lg:flex lg:w-5/12 bg-primary relative overflow-hidden">
            <div class="relative z-10 flex flex-col justify-center items-center p-12 w-full">
                <div class="text-center">
                    <x-app-brand class="px-0 py-0 flex-col items-center gap-3" />
                    <p class="text-white/60 text-sm mt-6 max-w-xs mx-auto">{{ __('app.landing.tagline') }}</p>
                </div>
            </div>
            {{-- Subtle texture overlay --}}
            <div class="absolute inset-0 bg-gradient-to-br from-secondary/20 to-transparent"></div>
        </div>

        {{-- Right panel: form --}}
        <div class="flex-1 flex items-center justify-center p-6 sm:p-10 bg-base-200/50">
            <div class="guest-fade-in w-full max-w-md bg-base-100 rounded-2xl border border-base-200 p-8 sm:p-10">
                {{ $slot }}
            </div>
        </div>

    </div>
</body>
</html>
