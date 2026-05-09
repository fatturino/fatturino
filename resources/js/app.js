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
            // Error and warning toasts stay longer for readability
            const duration = (type === 'error' || type === 'warning') ? 8000 : 4000;
            this.timer = setTimeout(() => { this.show = false; }, duration);
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


// ═══ Livewire resilience: offline detection & error recovery ═══

document.addEventListener('livewire:init', () => {
    let offlineToastShown = false;

    window.addEventListener('offline', () => {
        if (!offlineToastShown) {
            Alpine.store('toast').warning(
                'Connessione persa',
                'Verifica la tua connessione internet.',
            );
            offlineToastShown = true;
        }
    });

    window.addEventListener('online', () => {
        if (offlineToastShown) {
            Alpine.store('toast').success('Connessione ripristinata');
            offlineToastShown = false;
        }
    });

    Livewire.hook('request', ({ fail }) => {
        fail(({ status }) => {
            const store = Alpine.store('toast');
            if (status === 422) return;

            if (status === 419) {
                store.error('Sessione scaduta', 'La pagina verra ricaricata...');
                setTimeout(() => window.location.reload(), 2000);
                return;
            }

            if (status === 500 || status === 503) {
                store.error('Errore del server', 'Riprova tra qualche istante.');
            } else if (status === 403) {
                store.error('Accesso negato', 'Non hai i permessi necessari.');
            } else {
                store.error('Operazione non riuscita', 'Verifica la connessione e riprova.');
            }
        });
    });
});
