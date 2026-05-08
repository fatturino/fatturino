@props(['isCurrentYear'])

<x-card class="h-full">
    <div class="flex items-center gap-2 mb-4">
        <div class="bg-primary/10 rounded-xl p-2.5">
            <x-icon name="o-bolt" class="w-5 h-5 text-primary" />
        </div>
        <span class="font-semibold">{{ __('app.dashboard.quick_actions') }}</span>
    </div>

    <div class="space-y-2">
        @if($isCurrentYear)
            <a href="{{ route('sell-invoices.create') }}" wire:navigate
               class="flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-base-200/50 transition-colors text-sm">
                <span class="w-8 h-8 rounded-lg bg-primary/10 flex items-center justify-center shrink-0">
                    <x-icon name="o-plus" class="w-4 h-4 text-primary" />
                </span>
                <span class="font-medium">{{ __('app.invoices.create_title') }}</span>
            </a>
        @endif

        <a href="/contacts/create" wire:navigate
           class="flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-base-200/50 transition-colors text-sm">
            <span class="w-8 h-8 rounded-lg bg-primary/10 flex items-center justify-center shrink-0">
                <x-icon name="o-user" class="w-4 h-4 text-primary" />
            </span>
            <span class="font-medium">{{ __('app.contacts.create_title') }}</span>
        </a>

        <a href="/imports" wire:navigate
           class="flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-base-200/50 transition-colors text-sm">
            <span class="w-8 h-8 rounded-lg bg-primary/10 flex items-center justify-center shrink-0">
                <x-icon name="o-arrow-down-tray" class="w-4 h-4 text-primary" />
            </span>
            <span class="font-medium">{{ __('app.imports.title') }}</span>
        </a>
    </div>
</x-card>
