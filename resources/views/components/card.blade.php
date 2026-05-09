@props([
    'title' => null,
    'subtitle' => null,
    'separator' => false,
])

<div {{ $attributes->merge(['class' => 'rounded-xl border border-base-200 bg-white text-base-content shadow']) }}>
    @if($title)
        <div class="px-5 py-4 @if($separator) border-b border-base-300 @endif flex items-center justify-between">
            <div>
                <h2 class="text-lg font-semibold">{{ $title }}</h2>
                @if($subtitle)
                    <p class="text-sm text-base-content/60 mt-0.5">{{ $subtitle }}</p>
                @endif
            </div>
            @if(isset($menu))
                <div class="shrink-0">{{ $menu }}</div>
            @endif
        </div>
    @endif

    <div class="p-5">
        {{ $slot }}
    </div>

    @if(isset($actions))
        <div class="px-5 py-3 border-t border-base-200 flex items-center gap-3 justify-end">
            {{ $actions }}
        </div>
    @endif
</div>