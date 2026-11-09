@props([
    'target' => null,
])

<div
    wire:loading.flex
    @if($target) wire:target="{{ $target }}" @endif
    {{ $attributes->merge([
        'class' => 'hidden items-center gap-2 rounded-lg border border-info/20 bg-info-bg px-4 py-3 text-sm text-info',
        'role' => 'status',
    ]) }}
>
    <span class="size-4 animate-pulse rounded bg-current/30" aria-hidden="true"></span>
    {{ $slot }}
</div>
