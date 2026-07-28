@props([
    'document',
    'type',
    'base',
])

@php
    $canManagePayments = in_array($type, ['sales', 'purchase', 'self'], true);
    // The modal lets the user supply a recipient even when the contact has no saved email.
    $canSendEmail = in_array($type, ['sales', 'credit', 'proforma'], true);
    $canValidateXml = in_array($type, ['sales', 'self', 'credit'], true)
        && $document->isSdiEditable()
        && $document->status->canValidateXml();
    $canSendToSdi = in_array($type, ['sales', 'self', 'credit'], true)
        && $document->isSdiEditable()
        && $document->status->canSendToSdi();
    $canDownloadXml = in_array($type, ['sales', 'self', 'credit'], true);
    $canConvert = $type === 'proforma' && $document->isConvertible();
    $canDelete = $type === 'proforma' && $document->statusValue() !== 'converted';
    $event = "document-action";
@endphp

<div
    class="inline-block text-left"
    x-data="{
        open: false,
        position: { top: 0, left: 0 },
        toggle() {
            this.open = !this.open;

            if (this.open) {
                this.$nextTick(() => this.positionMenu());
            }
        },
        positionMenu() {
            const trigger = this.$refs.trigger;
            const menu = this.$refs.menu;
            const gap = 8;
            const viewportPadding = 8;
            const triggerRect = trigger.getBoundingClientRect();
            const menuRect = menu.getBoundingClientRect();
            const opensAbove = triggerRect.bottom + gap + menuRect.height > window.innerHeight
                && triggerRect.top - gap - menuRect.height >= viewportPadding;

            this.position = {
                top: opensAbove ? triggerRect.top - gap - menuRect.height : triggerRect.bottom + gap,
                left: Math.min(
                    Math.max(viewportPadding, triggerRect.right - menuRect.width),
                    window.innerWidth - menuRect.width - viewportPadding,
                ),
            };
        },
    }"
    @keydown.escape.window="open = false"
    @resize.window="open && positionMenu()"
    @scroll.window.capture="open && positionMenu()"
