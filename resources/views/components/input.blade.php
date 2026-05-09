@props([
    'label' => null,
    'icon' => null,
    'placeholder' => null,
    'hint' => null,
    'type' => 'text',
    'inline' => false,
])

@php
$wrapperClasses = $inline ? 'flex items-center gap-3' : 'w-full';
$inputClasses = 'flex w-full h-11 px-3 py-2 text-sm bg-white border rounded-md border-base-300 placeholder:text-base-content/40 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary/50 disabled:cursor-not-allowed disabled:opacity-50';

if ($icon) {
    $inputClasses .= ' pl-10';
}

// Error state
$error = $attributes->wire('model') ? $errors->first($attributes->wire('model')->value()) : null;
if ($error) {
    $inputClasses .= ' border-error focus:ring-error/50';
}
@endphp

<div class="{{ $wrapperClasses }}">
    @if($label)
        <label for="{{ $inputId }}" class="text-sm font-medium text-base-content/70 mb-1 block">{{ $label }}</label>
    @endif

    @php $inputId = 'input-' . uniqid(); @endphp

    <div class="relative w-full">
        @if($icon)
            <span class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-base-content/40">
                <x-icon :name="$icon" class="w-4 h-4" />
            </span>
        @endif

        <input
            id="{{ $inputId }}"
            type="{{ $type }}"
            placeholder="{{ $placeholder }}"
            {{ $attributes->merge(['class' => $inputClasses]) }}
        />

        @if($error)
            <p class="text-error text-xs mt-1">{{ $error }}</p>
        @elseif($hint)
            <p class="text-base-content/40 text-xs mt-1">{{ $hint }}</p>
        @endif
    </div>
</div>
