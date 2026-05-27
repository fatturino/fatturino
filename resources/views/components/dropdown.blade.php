@props([
    'right' => false,
    'noXAnchor' => false,
])

<div
    x-data="{ open: false }"
    class="relative inline-block"
>
    {{-- Trigger --}}
    <div @click.stop="open = !open" class="cursor-pointer">
        @if(isset($trigger))
            {{ $trigger }}
        @else
            <x-button icon="o-ellipsis-vertical" variant="ghost" size="sm" />
        @endif
    </div>

    {{-- Menu --}}
    <div
        x-show="open"
        @click.away="open = false"
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-100"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        class="absolute z-50 mt-1 w-56 rounded-lg bg-white border border-base-200 shadow-lg {{ $right ? 'right-0' : 'left-0' }}"
        x-cloak
    >
        <div class="p-1 text-sm text-base-content">
            {{ $slot }}
        </div>
    </div>
</div>
