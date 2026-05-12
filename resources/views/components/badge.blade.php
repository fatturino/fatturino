@props([
    'value' => null,
    'icon' => null,
    'variant' => 'default',
    'type' => 'soft', // soft, solid, outline
])

@php
// Map variant to CSS variable suffix. 'danger' uses 'error' (the CSS var name).
$colorVar = match($variant) {
    'danger' => 'error',
    default => $variant,
};

// Neutral / default fallback uses base tokens, not semantic colors.
$isNeutral = in_array($variant, ['neutral', 'default']);

// Build style attribute matching the requested type.
$style = match($type) {
    'solid' => $isNeutral
        ? 'background-color: var(--color-neutral); color: var(--color-neutral-content)'
        : 'background-color: var(--color-' . $colorVar . '); color: var(--color-' . $colorVar . '-content)',
    'outline' => $isNeutral
        ? 'border: 1px solid var(--color-base-300); color: var(--color-base-content); opacity: 0.7'
        : 'border: 1px solid var(--color-' . $colorVar . '); color: var(--color-' . $colorVar . ')',
    default => $isNeutral
        ? 'background-color: var(--color-base-200); color: color-mix(in oklab, var(--color-base-content) 70%, transparent)'
        : 'background-color: color-mix(in oklab, var(--color-' . $colorVar . ') 10%, transparent); color: var(--color-' . $colorVar . ')',
};
@endphp

<span {{ $attributes->merge(['style' => $style, 'class' => 'inline-flex items-center gap-1 px-2.5 py-0.5 text-xs font-semibold rounded-full whitespace-nowrap']) }}>
    @if($icon)
        <x-icon :name="$icon" class="w-3.5 h-3.5" />
    @endif
    <span>{{ $value ?? $slot }}</span>
</span>
