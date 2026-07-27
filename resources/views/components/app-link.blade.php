@props([
    'href',
    'fullReload' => false,
    'external' => false,
])

@php
    $isExternal = $external || str_starts_with($href, '//') || preg_match('/^[a-z][a-z0-9+.-]*:/i', $href);
    $shouldNavigate = ! $fullReload && ! $isExternal && ! $attributes->has('download') && ! $attributes->has('target');
@endphp

<a href="{{ $href }}" {{ $attributes }} @if($shouldNavigate) wire:navigate @endif>{{ $slot }}</a>
