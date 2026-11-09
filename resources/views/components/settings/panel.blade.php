@props([
    'title' => null,
    'subtitle' => null,
    'flush' => false,
    'tone' => 'default',
])

@php
$borderClass = $tone === 'warning' ? 'border-warning/30' : 'border-border-light';
$baseClass = $flush
    ? "overflow-hidden rounded-xl border {$borderClass} bg-white shadow-[var(--shadow-card)]"
    : "rounded-xl border {$borderClass} bg-white p-5 shadow-[var(--shadow-card)] sm:p-6";
$hasHeading = filled($title) || filled($subtitle) || isset($heading);
@endphp

<article {{ $attributes->merge(['class' => $baseClass]) }}>
    @isset($heading)
        {{ $heading }}
    @else
        @if($title)
            <h2 class="text-base font-bold text-content">{{ $title }}</h2>
        @endif
        @if($subtitle)
            <p @class(['text-sm text-content-muted', 'mt-1' => filled($title)])>{{ $subtitle }}</p>
        @endif
    @endisset

    @if($flush)
        {{ $slot }}
    @elseif($hasHeading)
        <div class="mt-5">
            {{ $slot }}
        </div>
    @else
        {{ $slot }}
    @endif
</article>
