@props([
    'selectedCount' => 0,
])

<div {{ $attributes->merge(['class' => 'flex items-center gap-3 px-5 py-3 border border-base-300 rounded-lg bg-base-200/50']) }}>
    {{-- Search slot --}}
    @if(isset($search))
        <div class="flex-1 min-w-0">{{ $search }}</div>
    @endif

    {{-- Filters slot --}}
    @if(isset($filters))
        <div class="flex items-center gap-2">{{ $filters }}</div>
    @endif
</div>
