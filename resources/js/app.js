import './bootstrap';
import './page-notices';
import Alpine from 'alpinejs';
import { createIcons } from 'lucide';
import { icons } from './icons';
import { onDocumentReady } from './dom-ready';
import { markPerformance, pageInitialized } from './page-work';
import { clearNotificationRequests } from './notification-data';
import './pages';

window.Alpine = Alpine;
markPerformance('entry');

const renderIcons = () => createIcons({ icons });
window.renderIcons = renderIcons;

document.addEventListener('alpine:initialized', renderIcons);

async function start() {
    markPerformance('modules-start');
    const modules = [];
    const uses = name => !!document.querySelector(`[x-data*="${name}"]`);
    if (uses('auditorOverview')) modules.push(import('./auditor-overview'));
    if (uses('qmsArchives')) modules.push(import('./qms-archives'));
    if (uses('superAdmin') || uses('dashboardActivityList')) modules.push(import('./super-admin-dashboard'));
    if (uses('systemSettings')) modules.push(import('./system-settings'));
    if (uses('atlWorkspace') || document.querySelector('#atl-review-dialog')) modules.push(import('./atl-workspace'));
    if (document.querySelector('#car-history-dialog')) modules.push(import('./car-history'));
    if (document.body.dataset.portalRole === '3') {
        modules.push(import('./auditee-results'), import('./auditee-result-modal'),
            import('./auditee-evidence-preview'), import('./auditee-evidence-requests'));
    }
    // Register page-specific Alpine components before the single Alpine.start().
    await Promise.all(modules);
    markPerformance('modules-ready');
    markPerformance('alpine-start');
    Alpine.start();
    pageInitialized();
}

window.addEventListener('pagehide', event => {
    clearNotificationRequests();
    window.dispatchEvent(new Event('complitrack:clear-sensitive'));
    // BFCache must not resurrect another account's authorized data after switching users.
    if (event.persisted && document.body.dataset.userId) document.body.replaceChildren();
});
window.addEventListener('pageshow', event => {
    if (event.persisted) window.location.reload();
});

onDocumentReady(() => {
    start().catch(error => {
        console.error('Page components could not be loaded.', error);
        window.notify?.('Page components could not be loaded. Please refresh.', 'error');
    });
});
