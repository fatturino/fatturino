@props([
    'label' => null,
    'icon' => null,
    'variant' => 'primary',
    'size' => 'md',
    'link' => null,
    'type' => 'button',
    'responsive' => false,
    'spinner' => null,
    'tooltipLeft' => null,
    'tooltipRight' => null,
    'tooltipBottom' => null,
    'tooltipTop' => null,
    'ariaLabel' => null,
])

@php
$baseClasses = 'inline-flex items-center justify-center font-medium tracking-wide transition-colors duration-200 rounded-md focus:ring-2 focus:ring-offset-2 focus:outline-none disabled:opacity-50 disabled:cursor-not-allowed';

$variantClasses = match($variant) {
    'primary' => 'text-primary-content bg-primary hover:bg-primary/90 focus:ring-primary',
    'secondary' => 'text-secondary-content bg-secondary hover:bg-secondary/90 focus:ring-secondary',
    'ghost' => 'text-base-content/70 bg-transparent hover:bg-base-300/50 hover:text-base-content focus:ring-base-300',
    'outline' => 'text-base-content bg-transparent border border-base-300 hover:bg-base-200 focus:ring-base-300',
    'danger' => 'text-error-content bg-error hover:bg-error/90 focus:ring-error',
    'warning' => 'text-warning-content bg-warning hover:bg-warning/90 focus:ring-warning',
    'success' => 'text-success-content bg-success hover:bg-success/90 focus:ring-success',
    'info' => 'text-info-content bg-info hover:bg-info/90 focus:ring-info',
    default => 'text-primary-content bg-primary hover:bg-primary/90 focus:ring-primary',
};

$sizeClasses = match($size) {
    'xs' => 'px-2 py-1 text-xs gap-1',
    'sm' => 'px-3 py-1.5 text-sm gap-1.5',
    'md' => 'px-4 py-2 text-sm gap-2',
    'lg' => 'px-6 py-3 text-base gap-2',
    default => 'px-4 py-2 text-sm gap-2',
};

// Circle button when only icon, no label
$isCircle = !$label && $icon;
if ($isCircle) {
    $sizeClasses = match($size) {
        'xs' => 'p-1 text-xs',
        'sm' => 'p-1.5 text-sm',
        'md' => 'p-2 text-sm',
        'lg' => 'p-3 text-base',
        default => 'p-2 text-sm',
    };
    $sizeClasses .= ' rounded-full';
}

// Spinner target for Livewire loading states
$spinnerAttrs = '';
if ($spinner) {
    $spinnerAttrs = "wire:loading.attr='disabled' wire:target='{$spinner}'";
}
@endphp

@if($link)
    <a href="{{ $link }}"
       {{ $attributes->merge(['class' => "$baseClasses $variantClasses $sizeClasses"]) }}
       @if($spinner) wire:navigate @endif
    >
        @if($icon)
            <x-icon :name="$icon" />
        @endif
        @if($label)
            <span @if($responsive) class="hidden sm:inline" @endif>{{ $label }}</span>
        @endif
        @if($spinner)
            <x-icon name="spinner" class="animate-spin" wire:loading wire:target="{{ $spinner }}" />
        @endif
    </a>
@else
    <button type="{{ $type }}"
            {{ $attributes->merge(['class' => "$baseClasses $variantClasses $sizeClasses"]) }}
            @if($spinner) wire:loading.attr="disabled" wire:target="{{ $spinner }}" @endif
    >
        @if($icon)
            <span @if($spinner) wire:loading.remove wire:target="{{ $spinner }}" @endif>
                <x-icon :name="$icon" />
            </span>
        @endif
        @if($label)
            <span @if($responsive) class="hidden sm:inline" @endif
                  @if($spinner) wire:loading.remove wire:target="{{ $spinner }}" @endif>
                {{ $label }}
            </span>
        @endif
        @if($spinner)
            <x-icon name="spinner" class="animate-spin" wire:loading wire:target="{{ $spinner }}" />
            <span class="animate-pulse" wire:loading wire:target="{{ $spinner }}">...</span>
        @endif
    </button>
@endif
