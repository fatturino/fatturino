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
<body class="min-h-screen font-sans antialiased bg-base-200/50"
      x-data="{ sidebarOpen: false }"
      @keydown.window.escape="sidebarOpen = false">

    {{-- Mobile navbar --}}
    <div class="lg:hidden fixed top-0 inset-x-0 z-40 bg-primary text-primary-content shadow-lg">
        <div class="flex items-center justify-between px-4 h-14">
            <button @click="sidebarOpen = true" class="p-1 -ml-1">
                <x-icon name="o-bars-3" class="w-6 h-6" />
            </button>
            <div class="w-40">
                <livewire:fiscal-year-selector />
            </div>
        </div>
    </div>

    <div class="flex min-h-screen pt-14 lg:pt-0">
        {{-- Sidebar backdrop (mobile) --}}
        <div
            x-show="sidebarOpen"
            x-transition.opacity
            @click="sidebarOpen = false"
            class="fixed inset-0 z-40 bg-black/20 lg:hidden"
        ></div>

        {{-- Sidebar --}}
        <aside
            class="fatturino-sidebar fixed lg:sticky top-0 left-0 z-50 lg:z-auto h-screen w-64 shrink-0 overflow-y-auto flex flex-col
                   transition-transform duration-300 lg:translate-x-0"
            :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
        >
            {{-- BRAND --}}
            <x-app-brand class="p-5 pt-3 hidden lg:block" />

            {{-- FISCAL YEAR SELECTOR (desktop only) --}}
            <div class="px-4 mb-2 hidden lg:block">
                <livewire:fiscal-year-selector />
            </div>

            {{-- Close button (mobile) --}}
            <button @click="sidebarOpen = false" class="lg:hidden absolute top-3 right-3 p-1 text-white/70 hover:text-white">
                <x-icon name="o-x-mark" class="w-5 h-5" />
            </button>

            {{-- MENU --}}
            <nav class="flex-1 px-3 py-2 space-y-0.5">
                {{-- User --}}
                @if($user = auth()->user())
                    <x-menu-separator />

                    <x-list-item :item="$user" value="name" sub-value="email" no-separator no-hover>
                        <x-slot:actions>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <x-button icon="o-power" variant="ghost" size="xs" type="submit" />
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
                            $hasActiveChild = collect($visibleChildren)
                                ->contains(fn ($child) => request()->url() === url($child['link']));
                        @endphp
                        @if(count($visibleChildren))
                            <x-menu-sub :title="$item['title']" :icon="$item['icon']" :active="$hasActiveChild">
                                @foreach($visibleChildren as $child)
                                    <x-menu-item :title="$child['title']" :icon="$child['icon']" :link="$child['link']" />
                                @endforeach
                            </x-menu-sub>
                        @endif
                    @else
                        <x-menu-item :title="$item['title']" :icon="$item['icon']" :link="$item['link']" />
                    @endif
                @endforeach
            </nav>

            {{-- Plugin injection point: sidebar bottom area --}}
            @foreach(app(\App\Services\PluginRegistry::class)->injections('sidebar-bottom') as $__view)
                @include($__view)
            @endforeach

            {{-- Version --}}
            <div class="mt-auto px-5 pb-5 pt-3">
                <span class="text-xs text-white/25 font-sans">v{{ config('app.version') }}</span>
            </div>
        </aside>

        {{-- Main content --}}
        <main class="flex-1 flex flex-col min-w-0">
            <div class="flex flex-col min-h-[calc(100vh-4rem)]">
                <div class="flex-1 p-5 lg:p-8">
                    {{-- Plugin injection point --}}
                    @foreach(app(\App\Services\PluginRegistry::class)->injections('content-before') as $__view)
                        @include($__view)
                    @endforeach

                    {{ $slot }}
                </div>
            </div>
        </main>
    </div>

    {{-- Plugin injection point: fixed overlays, footer bars, etc. --}}
    @foreach(app(\App\Services\PluginRegistry::class)->injections('body-end') as $__view)
        @include($__view)
    @endforeach

    {{-- Custom confirmation modal (replaces browser's native confirm dialog) --}}
    <x-confirm-modal />

    {{-- TOAST area --}}
    <x-toast />
</body>
</html>
