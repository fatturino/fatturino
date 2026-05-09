<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ isset($title) ? $title.' - '.config('app.name') : config('app.name') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=outfit:400,500,600,700&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen font-sans antialiased bg-base-200/50 flex flex-col">

    {{-- Brand header --}}
    <header class="bg-primary py-14 sm:py-16">
        <div class="flex flex-col items-center">
            <div class="flex items-center gap-3">
                <svg class="w-12 h-14" viewBox="0 0 28 34" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <title>{{ config('app.name') }}</title>
                    <path d="M2 3C2 1.34 3.34 0 5 0H23C24.66 0 26 1.34 26 3V29L23 27L20 29L17 27L14 29L11 27L8 29L5 27L2 29V3Z" fill="white" fill-opacity="0.95"/>
                    <path d="M8 6H20V9.5H12.5V13.5H18.5V17H12.5V24H8V6Z" fill="#3D348B"/>
                    <circle cx="22" cy="5.5" r="2.5" fill="#F7B801"/>
                </svg>
                <span class="text-3xl font-extrabold tracking-tight text-white">Fatturino</span>
            </div>
            <p class="text-white/50 text-sm mt-3">{{ __('app.landing.tagline') }}</p>
        </div>
    </header>

    {{-- Form area --}}
    <main class="flex-1 flex items-start justify-center px-4 sm:px-6 py-8 sm:py-12">
        <div class="guest-fade-in w-full max-w-lg bg-base-100 rounded-2xl border border-base-200 p-6 sm:p-10">
            {{ $slot }}
        </div>
    </main>

</body>
</html>
