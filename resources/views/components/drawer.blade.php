@props([
    'title' => null,
    'right' => false,
    'separator' => false,
    'withCloseButton' => false,
    'closeOnEscape' => true,
])

@php
// Determine the Alpine variable name from wire:model
$modelName = 'open';
try {
    $modelName = $attributes->wire('model')->value() ?? 'open';
} catch (\Throwable) {}

$positionClasses = $right ? 'right-0' : 'left-0';
$transformEnter = $right ? 'translate-x-full' : '-translate-x-full';
$transformLeave = $right ? 'translate-x-full' : '-translate-x-full';
$transformEnd = 'translate-x-0';
@endphp

<div
    x-data="{ {{ $modelName }}: @entangle($attributes->wire('model')) }"
    class="relative z-50"
>
    <template x-teleport="body">
        <div
            x-show="{{ $modelName }}"
            @if($closeOnEscape) @keydown.window.escape="{{ $modelName }} = false" @endif
            class="relative z-[99]"
        >
            {{-- Backdrop --}}
            <div
                x-show="{{ $modelName }}"
                x-transition.opacity.duration.300ms
                @click="{{ $modelName }} = false"
                class="fixed inset-0 bg-black/20"
            ></div>

            <div class="fixed inset-0 overflow-hidden">
                <div class="absolute inset-0 overflow-hidden">
                    <div class="flex fixed inset-y-0 {{ $right ? 'right-0' : 'left-0' }} max-w-full">
                        <div
                            x-show="{{ $modelName }}"
                            @click.away="{{ $modelName }} = false"
                            x-transition:enter="transform transition ease-in-out duration-300"
                            x-transition:enter-start="{{ $transformEnter }}"
                            x-transition:enter-end="{{ $transformEnd }}"
                            x-transition:leave="transform transition ease-in-out duration-300"
                            x-transition:leave-start="{{ $transformEnd }}"
                            x-transition:leave-end="{{ $transformEnter }}"
                            {{ $attributes->except(['wire:model', 'wire:model.live'])->merge(['class' => 'w-screen max-w-md']) }}
                        >
                            <div class="flex flex-col h-full bg-white shadow-xl border-l border-base-200">
                                {{-- Header --}}
                                <div class="px-5 py-4 @if($separator) border-b border-base-200 @endif">
                                    <div class="flex items-center justify-between">
                                        @if($title)
                                            <h2 class="text-lg font-semibold text-base-content">{{ $title }}</h2>
                                        @endif
                                        @if($withCloseButton)
                                            <button @click="{{ $modelName }} = false" class="p-1 rounded-md text-base-content/40 hover:text-base-content hover:bg-base-200 transition-colors">
                                                <x-icon name="o-x-mark" class="w-5 h-5" />
                                            </button>
                                        @endif
                                    </div>
                                </div>

                                {{-- Body --}}
                                <div class="flex-1 overflow-y-auto px-5 py-4">
                                    {{ $slot }}
                                </div>

                                {{-- Footer actions --}}
                                @if(isset($actions))
                                    <div class="px-5 py-4 border-t border-base-200">
                                        <div class="flex items-center gap-3 justify-end">
                                            {{ $actions }}
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </template>
</div>
