import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// Alpine.js — used for the interactive bits of the Blade dashboards
// (dropdowns, the Kanban board's drag state, modals) per Guide §2.
import Alpine from 'alpinejs';
window.Alpine = Alpine;
Alpine.start();
