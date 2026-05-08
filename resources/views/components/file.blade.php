@props([
    'label' => null,
    'hint' => null,
    'accept' => null,
])

@php
$fileInputClasses = 'block w-full text-sm text-base-content/70 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-medium file:bg-primary file:text-primary-content hover:file:bg-primary/90 file:cursor-pointer file:transition-colors cursor-pointer';

$error = null;
try {
    $modelName = $attributes->wire('model')->value();
    $error = $errors->first($modelName);
} catch (\Throwable) {}
@endphp

<div class="w-full">
    @if($label)
        <label class="text-sm font-medium text-base-content/70 mb-1 block">{{ $label }}</label>
    @endif

    <input
        type="file"
        @if($accept) accept="{{ $accept }}" @endif
        {{ $attributes->merge(['class' => $fileInputClasses]) }}
    >

    @if($error)
        <p class="text-error text-xs mt-1">{{ $error }}</p>
    @elseif($hint)
        <p class="text-base-content/40 text-xs mt-1">{{ $hint }}</p>
    @endif
</div>
