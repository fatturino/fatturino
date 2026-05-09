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
    <header class="bg-primary py-8 sm:py-10">
        <div class="flex flex-col items-center">
            <x-app-brand class="px-0 py-0 flex-col items-center gap-2" />
            <p class="text-white/50 text-sm mt-4">{{ __('app.landing.tagline') }}</p>
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
