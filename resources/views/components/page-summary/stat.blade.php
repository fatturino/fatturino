@props([
    'label',
    'value' => null,
    'detail' => null,
    'valueClass' => 'text-content',
    'detailClass' => 'text-content-muted',
    'truncate' => false,
    'valueId' => null,
])

@php
$valueClasses = 'mt-1 text-xl font-semibold tabular-nums '.$valueClass.($truncate ? ' truncate' : '');
@endphp

<div {{ $attributes->merge(['class' => 'min-w-0 bg-white px-4 py-4 sm:px-5']) }}>
    <dt class="text-xs font-medium text-content-muted">{{ $label }}</dt>
    <dd {!! $valueId ? 'id="'.e($valueId).'" ' : '' !!}class="{{ $valueClasses }}">{{ $value }}</dd>
    @if($detail !== null)
        <p class="mt-1 text-xs {{ $detailClass }}">{{ $detail }}</p>
    @endif
    {{ $slot }}
</div>
