@props([
    'item' => null,
    'value' => 'name',
    'subValue' => 'email',
    'noSeparator' => false,
    'noHover' => false,
    'avatar' => null,
    'icon' => null,
])

@php
$item = is_array($item) ? $item : (method_exists($item, 'toArray') ? $item->toArray() : (array) $item);
@endphp

<div class="flex items-center gap-3 px-3 py-2 {{ !$noHover ? 'hover:bg-base-200/50 rounded-lg' : '' }} {{ !$noSeparator ? 'border-b border-base-200/50' : '' }}">
    @if($avatar)
        <div class="w-8 h-8 rounded-full bg-primary/10 flex items-center justify-center text-primary font-semibold text-sm shrink-0">
            {{ strtoupper(substr($item[$value] ?? '?', 0, 1)) }}
        </div>
    @elseif($icon)
        <x-icon :name="$icon" class="w-5 h-5 text-base-content/40 shrink-0" />
    @endif

    <div class="flex-1 min-w-0">
        <div class="text-sm font-medium truncate">{{ $item[$value] ?? '' }}</div>
        @if(isset($item[$subValue]))
            <div class="text-xs text-base-content/40 truncate">{{ $item[$subValue] }}</div>
        @endif
    </div>

    @if(isset($actions))
        <div class="shrink-0">
            {{ $actions }}
        </div>
    @endif
</div>
