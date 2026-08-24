@props([
    'label',
    'description',
    'accept',
    'multiple' => false,
    'errorKey',
    'target',
])

@php
    $inputId = 'import-file-'.uniqid();
    $errorMessage = $errors->first($errorKey) ?: $errors->first($errorKey.'.0');
@endphp

<div
    x-data="{ dragging: false, fileNames: [], uploading: false, progress: 0 }"
    x-on:dragenter.prevent="dragging = true"
    x-on:dragover.prevent="dragging = true"
    x-on:dragleave.prevent="dragging = false"
    x-on:drop.prevent="dragging = false; $refs.input.files = $event.dataTransfer.files; $refs.input.dispatchEvent(new Event('change', { bubbles: true }))"
    x-on:livewire-upload-start="uploading = true; progress = 0"
    x-on:livewire-upload-finish="uploading = false; progress = 100"
    x-on:livewire-upload-error="uploading = false"
    x-on:livewire-upload-cancel="uploading = false"
    x-on:livewire-upload-progress="progress = $event.detail.progress"
    x-on:import-type-changed.window="fileNames = []; uploading = false; progress = 0"
    :class="dragging ? 'border-primary bg-primary-subtle' : 'border-border bg-white hover:border-border-strong'"
    class="relative rounded-xl border-2 border-dashed p-6 text-center transition-colors sm:p-8"
>
    <input
        x-ref="input"
        id="{{ $inputId }}"
        type="file"
        accept="{{ $accept }}"
        @if($multiple) multiple @endif
        wire:loading.attr="disabled"
        wire:target="import"
        x-on:change="fileNames = Array.from($event.target.files).map(file => file.name)"
        {{ $attributes->merge(['class' => 'peer sr-only']) }}
    >

    <div class="mx-auto flex size-11 items-center justify-center rounded-full bg-primary-subtle text-primary" aria-hidden="true">
        <x-icon name="o-arrow-up-tray" class="size-5" />
    </div>
    <p class="mt-4 text-sm font-semibold text-content">{{ $label }}</p>
    <p class="mt-1 text-sm text-content-muted">{{ $description }}</p>

    <label for="{{ $inputId }}" class="mt-4 inline-flex h-10 cursor-pointer items-center justify-center rounded-lg border border-border bg-white px-4 text-sm font-medium text-content transition hover:border-border-strong hover:bg-surface-muted peer-focus:ring-2 peer-focus:ring-primary/20">
        Scegli {{ $multiple ? 'i file' : 'il file' }}
    </label>
    <p class="mt-3 text-xs text-content-muted">Puoi anche trascinare {{ $multiple ? 'i file' : 'il file' }} in quest’area.</p>

    <div x-show="fileNames.length" x-cloak class="mt-4 rounded-lg bg-surface-muted px-3 py-2 text-left text-sm text-content" aria-live="polite">
        <p class="font-medium" x-text="fileNames.length === 1 ? fileNames[0] : fileNames.length + ' file selezionati'"></p>
        <template x-if="fileNames.length > 1">
            <ul class="mt-1 space-y-1 text-xs text-content-muted">
                <template x-for="fileName in fileNames" :key="fileName"><li class="truncate" x-text="fileName"></li></template>
            </ul>
        </template>
    </div>

    <div x-show="uploading" x-cloak class="mt-4 text-left" role="status" aria-live="polite">
        <div class="flex items-center justify-between gap-3 text-sm font-medium text-primary"><span>Caricamento file in corso...</span><span class="tabular-nums" x-text="progress + '%'"></span></div>
        <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-primary/15"><div class="h-full bg-primary transition-all" :style="`width: ${progress}%`"></div></div>
    </div>

    <div wire:loading wire:target="{{ $target }}" class="mt-4 flex items-center justify-center gap-2 text-sm font-medium text-primary" role="status">
        <x-icon name="o-arrow-path" class="size-4 animate-spin" /> Preparazione del file...
    </div>

    @if($errorMessage)<p class="mt-3 text-left text-sm text-danger" role="alert">{{ $errorMessage }}</p>@endif
</div>