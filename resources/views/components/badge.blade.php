@props([
    'value' => null,
    'icon' => null,
    'variant' => 'default',
    'type' => 'soft', // soft, solid, outline
])

@php
$baseClasses = 'inline-flex items-center gap-1 px-2.5 py-0.5 text-xs font-semibold rounded-full whitespace-nowrap';

$colors = match($variant) {
    'primary' => ['soft' => 'bg-primary/10 text-primary', 'solid' => 'bg-primary text-primary-content', 'outline' => 'bg-transparent text-primary border border-primary'],
    'secondary' => ['soft' => 'bg-secondary/10 text-secondary', 'solid' => 'bg-secondary text-secondary-content', 'outline' => 'bg-transparent text-secondary border border-secondary'],
    'success' => ['soft' => 'bg-success/10 text-success', 'solid' => 'bg-success text-success-content', 'outline' => 'bg-transparent text-success border border-success'],
    'warning' => ['soft' => 'bg-warning/10 text-warning', 'solid' => 'bg-warning text-warning-content', 'outline' => 'bg-transparent text-warning border border-warning'],
    'danger' => ['soft' => 'bg-error/10 text-error', 'solid' => 'bg-error text-error-content', 'outline' => 'bg-transparent text-error border border-error'],
    'info' => ['soft' => 'bg-info/10 text-info', 'solid' => 'bg-info text-info-content', 'outline' => 'bg-transparent text-info border border-info'],
    'neutral' => ['soft' => 'bg-base-200 text-base-content/70', 'solid' => 'bg-neutral text-neutral-content', 'outline' => 'bg-transparent text-base-content/70 border border-base-300'],
    'accent' => ['soft' => 'bg-accent/10 text-accent', 'solid' => 'bg-accent text-accent-content', 'outline' => 'bg-transparent text-accent border border-accent'],
    default => ['soft' => 'bg-base-200 text-base-content/70', 'solid' => 'bg-neutral text-neutral-content', 'outline' => 'bg-transparent text-base-content/70 border border-base-300'],
};

$classes = $baseClasses . ' ' . ($colors[$type] ?? 'bg-base-200 text-base-content/70');
@endphp

<span {{ $attributes->merge(['class' => $classes]) }}>
    @if($icon)
        <x-icon :name="$icon" class="w-3.5 h-3.5" />
    @endif
    <span>{{ $value ?? $slot }}</span>
</span>
