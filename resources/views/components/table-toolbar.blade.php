@props([
    'selectedCount' => 0,
])

<div class="flex items-center gap-3 px-5 py-3 border border-base-300 rounded-lg bg-base-200/50">
    {{-- Search slot --}}
    @if(isset($search))
        <div class="flex-1 min-w-0">{{ $search }}</div>
    @endif

    {{-- Filters slot --}}
    @if(isset($filters))
        <div class="flex items-center gap-2">{{ $filters }}</div>
    @endif

    {{-- Separator between filters and bulk actions --}}
    @if(isset($bulk) && isset($filters))
        <div class="w-px h-6 bg-base-300"></div>
    @endif

    {{-- Bulk actions --}}
    @if(isset($bulk))
        <div @class(['flex items-center gap-2', 'opacity-40 pointer-events-none' => $selectedCount === 0])>
            {{ $bulk }}
        </div>
    @endif
</div>
