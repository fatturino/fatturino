import './bootstrap';
import './confirm';
import 'phosphor-icons/src/css/icons.css';

// Register Alpine.js plugins (loaded before Alpine starts via Livewire)
import collapse from '@alpinejs/collapse';
import anchor from '@alpinejs/anchor';
document.addEventListener('alpine:init', () => {
    window.Alpine.plugin(collapse);
    window.Alpine.plugin(anchor);

    // Toast notification store (replaces Mary\Traits\Toast)
    window.Alpine.store('toast', {
        show: false,
        type: 'info',
        message: '',
        description: '',
        position: 'toast-bottom toast-end',
        timer: null,

        showToast(type, message, description = '', position = 'toast-bottom toast-end') {
            clearTimeout(this.timer);
            this.type = type;
            this.message = message;
            this.description = description;
            this.position = position;
            this.show = true;
            this.timer = setTimeout(() => { this.show = false; }, 4000);
        },

        success(message, description = '', position = 'toast-bottom toast-end') {
            this.showToast('success', message, description, position);
        },
        error(message, description = '', position = 'toast-bottom toast-end') {
            this.showToast('error', message, description, position);
        },
        warning(message, description = '', position = 'toast-bottom toast-end') {
            this.showToast('warning', message, description, position);
        },
        info(message, description = '', position = 'toast-bottom toast-end') {
            this.showToast('info', message, description, position);
        },
    });
});

// Listen for toast events dispatched by Livewire (replaces Mary\Traits\Toast)
window.addEventListener('toast', (e) => {
    const store = window.Alpine.store('toast');
    const { type, message, description, position } = e.detail;
    store[type](message, description, position);
});
