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
    x-effect="document.documentElement.classList.toggle('modal-open', Boolean({{ $modelName }}))"
    @keydown.escape.window="{{ $modelName }} = false"
    class="relative z-50"
>
    <template x-teleport="body">
        <div x-show="{{ $modelName }}" x-trap.inert.noscroll="{{ $modelName }}" class="modal-viewport" x-cloak role="dialog" aria-modal="true" @keydown.escape.window="if (!{{ $persistent ? 'true' : 'false' }}) {{ $modelName }} = false">
            {{-- Backdrop --}}
            <div x-show="{{ $modelName }}"
                 x-transition:enter="ease-out duration-200"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="ease-in duration-200"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"
                 @if(!$persistent) @click="{{ $modelName }} = false" @endif
                 class="modal-backdrop bg-black/30">
            </div>

            {{-- Modal content --}}
            <div x-show="{{ $modelName }}"
                 x-transition:enter="ease-out duration-200"
                 x-transition:enter-start="opacity-0 translate-y-4 sm:scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave="ease-in duration-150"
                 x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave-end="opacity-0 translate-y-4 sm:scale-95"
                 {{ $attributes->except(['wire:model'])->merge(['class' => 'modal-panel modal-panel--md bg-white sm:rounded-xl shadow-xl']) }}>
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
