import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// Alpine is provided by Livewire, so we extend it instead of initializing our own
import collapse from '@alpinejs/collapse';
import focus from '@alpinejs/focus';

// Wait for Livewire to initialize Alpine, then add our plugins
document.addEventListener('livewire:init', () => {
    if (window.Alpine) {
        window.Alpine.plugin(collapse);
        window.Alpine.plugin(focus);
    }
});

// Fallback for pages without Livewire
document.addEventListener('DOMContentLoaded', () => {
    // Only initialize Alpine if Livewire hasn't already done it
    if (!window.Alpine) {
        import('alpinejs').then((Alpine) => {
            Alpine.default.plugin(collapse);
            Alpine.default.plugin(focus);
            window.Alpine = Alpine.default;
            Alpine.default.start();
        });
    }
});
