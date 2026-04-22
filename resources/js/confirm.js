/**
 * Override browser's native confirm() dialog with a custom DaisyUI modal.
 * Works transparently with all wire:confirm attributes — no blade changes needed.
 */

let pendingElement = null;
let isConfirmed = false;

// Capture the clicked element before Livewire processes it
document.addEventListener('click', (e) => {
    const el = e.target.closest('[wire\\:confirm]');
    if (el) {
        pendingElement = el;
    }
}, true);

// Override native confirm() to show custom modal instead
window.confirm = function (message) {
    // If user already confirmed via our modal, allow the action through
    if (isConfirmed) {
        isConfirmed = false;
        return true;
    }

    // Show the custom modal and block the current action
    window.dispatchEvent(new CustomEvent('confirm-dialog', {
        detail: {
            message: message,
            callback: () => {
                isConfirmed = true;
                pendingElement?.click();
                pendingElement = null;
            }
        }
    }));

    return false;
};
