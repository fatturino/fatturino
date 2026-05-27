<script lang="ts">
    import { Dialog as DialogPrimitive, Button, type WithoutChildrenOrChild } from "bits-ui";
    import X from "phosphor-svelte/lib/X";

    let {
        open = $bindable(false),
        title = "",
        description = "",
        cancelText = "Annulla",
        confirmText = "Conferma",
        variant = "primary" as "primary" | "danger",
        isLoading = false,
        onConfirm = undefined as (() => void) | undefined,
        contentClass = "",
        children,
        ...restProps
    }: WithoutChildrenOrChild<DialogPrimitive.RootProps> & {
        title?: string;
        description?: string;
        cancelText?: string;
        confirmText?: string;
        variant?: "primary" | "danger";
        isLoading?: boolean;
        onConfirm?: () => void;
        contentClass?: string;
    } = $props();

    function handleConfirm() {
        onConfirm?.();
        open = false;
    }
</script>

<DialogPrimitive.Root bind:open {...restProps}>
    <DialogPrimitive.Portal>
        <DialogPrimitive.Overlay class="modal-backdrop fixed inset-0 z-40" />
        <DialogPrimitive.Content class={`modal-panel fixed left-[50%] top-[50%] z-50 w-full max-w-md translate-x-[-50%] translate-y-[-50%] p-6 ${contentClass}`}>
            <DialogPrimitive.Title class="text-lg font-semibold text-brand-deep mb-2">
                {title}
            </DialogPrimitive.Title>

            {#if description}
                <DialogPrimitive.Description class="text-sm text-brand-deep/60 mb-6 whitespace-pre-line">
                    {description}
                </DialogPrimitive.Description>
            {/if}

            {@render children?.()}

            <div class="flex justify-end gap-3 mt-6">
                <DialogPrimitive.Close asChild>
                    <Button.Root class="btn-ghost px-4 py-2 text-sm">
                        {cancelText}
                    </Button.Root>
                </DialogPrimitive.Close>

                {#if onConfirm}
                    <Button.Root
                        class={variant === "danger" ? "btn-danger px-4 py-2 text-sm min-h-11" : "btn-brand px-4 py-2 text-sm min-h-11"}
                        onclick={handleConfirm}
                        disabled={isLoading}
                        aria-busy={isLoading}
                    >
                        {isLoading ? 'Caricamento...' : confirmText}
                    </Button.Root>
                {/if}
            </div>

            <DialogPrimitive.Close class="absolute right-5 top-5 rounded-full size-8 inline-flex items-center justify-center hover:bg-surface-muted transition-colors" aria-label="Chiudi">
                <X class="size-4 text-brand-deep/50" />
            </DialogPrimitive.Close>
        </DialogPrimitive.Content>
    </DialogPrimitive.Portal>
</DialogPrimitive.Root>
