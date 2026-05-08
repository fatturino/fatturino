@props([
    'label' => null,
    'hint' => null,
    'leftLabel' => null,
    'rightLabel' => null,
])

@php
$toggleWrapperClasses = 'relative inline-flex items-center h-6 w-11 rounded-full cursor-pointer transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary/50';
$toggleDotClasses = 'inline-block w-4 h-4 transform bg-white rounded-full transition-transform duration-200 ease-in-out translate-x-1';
$toggleActiveWrapper = 'bg-primary';
$toggleInactiveWrapper = 'bg-base-300';

// We use x-data to track state
@endphp

<div x-data="{ on: @json($attributes->get('checked', false)) }"
     class="flex items-center gap-3">
    @if($leftLabel)
        <span class="text-sm text-base-content/70">{{ $leftLabel }}</span>
    @endif

    <button
        type="button"
        role="switch"
        :aria-checked="on"
        :class="on ? '{{ $toggleActiveWrapper }}' : '{{ $toggleInactiveWrapper }}'"
        class="{{ $toggleWrapperClasses }}"
        @click="on = !on; $wire.set('{{ $attributes->wire('model')->value() }}', on)"
        {{ $attributes->except(['class']) }}
    >
        <span :class="on ? 'translate-x-6' : 'translate-x-1'" class="{{ $toggleDotClasses }}"></span>
    </button>

    @if($rightLabel)
        <span class="text-sm text-base-content/70">{{ $rightLabel }}</span>
    @endif
</div>

@if($hint)
    <p class="text-base-content/40 text-xs mt-1">{{ $hint }}</p>
@endif
