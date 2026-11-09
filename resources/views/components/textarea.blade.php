@props([
    'label' => null,
    'placeholder' => null,
    'hint' => null,
    'icon' => null,
    'rows' => 4,
    'autoResize' => false,
])

@php
$textareaClasses = 'mt-1.5 block min-h-28 w-full rounded-lg border border-border-strong bg-white px-3 py-2.5 text-sm leading-6 text-content placeholder:text-text-muted focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20 disabled:cursor-not-allowed disabled:bg-surface-muted disabled:text-content-muted';

if ($icon) {
    $textareaClasses .= ' pl-10';
}

$error = null;
try {
    $modelName = $attributes->wire('model')->value();
    $error = $errors->first($modelName);
} catch (\Throwable) {}
if ($error) {
    $textareaClasses .= ' border-danger focus:ring-danger/20';
}
@endphp

<div class="w-full">
    @if($label)
        <label class="block text-sm font-medium text-content">{{ $label }}</label>
    @endif

    <div class="relative">
        @if($icon)
            <span class="pointer-events-none absolute left-0 top-3 flex items-start pl-3 text-content-muted">
                <x-icon :name="$icon" class="size-4" />
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
        <p class="mt-1 text-xs text-danger">{{ $error }}</p>
    @elseif($hint)
        <p class="mt-1 text-xs text-content-muted">{{ $hint }}</p>
    @endif
</div>
