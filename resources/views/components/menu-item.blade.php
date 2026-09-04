@props([
    'title' => null,
    'icon' => null,
    'link' => null,
    'active' => false,
    'badge' => null,
])

@php
$isActive = $active || ($link && request()->url() === url($link));
$classes = 'flex items-center gap-3 px-4 py-2.5 text-sm rounded-lg transition-colors '
    . ($isActive
        ? 'bg-base-content/10 text-base-content font-semibold'
        : 'text-base-content/70 hover:bg-base-content/5 hover:text-base-content');
@endphp

@if($link)
    <x-app-link :href="$link" {{ $attributes->merge(['class' => $classes]) }} @if($isActive) aria-current="page" @endif>
        @if($icon)
            <x-icon :name="$icon" class="w-5 h-5 shrink-0" />
        @endif
        <span class="flex-1 truncate">{{ $title }}</span>
        @isset($badgeSlot)
            {{ $badgeSlot }}
        @elseif($badge)
            <x-badge :value="$badge" inverted />
        @endif
    </x-app-link>
@else
    <div {{ $attributes->merge(['class' => $classes]) }}>
        @if($icon)
            <x-icon :name="$icon" class="w-5 h-5 shrink-0" />
        @endif
        <span class="flex-1 truncate">{{ $title ?? $slot }}</span>
    </div>
@endif
