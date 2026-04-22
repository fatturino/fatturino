import './bootstrap';
import './confirm';

// Register Alpine.js plugins (loaded before Alpine starts via Livewire)
import collapse from '@alpinejs/collapse';
import anchor from '@alpinejs/anchor';
document.addEventListener('alpine:init', () => {
    window.Alpine.plugin(collapse);
    window.Alpine.plugin(anchor);
});
