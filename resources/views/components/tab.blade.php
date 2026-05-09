@props(['name', 'label' => null, 'icon' => null])

<button
    type="button"
    @click="activeTab = '{{ $name }}'"
    :class="activeTab === '{{ $name }}'
        ? 'border-primary text-primary border-b-2 font-medium'
        : 'border-transparent text-base-content/50 hover:text-base-content hover:border-base-300 border-b-2'"
    class="px-4 py-2 text-sm whitespace-nowrap transition-colors duration-200 inline-flex items-center gap-1.5"
>
    @if($icon)
        <x-icon :name="$icon" class="w-4 h-4" />
    @endif
    {{ $label ?? $name }}
</button>