>
    <button
        type="button"
        x-ref="trigger"
        class="inline-flex items-center justify-center gap-2 rounded-lg border border-primary bg-primary px-3 py-2 text-sm font-semibold leading-5 text-white shadow-sm hover:border-primary/90 hover:bg-primary/90 focus:outline-none focus:ring-3 focus:ring-primary/30"
        aria-haspopup="true"
        :aria-expanded="open"
        @click="toggle()"
    >
        <span>Azioni</span>
        <svg class="size-4 opacity-70" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.168l3.71-3.938a.75.75 0 1 1 1.08 1.04l-4.25 4.5a.75.75 0 0 1-1.08 0l-4.25-4.5a.75.75 0 0 1 .02-1.06Z" clip-rule="evenodd" /></svg>
    </button>

    <template x-teleport="body">
        <div
            x-cloak
            x-ref="menu"
            x-show="open"
            x-transition:enter="transition ease-out duration-100"
            x-transition:enter-start="scale-90 opacity-0"
            x-transition:enter-end="scale-100 opacity-100"
            x-transition:leave="transition ease-in duration-75"
            x-transition:leave-start="scale-100 opacity-100"
            x-transition:leave-end="scale-90 opacity-0"
            @click.outside="open = false"
            :style="`top: ${position.top}px; left: ${position.left}px;`"
            role="menu"
            class="fixed z-50 w-56 origin-top-right rounded-lg bg-white shadow-xl ring-1 ring-black/5"
        >
        <div class="divide-y divide-base-200 rounded-lg">
            <div class="space-y-1 p-2.5">
                <x-app-link href="/{{ $base }}/{{ $document->id }}/edit" role="menuitem" class="group flex items-center gap-2 rounded-lg border border-transparent px-2.5 py-2 text-sm font-medium text-base-content/70 hover:bg-primary/5 hover:text-primary">
                    <x-icon name="o-pencil-square" class="size-5 shrink-0 opacity-40 group-hover:opacity-70" />
                    <span class="grow">Apri documento</span>
                </x-app-link>
        @if($canManagePayments)
            <button type="button" role="menuitem" @click="open = false; $dispatch('{{ $event }}', { action: 'payment', id: {{ $document->id }} })" :disabled="busy" class="group flex w-full items-center gap-2 rounded-lg border border-transparent px-2.5 py-2 text-left text-sm font-medium text-base-content/70 hover:bg-primary/5 hover:text-primary disabled:cursor-not-allowed disabled:opacity-50">
                <x-icon name="o-banknotes" class="size-5 shrink-0 opacity-40 group-hover:opacity-70" />
                <span class="flex-1">{{ $type === 'sales' ? 'Segna incasso' : 'Segna pagamento' }}</span>
            </button>
        @endif

        @if($canSendEmail)
            <button type="button" role="menuitem" @click="open = false; $dispatch('{{ $event }}', { action: 'email', id: {{ $document->id }} })" :disabled="busy" class="group flex w-full items-center gap-2 rounded-lg border border-transparent px-2.5 py-2 text-left text-sm font-medium text-base-content/70 hover:bg-primary/5 hover:text-primary disabled:cursor-not-allowed disabled:opacity-50">
                <x-icon name="o-envelope" class="size-5 shrink-0 opacity-40 group-hover:opacity-70" />
                <span class="flex-1">Invia email</span>
            </button>
        @endif

        @if($canConvert)
            <button type="button" role="menuitem" @click="open = false; $dispatch('{{ $event }}', { action: 'convert', id: {{ $document->id }} })" :disabled="busy" class="group flex w-full items-center gap-2 rounded-lg border border-transparent px-2.5 py-2 text-left text-sm font-medium text-base-content/70 hover:bg-primary/5 hover:text-primary disabled:cursor-not-allowed disabled:opacity-50">
                <x-icon name="o-arrow-path" class="size-5 shrink-0 opacity-40 group-hover:opacity-70" />
                <span class="flex-1">Converti in fattura</span>
            </button>
        @endif

        @if($canDownloadXml)
            <x-app-link href="/{{ $base }}/{{ $document->id }}/xml" download role="menuitem" class="group flex items-center gap-2 rounded-lg border border-transparent px-2.5 py-2 text-sm font-medium text-base-content/70 hover:bg-primary/5 hover:text-primary">
                <x-icon name="o-arrow-down-tray" class="size-5 shrink-0 opacity-40 group-hover:opacity-70" />
                <span class="grow">Scarica XML</span>
            </x-app-link>
        @endif
            </div>

        @if($canValidateXml || $canSendToSdi)
            <div class="space-y-1 p-2.5">
        @endif

        @if($canValidateXml)
            <button type="button" role="menuitem" @click="open = false; $dispatch('{{ $event }}', { action: 'validate-xml', id: {{ $document->id }} })" :disabled="busy" class="group flex w-full items-center gap-2 rounded-lg border border-transparent px-2.5 py-2 text-left text-sm font-medium text-base-content/70 hover:bg-primary/5 hover:text-primary disabled:cursor-not-allowed disabled:opacity-50">
                <x-icon name="o-shield-check" class="size-5 shrink-0 opacity-40 group-hover:opacity-70" />
                <span class="flex-1">Verifica XML</span>
            </button>
        @endif

        @if($canSendToSdi)
            <button type="button" role="menuitem" @click="open = false; $dispatch('{{ $event }}', { action: 'send-sdi', id: {{ $document->id }} })" :disabled="busy" class="group flex w-full items-center gap-2 rounded-lg border border-transparent px-2.5 py-2 text-left text-sm font-medium text-error hover:bg-error/5 disabled:cursor-not-allowed disabled:opacity-50">
                <x-icon name="o-paper-airplane" class="size-5 shrink-0 opacity-50 group-hover:opacity-80" />
                <span class="flex-1">Invia a SDI</span>
            </button>
        @endif
        @if($canValidateXml || $canSendToSdi)
            </div>
        @endif
        @if($canDelete)
            <div class="p-2.5">
                <button type="button" role="menuitem" @click="open = false; $dispatch('{{ $event }}', { action: 'delete', id: {{ $document->id }} })" :disabled="busy" class="group flex w-full items-center gap-2 rounded-lg border border-transparent px-2.5 py-2 text-left text-sm font-medium text-error hover:bg-error/5 disabled:cursor-not-allowed disabled:opacity-50">
                    <x-icon name="o-trash" class="size-5 shrink-0 opacity-50 group-hover:opacity-80" />
                    <span class="flex-1">Elimina proforma</span>
                </button>
            </div>
        @endif
        </div>
        </div>
    </template>
</div>
