@props(['isCurrentYear'])

<x-card class="h-full">
    <x-card-header icon="o-bolt" :title="__('app.dashboard.quick_actions')" class="mb-4" />

    <div class="grid grid-cols-2 gap-2">
        @if($isCurrentYear)
            <a href="{{ route('sell-invoices.create') }}" wire:navigate
               class="flex flex-col items-center gap-1.5 p-3 rounded-xl bg-primary/5 hover:bg-primary/10 border border-primary/20 transition-colors text-center">
                <span class="w-9 h-9 rounded-lg bg-primary flex items-center justify-center">
                    <x-icon name="o-plus" class="w-5 h-5 text-white" />
                </span>
                <span class="text-xs font-medium text-primary">{{ __('app.dashboard.quick_invoice') }}</span>
            </a>
        @endif

        <a href="/self-invoices/create" wire:navigate
           class="flex flex-col items-center gap-1.5 p-3 rounded-xl bg-accent/5 hover:bg-accent/10 border border-accent/20 transition-colors text-center">
            <span class="w-9 h-9 rounded-lg bg-accent flex items-center justify-center">
                <x-icon name="o-document-duplicate" class="w-5 h-5 text-white" />
            </span>
            <span class="text-xs font-medium text-accent">{{ __('app.dashboard.quick_self_invoice') }}</span>
        </a>

        <a href="/contacts/create" wire:navigate
           class="flex flex-col items-center gap-1.5 p-3 rounded-xl bg-success/5 hover:bg-success/10 border border-success/20 transition-colors text-center">
            <span class="w-9 h-9 rounded-lg bg-success flex items-center justify-center">
                <x-icon name="o-users" class="w-5 h-5 text-white" />
            </span>
            <span class="text-xs font-medium text-success">{{ __('app.dashboard.quick_contact') }}</span>
        </a>

        <a href="/imports" wire:navigate
           class="flex flex-col items-center gap-1.5 p-3 rounded-xl bg-info/5 hover:bg-info/10 border border-info/20 transition-colors text-center">
            <span class="w-9 h-9 rounded-lg bg-info flex items-center justify-center">
                <x-icon name="o-arrow-down-tray" class="w-5 h-5 text-white" />
            </span>
            <span class="text-xs font-medium text-info">{{ __('app.dashboard.quick_import') }}</span>
        </a>
    </div>
</x-card>
