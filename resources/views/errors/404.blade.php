<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>404 - {{ config('app.name') }}</title>

    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=outfit:400,500,600,700,800&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen font-sans antialiased bg-base-100">
    <div class="min-h-screen flex items-center justify-center px-6">
        <div class="text-center max-w-md">

            {{-- Receipt-shaped icon with torn bottom --}}
            <div class="inline-flex items-center justify-center mb-8">
                <svg class="w-20 h-24" viewBox="0 0 28 34" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M2 3C2 1.34 3.34 0 5 0H23C24.66 0 26 1.34 26 3V29L23 27L20 29L17 27L14 29L11 27L8 29L5 27L2 29V3Z"
                          class="fill-base-300"/>
                    {{-- "?" mark instead of "F" --}}
                    <text x="14" y="20" text-anchor="middle" class="fill-primary" font-size="16" font-weight="800" font-family="Outfit, sans-serif">?</text>
                </svg>
            </div>

            {{-- Error code --}}
            <h1 class="text-7xl font-extrabold tracking-tight text-primary mb-2">404</h1>

            {{-- Message --}}
            <p class="text-lg font-semibold text-base-content mb-2">
                {{ __('app.errors.404_title') }}
            </p>
            <p class="text-base-content/50 mb-10">
                {{ __('app.errors.404_desc') }}
            </p>

            {{-- Actions --}}
            <div class="flex flex-wrap justify-center gap-3">
                <a href="/" class="btn btn-primary">
                    {{ __('app.errors.go_home') }}
                </a>
                <a href="javascript:history.back()" class="btn btn-ghost border border-base-300">
                    {{ __('app.errors.go_back') }}
                </a>
            </div>

        </div>
    </div>
</body>
</html>
