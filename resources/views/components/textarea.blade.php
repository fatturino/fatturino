@props([
    'label' => null,
    'placeholder' => null,
    'hint' => null,
    'icon' => null,
    'rows' => 4,
    'autoResize' => false,
])

@php
$textareaClasses = 'flex w-full px-3 py-2 text-sm bg-white border rounded-md border-base-300 placeholder:text-base-content/40 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary/50 disabled:cursor-not-allowed disabled:opacity-50';

if ($icon) {
    $textareaClasses .= ' pl-10';
}

$error = null;
try {
    $modelName = $attributes->wire('model')->value();
    $error = $errors->first($modelName);
} catch (\Throwable) {}
if ($error) {
    $textareaClasses .= ' border-error focus:ring-error/50';
}
@endphp

<div class="w-full">
    @if($label)
        <label class="text-sm font-medium text-base-content/70 mb-1 block">{{ $label }}</label>
    @endif

    <div class="relative">
        @if($icon)
            <span class="absolute top-3 left-0 flex items-start pl-3 pointer-events-none text-base-content/40">
                <x-icon :name="$icon" class="w-4 h-4" />
            </span>
        @endif

        <textarea
            rows="{{ $rows }}"
            placeholder="{{ $placeholder }}"
            {{ $attributes->merge(['class' => $textareaClasses]) }}
            @if($autoResize)
            x-data
            @input="$el.style.height = 'auto'; $el.style.height = $el.scrollHeight + 'px'"
            @endif
        ></textarea>
    </div>

    @if($error)
        <p class="text-error text-xs mt-1">{{ $error }}</p>
    @elseif($hint)
        <p class="text-base-content/40 text-xs mt-1">{{ $hint }}</p>
    @endif
</div>
