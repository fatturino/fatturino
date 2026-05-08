@props([
    'title' => null,
    'icon' => null,
    'active' => false,
])

@php
$isActive = $active || request()->url() === url($attributes->get('link', ''));
@endphp

<div
    x-data="{ open: @json($isActive) }"
    class="select-none"
>
    <button
        @click="open = !open"
        class="flex items-center gap-3 w-full px-4 py-2.5 text-sm rounded-lg transition-colors text-white/70 hover:bg-white/10 hover:text-white"
    >
        @if($icon)
            <x-icon :name="$icon" class="w-5 h-5 shrink-0" />
        @endif
        <span class="flex-1 text-left">{{ $title }}</span>
        <x-icon name="o-chevron-down" class="w-4 h-4 shrink-0 transition-transform" ::class="open ? 'rotate-180' : ''" />
    </button>

    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 -translate-y-1"
        x-transition:enter-end="opacity-100 translate-y-0"
        class="ml-4 border-l border-white/10 my-1"
    >
        {{ $slot }}
    </div>
</div>
