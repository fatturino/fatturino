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
    <a href="{{ $link }}" {{ $attributes->merge(['class' => $classes]) }} wire:navigate @if($isActive) aria-current="page" @endif>
        @if($icon)
            <x-icon :name="$icon" class="w-5 h-5 shrink-0" />
        @endif
        <span class="flex-1">{{ $title }}</span>
        @isset($badgeSlot)
            {{ $badgeSlot }}
        @elseif($badge)
            <span class="text-xs bg-base-content/10 px-1.5 py-0.5 rounded-full">{{ $badge }}</span>
        @endif
    </a>
@else
    <div {{ $attributes->merge(['class' => $classes]) }}>
        @if($icon)
            <x-icon :name="$icon" class="w-5 h-5 shrink-0" />
        @endif
        <span class="flex-1">{{ $title ?? $slot }}</span>
    </div>
@endif
