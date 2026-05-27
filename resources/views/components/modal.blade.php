@props([
    'title' => null,
    'persistent' => false,
])

@php
$modelName = 'modalOpen';
try { $modelName = $attributes->wire('model')->value(); } catch (\Throwable) {}
@endphp

<div
    x-data="{ {{ $modelName }}: @entangle($attributes->wire('model')) }"
    @keydown.escape.window="{{ $modelName }} = false"
    class="relative z-50"
>
    <template x-teleport="body">
        <div x-show="{{ $modelName }}" class="fixed top-0 left-0 z-[99] flex items-center justify-center w-screen h-screen" x-cloak>
            {{-- Backdrop --}}
            <div x-show="{{ $modelName }}"
                 x-transition:enter="ease-out duration-200"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="ease-in duration-200"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"
                 @if(!$persistent) @click="{{ $modelName }} = false" @endif
                 class="absolute inset-0 w-full h-full bg-black/30">
            </div>

            {{-- Modal content --}}
            <div x-show="{{ $modelName }}"
                 x-trap.inert.noscroll="{{ $modelName }}"
                 x-transition:enter="ease-out duration-200"
                 x-transition:enter-start="opacity-0 translate-y-4 sm:scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave="ease-in duration-150"
                 x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave-end="opacity-0 translate-y-4 sm:scale-95"
                 {{ $attributes->except(['wire:model'])->merge(['class' => 'relative w-full bg-white sm:max-w-lg sm:rounded-xl shadow-xl']) }}>
                <div class="px-6 py-5">
                    @if($title)
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-semibold text-base-content">{{ $title }}</h3>
                            <button @click="{{ $modelName }} = false" class="p-1 rounded-md text-base-content/40 hover:text-base-content hover:bg-base-200 transition-colors">
                                <x-icon name="o-x-mark" class="w-5 h-5" />
                            </button>
                        </div>
                    @endif

                    <div class="text-sm text-base-content">
                        {{ $slot }}
                    </div>

                    @if(isset($actions))
                        <div class="flex justify-end gap-3 mt-6">
                            {{ $actions }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </template>
</div>
