@php
$iconMap = [
    'success' => 'o-check-circle',
    'error' => 'o-x-circle',
    'warning' => 'o-exclamation-triangle',
    'info' => 'o-information-circle',
];
$bgMap = [
    'success' => 'bg-success text-success-content',
    'error' => 'bg-error text-error-content',
    'warning' => 'bg-warning text-warning-content',
    'info' => 'bg-info text-info-content',
];
@endphp

<div
    x-data
    x-show="$store.toast.show"
    x-transition:enter="transition ease-out duration-300"
    x-transition:enter-start="opacity-0 translate-y-2"
    x-transition:enter-end="opacity-100 translate-y-0"
    x-transition:leave="transition ease-in duration-200"
    x-transition:leave-start="opacity-100 translate-y-0"
    x-transition:leave-end="opacity-0 translate-y-2"
    @click="$store.toast.show = false"
    class="fixed z-[999] cursor-pointer"
    :class="$store.toast.position"
    style="bottom: 1.5rem; right: 1.5rem;"
>
    <div
        class="flex items-center gap-3 px-4 py-3 rounded-lg shadow-lg min-w-[300px] max-w-md"
        :class="{
            'bg-success text-success-content': $store.toast.type === 'success',
            'bg-error text-error-content': $store.toast.type === 'error',
            'bg-warning text-warning-content': $store.toast.type === 'warning',
            'bg-info text-info-content': $store.toast.type === 'info',
        }"
    >
        {{-- Success icon --}}
        <span x-show="$store.toast.type === 'success'">
            <x-icon name="o-check-circle" class="w-5 h-5 shrink-0" />
        </span>
        {{-- Error icon --}}
        <span x-show="$store.toast.type === 'error'">
            <x-icon name="o-x-circle" class="w-5 h-5 shrink-0" />
        </span>
        {{-- Warning icon --}}
        <span x-show="$store.toast.type === 'warning'">
            <x-icon name="o-exclamation-triangle" class="w-5 h-5 shrink-0" />
        </span>
        {{-- Info icon --}}
        <span x-show="$store.toast.type === 'info'">
            <x-icon name="o-information-circle" class="w-5 h-5 shrink-0" />
        </span>
        <div class="flex-1 min-w-0">
            <p class="text-sm font-medium" x-text="$store.toast.message"></p>
            <p x-show="$store.toast.description" x-text="$store.toast.description" class="text-xs opacity-80 mt-0.5"></p>
        </div>
        <button @click.stop="$store.toast.show = false" class="shrink-0 opacity-70 hover:opacity-100">
            <x-icon name="o-x-mark" class="w-4 h-4" />
        </button>
    </div>
</div>
