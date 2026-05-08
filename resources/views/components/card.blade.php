@props([
    'title' => null,
    'separator' => false,
])

<div {{ $attributes->merge(['class' => 'rounded-lg border border-base-300 bg-white text-base-content shadow-sm']) }}>
    @if($title)
        <div class="px-5 py-4 @if($separator) border-b border-base-300 @endif flex items-center justify-between">
            <h2 class="text-lg font-semibold">{{ $title }}</h2>
            @if(isset($menu))
                <div class="shrink-0">{{ $menu }}</div>
            @endif
        </div>
    @endif

    <div class="p-5 {{ $title ? '' : '' }}">
        {{ $slot }}
    </div>
</div>
