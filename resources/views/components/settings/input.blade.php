@props(['label' => null, 'type' => 'text'])

@php
$inputClasses = ($label ? 'mt-1.5 ' : '').'block h-11 w-full rounded-lg border border-border-strong bg-white px-3 text-sm text-content placeholder:text-text-muted focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20 disabled:cursor-not-allowed disabled:bg-surface-muted disabled:text-content-muted';
$model = null;
try {
    $model = $attributes->wire('model')->value();
} catch (\Throwable) {
}
@endphp

@if($label)
    <label class="block text-sm font-medium text-content">
        {{ $label }}
        <input {{ $attributes->merge(['class' => $inputClasses]) }} type="{{ $type }}">
        @if($model)
            @error($model)<span class="mt-1 block text-xs text-danger">{{ $message }}</span>@enderror
        @endif
    </label>
@else
    <div>
        <input {{ $attributes->merge(['class' => $inputClasses]) }} type="{{ $type }}">
        @if($model)
            @error($model)<span class="mt-1 block text-xs text-danger">{{ $message }}</span>@enderror
        @endif
    </div>
@endif
