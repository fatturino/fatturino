@props(['wireModel' => null])

@php
    // Extract wire:model value from attributes for Livewire two-way binding
    $wireModel = $attributes->whereStartsWith('wire:model')->first();
@endphp

<div
    x-data="{ activeTab: '' }"
    @if($wireModel)
    x-init="
        activeTab = $wire.get('{{ $wireModel }}') || '';
        $watch('activeTab', value => $wire.set('{{ $wireModel }}', value));
        $wire.$watch('{{ $wireModel }}', value => { if (value) activeTab = value; });
    "
    @endif
    {{ $attributes->whereDoesntStartWith('wire:model') }}
>
    <div class="flex border-b border-base-200 overflow-x-auto">
        {{ $tabs ?? '' }}
    </div>

    <div class="mt-4">
        {{ $slot }}
    </div>
</div>
