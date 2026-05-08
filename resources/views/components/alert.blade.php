@props([
    'title' => null,
    'icon' => null,
    'variant' => 'info', // info, success, warning, danger
    'dismissible' => false,
])

@php
$colors = match($variant) {
    'info' => 'bg-info/10 text-info border-info/30',
    'success' => 'bg-success/10 text-success border-success/30',
    'warning' => 'bg-warning/10 text-warning border-warning/30',
    'danger' => 'bg-error/10 text-error border-error/30',
    default => 'bg-info/10 text-info border-info/30',
};

$icons = match($variant) {
    'info' => 'info',
    'success' => 'check-circle',
    'warning' => 'warning',
    'danger' => 'x-circle',
    default => 'info',
};
@endphp

<div x-data="{ show: true }" x-show="show"
     {{ $attributes->merge(['class' => "flex items-start gap-3 p-4 border rounded-lg text-sm $colors"]) }}>
    @if($icon)
        <x-icon :name="$icon" class="w-5 h-5 shrink-0 mt-0.5" />
    @else
        <x-icon :name="$icons" class="w-5 h-5 shrink-0 mt-0.5" />
    @endif

    <div class="flex-1">
        @if($title)
            <p class="font-semibold mb-1">{{ $title }}</p>
        @endif
        <div class="opacity-90">{{ $slot }}</div>
    </div>

    @if($dismissible)
        <button type="button" @click="show = false" class="shrink-0 opacity-60 hover:opacity-100 transition-opacity">
            <x-icon name="x" class="w-4 h-4" />
        </button>
    @endif
</div>
