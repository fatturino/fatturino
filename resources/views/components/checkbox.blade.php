@props([
    'label' => null,
    'hint' => null,
])

@php
$error = null;
try {
    $modelName = $attributes->wire('model')->value();
    $error = $errors->first($modelName);
} catch (\Throwable) {}

// Generate unique ID for label association
$id = 'checkbox-' . uniqid();
@endphp

<div>
    <div class="flex items-start">
        <div class="flex items-center h-5">
            <input
                type="checkbox"
                id="{{ $id }}"
                {{ $attributes->merge(['class' => 'hidden peer']) }}
            >
            <label
                for="{{ $id }}"
                class="peer-checked:[&_svg]:scale-100 text-sm font-medium text-base-content/70 peer-checked:text-base-content [&_svg]:scale-0 peer-checked:[&_.custom-checkbox]:border-primary peer-checked:[&_.custom-checkbox]:bg-primary select-none flex items-center gap-2 cursor-pointer"
            >
                <span class="flex items-center justify-center w-5 h-5 border-2 border-base-300 rounded custom-checkbox text-white transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor" class="w-3 h-3 duration-300 ease-out">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                    </svg>
                </span>
                @if($label)
                    <span>{{ $label }}</span>
                @endif
            </label>
        </div>
    </div>

    @if($error)
        <p class="text-error text-xs mt-1 ml-7">{{ $error }}</p>
    @elseif($hint)
        <p class="text-base-content/40 text-xs mt-1 ml-7">{{ $hint }}</p>
    @endif
</div>
