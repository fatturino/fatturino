@props(['icon', 'title', 'tooltip' => null])

<div {{ $attributes->merge(['class' => 'flex items-center gap-2 mb-3']) }}>
    @if($icon)
        <div class="bg-primary/10 rounded-xl p-2.5">
            <x-icon :name="$icon" class="w-5 h-5 text-primary" />
        </div>
    @endif
    <span class="font-semibold">{{ $title }}</span>
    @if($tooltip)
        <x-icon name="o-information-circle" class="w-3.5 h-3.5 text-base-content/30" x-tooltip="{{ $tooltip }}" />
    @endif
</div>
