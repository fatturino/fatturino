@props([
    'title' => null,
    'subtitle' => null,
    'separator' => false,
])

<div {{ $attributes->merge(['class' => 'rounded-xl border border-border bg-white text-content']) }}>
    @if($title)
        <div class="flex items-center justify-between px-5 py-4 @if($separator) border-b border-border @endif">
            <div>
                <h2 class="text-lg font-semibold">{{ $title }}</h2>
                @if($subtitle)
                    <p class="mt-0.5 text-sm text-content-muted">{{ $subtitle }}</p>
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
        <div class="flex items-center justify-end gap-3 border-t border-border px-5 py-3">
            {{ $actions }}
        </div>
    @endif
</div>