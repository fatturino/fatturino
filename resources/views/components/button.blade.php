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
$baseClasses = 'inline-flex items-center justify-center rounded-lg font-medium transition-colors duration-150 focus:outline-none focus:ring-2 focus:ring-primary/20 disabled:cursor-not-allowed disabled:opacity-50';

$variantClasses = match($variant) {
    'primary' => 'bg-primary text-white hover:bg-primary-hover',
    'secondary', 'outline' => 'border border-border bg-white text-content hover:border-border-strong hover:bg-surface-muted',
    'ghost' => 'bg-transparent text-content-muted hover:bg-surface-muted hover:text-content',
    'danger' => 'bg-danger text-white hover:bg-[#912018] focus:ring-danger/20',
    'warning' => 'bg-warning text-white hover:bg-[#854B06] focus:ring-warning/20',
    'success' => 'bg-success text-white hover:bg-[#055F3A] focus:ring-success/20',
    'info' => 'bg-info text-white hover:bg-[#144CA5] focus:ring-info/20',
    default => 'bg-primary text-white hover:bg-primary-hover',
};

$sizeClasses = match($size) {
    'xs' => 'px-2 py-1 text-xs gap-1',
    'sm' => 'px-3 py-1.5 text-sm gap-1.5',
    'md' => 'px-4 py-2 text-sm gap-2',
    'cta' => 'h-11 px-4 text-sm gap-2',
    'lg' => 'h-11 px-6 text-base gap-2',
    default => 'px-4 py-2 text-sm gap-2',
};

$isIconOnly = ! $label && $icon;
if ($isIconOnly) {
    $sizeClasses = match($size) {
        'xs' => 'size-7 text-xs',
        'sm' => 'size-8 text-sm',
        'md' => 'size-9 text-sm',
        'cta' => 'size-11 text-base',
        'lg' => 'size-11 text-base',
        default => 'size-8 text-sm',
    };
}

$attributes = $attributes->class(trim("$baseClasses $variantClasses $sizeClasses"));
if ($isIconOnly && $ariaLabel) {
    $attributes = $attributes->merge(['aria-label' => $ariaLabel]);
}
@endphp

@php
$spinnerTarget = $attributes->get('wire:target', $spinner);
@endphp

@if($link)
    <x-app-link :href="$link" :full-reload="$attributes->has('download')" {{ $attributes }}>
        @if($icon)
            <x-icon :name="$icon" />
        @endif
        @if($label)
            <span @if($responsive) class="hidden sm:inline" @endif>{{ $label }}</span>
        @endif
        @if($spinner)
            <x-icon name="o-spinner" class="animate-spin" wire:loading wire:target="{{ $spinnerTarget }}" />
        @endif
    </x-app-link>
@else
    <button
        type="{{ $type }}"
        @if($spinner) wire:loading.attr="disabled" wire:target="{{ $spinnerTarget }}" @endif
        {{ $attributes->except(['wire:target']) }}
    >
        @if($icon)
            <span @if($spinner) wire:loading.remove wire:target="{{ $spinnerTarget }}" @endif><x-icon :name="$icon" /></span>
        @endif
        @if($label)
            <span @if($responsive) class="hidden sm:inline" @endif @if($spinner) wire:loading.remove wire:target="{{ $spinnerTarget }}" @endif>{{ $label }}</span>
        @endif
        @if($spinner)
            <x-icon name="o-spinner" class="animate-spin" wire:loading wire:target="{{ $spinnerTarget }}" />
            <span class="animate-pulse" wire:loading wire:target="{{ $spinnerTarget }}">...</span>
        @endif
    </button>
@endif
