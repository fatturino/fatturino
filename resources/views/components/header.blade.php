@props([
    'title' => null,
    'separator' => false,
    'progressIndicator' => false,
])

<div {{ $attributes->merge(['class' => 'mb-6']) }}>
    <div class="flex items-center justify-between flex-wrap gap-3
        @if($separator) border-b border-base-300 pb-4 @endif">

        <div class="flex-1 min-w-0">
            @if($title)
                <h1 class="text-xl font-bold text-base-content">
                    {{ $title }}
                </h1>
            @endif

            @if(isset($subtitle))
                <p {{ $subtitle->attributes->merge(['class' => 'text-sm text-base-content/50 mt-0.5']) }}>
                    {{ $subtitle }}
                </p>
            @endif

            @if(isset($middle))
                <div class="mt-2">
                    {{ $middle }}
                </div>
            @endif
        </div>

        @if(isset($actions))
            <div class="flex items-center gap-2 shrink-0">
                {{ $actions }}
            </div>
        @endif
    </div>

    {{-- Progress indicator during Livewire requests --}}
    @if($progressIndicator)
        <div wire:loading wire:target class="h-0.5 bg-primary/30 rounded-full mt-1 overflow-hidden">
            <div class="h-full bg-primary animate-pulse rounded-full" style="width: 60%"></div>
        </div>
    @endif
</div>
