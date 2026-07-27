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
    $event = "document-action";
@endphp

<div class="inline-flex items-center justify-end gap-1">
    <x-app-link href="/{{ $base }}/{{ $document->id }}/edit" class="rounded-md px-2 py-1.5 text-sm font-semibold text-primary hover:bg-primary/5">Apri</x-app-link>
    <x-dropdown right>
        <x-slot:trigger>
            <button type="button" class="inline-flex size-8 items-center justify-center rounded-full text-base-content/70 hover:bg-base-300/50 hover:text-base-content" aria-label="Azioni per {{ $document->number ?? 'documento #'.$document->id }}"><x-icon name="o-ellipsis-vertical" class="size-5" /></button>
        </x-slot:trigger>

        @if($canManagePayments)
            <button type="button" @click="$dispatch('{{ $event }}', { action: 'payment', id: {{ $document->id }} })" :disabled="busy" class="flex w-full items-center gap-3 rounded-lg px-4 py-2.5 text-left text-sm text-base-content/70 hover:bg-base-content/5 hover:text-base-content disabled:cursor-not-allowed disabled:opacity-50">
                <x-icon name="o-banknotes" class="size-5 shrink-0" />
                <span class="flex-1">{{ $type === 'sales' ? 'Segna incasso' : 'Segna pagamento' }}</span>
            </button>
        @endif

        @if($canSendEmail)
            <button type="button" @click="$dispatch('{{ $event }}', { action: 'email', id: {{ $document->id }} })" :disabled="busy" class="flex w-full items-center gap-3 rounded-lg px-4 py-2.5 text-left text-sm text-base-content/70 hover:bg-base-content/5 hover:text-base-content disabled:cursor-not-allowed disabled:opacity-50">
                <x-icon name="o-envelope" class="size-5 shrink-0" />
                <span class="flex-1">Invia email</span>
            </button>
        @endif

        @if($canValidateXml || $canSendToSdi)
            <div class="my-1 border-t border-base-200"></div>
        @endif

        @if($canValidateXml)
            <button type="button" @click="$dispatch('{{ $event }}', { action: 'validate-xml', id: {{ $document->id }} })" :disabled="busy" class="flex w-full items-center gap-3 rounded-lg px-4 py-2.5 text-left text-sm text-base-content/70 hover:bg-base-content/5 hover:text-base-content disabled:cursor-not-allowed disabled:opacity-50">
                <x-icon name="o-shield-check" class="size-5 shrink-0" />
                <span class="flex-1">Verifica XML</span>
            </button>
        @endif

        @if($canSendToSdi)
            <button type="button" @click="$dispatch('{{ $event }}', { action: 'send-sdi', id: {{ $document->id }} })" :disabled="busy" class="flex w-full items-center gap-3 rounded-lg px-4 py-2.5 text-left text-sm text-error hover:bg-error/5 disabled:cursor-not-allowed disabled:opacity-50">
                <x-icon name="o-paper-airplane" class="size-5 shrink-0" />
                <span class="flex-1">Invia a SDI</span>
            </button>
        @endif
    </x-dropdown>
</div>
