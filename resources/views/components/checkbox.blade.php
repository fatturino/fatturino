@props([
    'label' => null,
    'hint' => null,
])

@php
$checkboxClasses = 'w-4 h-4 text-primary bg-white border-base-300 rounded focus:ring-2 focus:ring-primary/50 focus:ring-offset-2 cursor-pointer disabled:cursor-not-allowed disabled:opacity-50';

$error = null;
try {
    $modelName = $attributes->wire('model')->value();
    $error = $errors->first($modelName);
} catch (\Throwable) {}
@endphp

<label class="inline-flex items-start gap-2 cursor-pointer">
    <input type="checkbox"
           {{ $attributes->merge(['class' => $checkboxClasses]) }}
    >
    @if($label)
        <span class="text-sm text-base-content/80">{{ $label }}</span>
    @endif
</label>

@if($error)
    <p class="text-error text-xs mt-1">{{ $error }}</p>
@elseif($hint)
    <p class="text-base-content/40 text-xs mt-1">{{ $hint }}</p>
@endif
