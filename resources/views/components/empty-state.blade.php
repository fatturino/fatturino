@props([
    'icon' => 'o-inbox',
    'title',
    'body' => null,
])

<div {{ $attributes->merge(['class' => 'px-5 py-14 text-center']) }}>
    <x-icon :name="$icon" class="mx-auto size-8 text-content-muted" aria-hidden="true" />
    <p class="mt-3 text-sm font-medium text-content">{{ $title }}</p>
    @if($body)
        <p class="mt-1 text-sm text-content-muted">{{ $body }}</p>
    @endif
    {{ $slot }}
    @isset($actions)
        <div class="mt-4 flex flex-wrap justify-center gap-2">
            {{ $actions }}
        </div>
    @endisset
</div>
