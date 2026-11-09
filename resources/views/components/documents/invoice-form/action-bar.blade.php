@props(['cancelRoute', 'submitLabel', 'readOnly' => false, 'variant' => 'default', 'netDue' => null])

@php
    $dirtyCancel = "if (!dirty) return; \$event.preventDefault(); const href = \$el.getAttribute('href'); \$dispatch('confirm-dialog', { message: 'Le modifiche non salvate andranno perse. Vuoi annullare?', callback: () => Livewire.navigate(href) })";
@endphp

@unless($readOnly)
    @if($variant === 'sales-editor')
        <div class="hidden border-t border-border pt-4 lg:flex lg:flex-col lg:gap-2">
            <x-button
                label="Annulla"
                :link="route($cancelRoute)"
                variant="secondary"
                size="cta"
                @click="{!! $dirtyCancel !!}"
            />
            <x-button
                type="submit"
                :label="$submitLabel"
                size="cta"
                spinner="save"
            />
        </div>
        <div class="fixed inset-x-0 bottom-0 z-20 border-t border-border bg-white/95 p-3 backdrop-blur lg:hidden">
            <div class="mx-auto flex max-w-[90rem] items-center gap-3">
                <span class="min-w-0 flex-1 text-xs text-content-muted">
                    Da pagare
                    <span class="ml-1 font-semibold tabular-nums text-content">€ {{ number_format($netDue ?? 0, 2, ',', '.') }}</span>
                </span>
                <x-button
                    label="Annulla"
                    :link="route($cancelRoute)"
                    variant="secondary"
                    size="cta"
                    class="px-3"
                    @click="{!! $dirtyCancel !!}"
                />
                <x-button
                    type="submit"
                    :label="$submitLabel"
                    size="cta"
                    class="px-3"
                    spinner="save"
                />
            </div>
        </div>
    @else
        <div class="fixed inset-x-0 bottom-0 z-20 border-t border-border bg-white/95 p-3 backdrop-blur">
            <div @class(['mx-auto flex justify-end gap-3', 'max-w-[90rem] px-1' => $variant === 'editor', 'max-w-7xl' => $variant !== 'editor'])>
                <x-button
                    label="Annulla"
                    :link="route($cancelRoute)"
                    variant="secondary"
                    size="cta"
                />
                <x-button
                    type="submit"
                    :label="$submitLabel"
                    size="cta"
                    spinner="save"
                />
            </div>
        </div>
    @endif
@endunless
