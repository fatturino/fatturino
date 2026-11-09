@props([
    'payment',
])

@php
    $canMarkPaid = $payment->status === \App\Enums\F24PaymentStatus::Planned;
    $hasAttachment = filled($payment->source_file_path);
    $label = $payment->primaryLabel();
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
        class="inline-flex size-9 items-center justify-center rounded-lg text-content-muted transition hover:bg-surface-muted hover:text-content focus:outline-none focus:ring-2 focus:ring-primary/20"
        aria-label="{{ __('app.f24.actions_for', ['label' => $label]) }}"
        aria-haspopup="true"
        :aria-expanded="open"
        @click="toggle()"
    >
        <x-icon name="o-ellipsis-vertical" class="size-5" />
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
            class="fixed z-50 w-56 origin-top-right rounded-xl border border-border bg-white shadow-[var(--shadow-elevated)]"
        >
            <div class="divide-y divide-border">
                <div class="space-y-1 p-2.5">
                    <x-app-link
                        :href="route('f24.edit', $payment)"
                        role="menuitem"
                        class="group flex items-center gap-2 rounded-lg border border-transparent px-2.5 py-2 text-sm font-medium text-content-muted hover:bg-primary-subtle hover:text-primary"
                        @click="open = false"
                    >
                        <x-icon name="o-pencil-square" class="size-5 shrink-0 opacity-40 group-hover:opacity-70" />
                        <span class="grow">{{ __('app.f24.edit') }}</span>
                    </x-app-link>

                    @if($canMarkPaid)
                        <button
                            type="button"
                            role="menuitem"
                            @click="open = false; $wire.markPaid({{ $payment->id }})"
                            class="group flex w-full items-center gap-2 rounded-lg border border-transparent px-2.5 py-2 text-left text-sm font-medium text-content-muted hover:bg-primary-subtle hover:text-primary"
                        >
                            <x-icon name="o-banknotes" class="size-5 shrink-0 opacity-40 group-hover:opacity-70" />
                            <span class="flex-1">{{ __('app.f24.mark_paid') }}</span>
                        </button>
                    @endif

                    @if($hasAttachment)
                        <x-app-link
                            :href="route('f24.attachment', ['path' => $payment->source_file_path])"
                            role="menuitem"
                            target="_blank"
                            rel="noopener"
                            class="group flex items-center gap-2 rounded-lg border border-transparent px-2.5 py-2 text-sm font-medium text-content-muted hover:bg-primary-subtle hover:text-primary"
                            @click="open = false"
                        >
                            <x-icon name="o-document-text" class="size-5 shrink-0 opacity-40 group-hover:opacity-70" />
                            <span class="grow">{{ __('app.f24.open_attachment') }}</span>
                        </x-app-link>
                    @endif
                </div>

                <div class="p-2.5">
                    <button
                        type="button"
                        role="menuitem"
                        @click="open = false; $dispatch('confirm-dialog', { message: @js(__('app.common.confirm_delete')), callback: () => $wire.delete({{ $payment->id }}) })"
                        class="group flex w-full items-center gap-2 rounded-lg border border-transparent px-2.5 py-2 text-left text-sm font-medium text-danger hover:bg-danger/5"
                    >
                        <x-icon name="o-trash" class="size-5 shrink-0 opacity-50 group-hover:opacity-80" />
                        <span class="flex-1">{{ __('app.common.delete') }}</span>
                    </button>
                </div>
            </div>
        </div>
    </template>
</div>
