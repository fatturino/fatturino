{{-- Global confirmation modal replacing browser's native confirm() dialog --}}
<div
    x-data="{
        show: false,
        message: '',
        onConfirm: null,
        open(message, callback) {
            this.message = message;
            this.onConfirm = callback;
            this.show = true;
        },
        confirm() {
            this.show = false;
            if (this.onConfirm) {
                this.onConfirm();
            }
        },
        cancel() {
            this.show = false;
            this.onConfirm = null;
        }
    }"
    x-on:confirm-dialog.window="open($event.detail.message, $event.detail.callback)"
    x-on:keydown.escape.window="cancel()"
>
    {{-- Backdrop --}}
    <div
        x-show="show"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-50 bg-black/50"
        x-on:click="cancel()"
        style="display: none;"
    ></div>

    {{-- Modal --}}
    <div
        x-show="show"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
        style="display: none;"
    >
        <div class="bg-base-100 rounded-box shadow-xl w-full max-w-sm p-6" x-on:click.stop>
            {{-- Icon --}}
            <div class="flex justify-center mb-4">
                <div class="bg-warning/10 rounded-full p-3">
                    <x-icon name="o-exclamation-triangle" class="w-8 h-8 text-warning" />
                </div>
            </div>

            {{-- Title --}}
            <h3 class="text-lg font-semibold text-center mb-2">{{ __('app.common.confirm_title') }}</h3>

            {{-- Message --}}
            <p class="text-center text-base-content/70 mb-6" x-text="message"></p>

            {{-- Actions --}}
            <div class="flex gap-3 justify-end">
                <x-button
                    :label="__('app.common.cancel')"
                    x-on:click="cancel()"
                    variant="ghost"
                />
                <x-button
                    :label="__('app.common.confirm')"
                    x-on:click="confirm()"
                    variant="primary"
                />
            </div>
        </div>
    </div>
</div>
