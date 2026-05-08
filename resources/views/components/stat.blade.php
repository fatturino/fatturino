@props([
    'title' => null,
    'value' => null,
    'icon' => null,
    'description' => null,
    'color' => null,
    'tooltip' => null,
])

<div {{ $attributes->merge(['class' => 'bg-base-100 rounded-xl border border-base-200 p-5 h-full shadow']) }}>
    <div class="flex items-center gap-2 mb-2">
        @if($icon)
            <div class="bg-primary/10 rounded-xl p-2.5">
                <x-icon :name="$icon" class="w-5 h-5 text-primary" />
            </div>
        @endif
        <span class="text-xs text-base-content/50 uppercase tracking-wide">{{ $title }}</span>
        @if($tooltip)
            <x-icon name="o-information-circle" class="w-3.5 h-3.5 text-base-content/30" x-tooltip="{{ $tooltip }}" />
        @endif
    </div>
    <div class="text-2xl font-bold">{{ $value ?? $slot }}</div>
    @if($description)
        <div class="text-xs mt-1 {{ $color ?? 'text-base-content/40' }}">{{ $description }}</div>
    @endif
</div>
