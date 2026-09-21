@props([
    'label' => null,
    'hint' => null,
    'disabled' => false,
])

@php
    $model = $attributes->wire('model')->value();
    $id = 'toggle-'.str($model ?: uniqid('field-', true))->slug();
    $hintId = $hint ? $id.'-hint' : null;
@endphp

<div
    x-data="{ on: @entangle($attributes->wire('model')) }"
    class="flex min-h-11 items-center justify-between gap-4 rounded-lg bg-surface-muted p-3"
>
    <div class="min-w-0">
        @if($label)
            <label x-on:click="on = !on" class="cursor-pointer text-sm font-medium text-content">{{ $label }}</label>
        @endif

        @if($hint)
            <p id="{{ $hintId }}" class="mt-0.5 text-xs leading-5 text-content-muted">{{ $hint }}</p>
        @endif
    </div>

    <button
        id="{{ $id }}"
        type="button"
        role="switch"
        x-on:click="on = !on"
        :aria-checked="on"
        aria-label="{{ $label }}"
        @if($hintId) aria-describedby="{{ $hintId }}" @endif
        @disabled($disabled)
        :class="on ? 'bg-primary' : 'bg-border-strong'"
        class="relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
    >
        <span :class="on ? 'translate-x-6' : 'translate-x-1'" class="size-4 rounded-full bg-white shadow-sm transition-transform duration-200 ease-in-out"></span>
        <span class="sr-only">{{ $label }}</span>
    </button>
</div>
