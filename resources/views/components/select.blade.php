@props([
    'label' => null,
    'icon' => null,
    'placeholder' => null,
    'hint' => null,
    'options' => [],
    'inline' => false,
    'optionValue' => null,
    'optionLabel' => null,
])

@php
$wrapperClasses = $inline ? 'flex items-center gap-3' : 'w-full';
$selectClasses = 'flex w-full h-10 px-3 py-2 text-sm bg-white border rounded-md border-base-300 text-base-content focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary/50 disabled:cursor-not-allowed disabled:opacity-50 appearance-none';

if ($icon) {
    $selectClasses .= ' pl-10';
}

$error = null;
try {
    $modelName = $attributes->wire('model')->value();
    $error = $errors->first($modelName);
} catch (\Throwable) {}
if ($error) {
    $selectClasses .= ' border-error focus:ring-error/50';
}
@endphp

<div class="{{ $wrapperClasses }}">
    @if($label)
        <label class="text-sm font-medium text-base-content/70 mb-1 block">{{ $label }}</label>
    @endif

    <div class="relative w-full">
        @if($icon)
            <span class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-base-content/40">
                <x-icon :name="$icon" class="w-4 h-4" />
            </span>
        @endif

        <select {{ $attributes->merge(['class' => $selectClasses]) }}>
            @if($placeholder)
                <option value="">{{ $placeholder }}</option>
            @endif
            @foreach($options as $key => $value)
                @php
                    if ($optionValue && $optionLabel) {
                        $optValue = data_get($value, $optionValue);
                        $optLabel = data_get($value, $optionLabel);
                    } elseif (is_scalar($value)) {
                        $optValue = $key;
                        $optLabel = $value;
                    } else {
                        // Array/object without option-value/option-label: use first two values
                        $arr = is_array($value) ? $value : (array) $value;
                        $vals = array_values($arr);
                        $optValue = $vals[0] ?? $key;
                        $optLabel = $vals[1] ?? json_encode($value);
                    }
                @endphp
                <option value="{{ $optValue }}">{{ $optLabel }}</option>
            @endforeach
        </select>

        {{-- Chevron icon --}}
        <span class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none text-base-content/40">
            <x-icon name="caret-down" class="w-4 h-4" />
        </span>

        @if($error)
            <p class="text-error text-xs mt-1">{{ $error }}</p>
        @elseif($hint)
            <p class="text-base-content/40 text-xs mt-1">{{ $hint }}</p>
        @endif
    </div>
</div>
