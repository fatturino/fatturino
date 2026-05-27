@props([
    'title' => null,
    'description' => null,
    'icon' => null,
    'timestamp' => null,
    'last' => false,
])

<div class="flex gap-4 {{ !$last ? 'pb-6' : '' }}">
    {{-- Timeline line + dot --}}
    <div class="flex flex-col items-center">
        <div class="w-8 h-8 rounded-full bg-primary/10 flex items-center justify-center shrink-0">
            @if($icon)
                <x-icon :name="$icon" class="w-4 h-4 text-primary" />
            @else
                <div class="w-2 h-2 rounded-full bg-primary"></div>
            @endif
        </div>
        @if(!$last)
            <div class="w-0.5 flex-1 bg-base-300 my-1"></div>
        @endif
    </div>

    {{-- Content --}}
    <div class="flex-1 min-w-0 pb-2">
        <div class="flex items-center gap-2 flex-wrap">
            @if($title)
                <span class="font-semibold text-sm">{{ $title }}</span>
            @endif
            @if($timestamp)
                <span class="text-xs text-base-content/40">{{ $timestamp }}</span>
            @endif
        </div>
        @if($description)
            <p class="text-sm text-base-content/60 mt-0.5">{{ $description }}</p>
        @endif
    </div>
</div>
