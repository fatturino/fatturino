<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ isset($title) ? $title.' - '.config('app.name') : config('app.name') }}</title>

    {{-- Font: Outfit via Bunny Fonts --}}
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=outfit:400,500,600,700,800&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen font-sans antialiased bg-base-100">

    {{-- Mobile brand strip (replaces hidden panel) --}}
    <div class="lg:hidden guest-panel-bg relative overflow-hidden">
        <div class="relative z-10 flex items-center justify-center py-6">
            <svg class="w-8 h-10" viewBox="0 0 28 34" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M2 3C2 1.34 3.34 0 5 0H23C24.66 0 26 1.34 26 3V29L23 27L20 29L17 27L14 29L11 27L8 29L5 27L2 29V3Z"
                      fill="white" fill-opacity="0.95"/>
                <path d="M8 6H20V9.5H12.5V13.5H18.5V17H12.5V24H8V6Z" fill="#3D348B"/>
                <circle cx="22" cy="5.5" r="2.5" fill="#F7B801"/>
            </svg>
            <span class="text-xl font-bold text-white tracking-tight ml-2.5">Fatturino</span>
        </div>
        {{-- Decorative orb (mobile, subtle) --}}
        <div class="guest-orb guest-orb-3 opacity-50"></div>
    </div>

    <div class="min-h-screen lg:min-h-screen flex">

        {{-- Left panel: animated brand showcase (desktop only) --}}
        <div class="hidden lg:flex lg:w-1/2 relative overflow-hidden guest-panel-bg">
            {{-- Floating decorative orbs --}}
            <div class="guest-orb guest-orb-1"></div>
            <div class="guest-orb guest-orb-2"></div>
            <div class="guest-orb guest-orb-3"></div>

            <div class="relative z-10 flex flex-col justify-between p-12 w-full">
                {{-- Logo and tagline --}}
                <div>
                    <div class="flex items-center gap-3 mb-2">
                        <svg class="w-12 h-14" viewBox="0 0 28 34" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M2 3C2 1.34 3.34 0 5 0H23C24.66 0 26 1.34 26 3V29L23 27L20 29L17 27L14 29L11 27L8 29L5 27L2 29V3Z"
                                  fill="white" fill-opacity="0.95"/>
                            <path d="M8 6H20V9.5H12.5V13.5H18.5V17H12.5V24H8V6Z" fill="#3D348B"/>
                            <circle cx="22" cy="5.5" r="2.5" fill="#F7B801"/>
                        </svg>
                        <span class="text-3xl font-extrabold text-white tracking-tight">Fatturino</span>
                    </div>
                    <p class="text-white/70 text-base ml-[60px]">{{ __('app.landing.tagline') }}</p>
                </div>

                {{-- Feature highlights with glassmorphism --}}
                <div class="space-y-6">
                    <div class="flex items-start gap-4">
                        <div class="w-12 h-12 rounded-xl bg-white/[0.08] backdrop-blur-sm border border-white/[0.12] flex items-center justify-center shrink-0">
                            <x-icon name="o-document-text" class="w-6 h-6 text-accent" />
                        </div>
                        <div>
                            <h3 class="text-white font-semibold">{{ __('app.landing.feature_xml_title') }}</h3>
                            <p class="text-white/50 text-sm mt-0.5">{{ __('app.landing.feature_xml_desc') }}</p>
                        </div>
                    </div>

                    <div class="flex items-start gap-4">
                        <div class="w-12 h-12 rounded-xl bg-white/[0.08] backdrop-blur-sm border border-white/[0.12] flex items-center justify-center shrink-0">
                            <x-icon name="o-paper-airplane" class="w-6 h-6 text-accent" />
                        </div>
                        <div>
                            <h3 class="text-white font-semibold">{{ __('app.landing.feature_sdi_title') }}</h3>
                            <p class="text-white/50 text-sm mt-0.5">{{ __('app.landing.feature_sdi_desc') }}</p>
                        </div>
                    </div>

                    <div class="flex items-start gap-4">
                        <div class="w-12 h-12 rounded-xl bg-white/[0.08] backdrop-blur-sm border border-white/[0.12] flex items-center justify-center shrink-0">
                            <x-icon name="o-chart-bar" class="w-6 h-6 text-accent" />
                        </div>
                        <div>
                            <h3 class="text-white font-semibold">{{ __('app.landing.feature_dashboard_title') }}</h3>
                            <p class="text-white/50 text-sm mt-0.5">{{ __('app.landing.feature_dashboard_desc') }}</p>
                        </div>
                    </div>
                </div>

                {{-- Trust line + footer --}}
                <div>
                    <p class="text-white/40 text-xs italic mb-4">{{ __('app.landing.trust_line') }}</p>
                    <p class="text-white/30 text-xs">&copy; {{ date('Y') }} Fatturino</p>
                </div>
            </div>
        </div>

        {{-- Right panel: form content with glass card --}}
        <div class="flex-1 flex items-center justify-center p-6 sm:p-10"
             style="background: radial-gradient(ellipse at top right, oklch(97.18% 0.015 294.31), oklch(100% 0 0) 70%);">
            <div class="glass-card guest-form guest-fade-in p-8 sm:p-10 w-full max-w-xl">
                {{ $slot }}
            </div>
        </div>

    </div>
</body>
</html>
