<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ isset($title) ? $title.' - '.config('app.name') : config('app.name') }}</title>

    <link rel="icon" type="image/svg+xml" href="/favicon.svg">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=outfit:400,500,600,700&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    {{-- Plugin injection point: analytics, tracking scripts, etc. --}}
    @foreach(app(\App\Services\PluginRegistry::class)->injections('head-scripts') as $__view)
        @include($__view)
    @endforeach
</head>
<body class="min-h-screen font-sans antialiased bg-base-200/50">

    {{-- NAVBAR mobile only --}}
    <x-nav sticky class="lg:hidden">
        <x-slot:brand>
            <x-app-brand />
        </x-slot:brand>
        <x-slot:actions>
            <div class="w-40">
                <livewire:fiscal-year-selector />
            </div>
            <label for="main-drawer" class="lg:hidden mr-3">
                <x-icon name="o-bars-3" class="cursor-pointer" />
            </label>
        </x-slot:actions>
    </x-nav>

    {{-- MAIN --}}
    <x-main full-width>
        {{-- SIDEBAR --}}
        <x-slot:sidebar drawer="main-drawer" collapsible :collapse-text="__('app.common.collapse')" class="fatturino-sidebar">

            {{-- BRAND --}}
            <x-app-brand class="p-5 pt-3" />

            {{-- FISCAL YEAR SELECTOR --}}
            <livewire:fiscal-year-selector />

            {{-- MENU --}}
            <x-menu activate-by-route active-bg-color="bg-white/15">

                {{-- User --}}
                @if($user = auth()->user())
                    <x-menu-separator />

                    <x-list-item :item="$user" value="name" sub-value="email" no-separator no-hover class="-mx-2 -my-2! rounded">
                        <x-slot:actions>
                            {{-- Logout uses POST to protect against CSRF --}}
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <x-button icon="o-power" class="btn-circle btn-ghost btn-xs" :tooltip-left="__('app.common.logoff')" type="submit" no-wire-navigate />
                            </form>
                        </x-slot:actions>
                    </x-list-item>

                    <x-menu-separator />
                @endif

                @foreach(app(\App\Services\MenuRegistry::class)->tree() as $item)
                    @continue($item['gate'] && ! auth()->user()?->can($item['gate']))
                    @if(count($item['children']))
                        @php
                            $visibleChildren = collect($item['children'])
                                ->filter(fn ($child) => ! $child['gate'] || auth()->user()?->can($child['gate']))
                                ->all();
                        @endphp
                        @if(count($visibleChildren))
                            <x-menu-sub :title="$item['title']" :icon="$item['icon']">
                                @foreach($visibleChildren as $child)
                                    <x-menu-item :title="$child['title']" :icon="$child['icon']" :link="$child['link']" />
                                @endforeach
                            </x-menu-sub>
                        @endif
                    @else
                        <x-menu-item :title="$item['title']" :icon="$item['icon']" :link="$item['link']" />
                    @endif
                @endforeach

            </x-menu>

            {{-- Plugin injection point: sidebar bottom area --}}
            @foreach(app(\App\Services\PluginRegistry::class)->injections('sidebar-bottom') as $__view)
                @include($__view)
            @endforeach

        </x-slot:sidebar>

        {{-- The `$slot` goes here --}}
        <x-slot:content>
            <div class="flex flex-col min-h-[calc(100vh-4rem)]">
                <div class="flex-1">
                    {{-- Plugin injection point: global banners, alerts, etc. --}}
                    @foreach(app(\App\Services\PluginRegistry::class)->injections('content-before') as $__view)
                        @include($__view)
                    @endforeach

                    {{ $slot }}
                </div>

                <footer class="px-4 pb-6 pt-8 text-center">
                    <span class="text-xs text-base-content/25 font-mono">v{{ config('app.version') }}</span>
                </footer>
            </div>
        </x-slot:content>
    </x-main>

    {{-- Plugin injection point: fixed overlays, footer bars, etc. --}}
    @foreach(app(\App\Services\PluginRegistry::class)->injections('body-end') as $__view)
        @include($__view)
    @endforeach

    {{-- Custom confirmation modal (replaces browser's native confirm dialog) --}}
    <x-confirm-modal />

    {{--  TOAST area --}}
    <x-toast />
</body>
</html>
