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
    if (uses('auditeeHelp')) modules.push(import('./auditee-help'));
    if (uses('auditPlans') || uses('auditPlanPermissions')) modules.push(import('./audit-plans'));
    if (uses('auditWorkspace')) modules.push(import('./audit-workspace'));
    if (document.querySelector('[data-my-audits]')) modules.push(import('./my-assigned-audits'));
    if (uses('auditorOverview')) modules.push(import('./auditor-overview'));
    if (uses('qmsArchives')) modules.push(import('./qms-archives'));
    if (uses('correctiveActions')) modules.push(import('./corrective-actions'));
    if (uses('superAdmin') || uses('dashboardActivityList')) modules.push(import('./super-admin-dashboard'));
    if (uses('systemSettings')) modules.push(import('./system-settings'));
    if (document.querySelector('#audit-assignment-dialog')) modules.push(import('./audit-assignments'));
    if (uses('atlWorkspace') || document.querySelector('#atl-review-dialog')) modules.push(import('./atl-workspace'));
    if (document.querySelector('#car-history-dialog')) modules.push(import('./car-history'));
    if (document.querySelector('#evidence-preview-modal')) modules.push(import('./auditee-evidence-preview'));
    if (document.body.dataset.portalRole === '3') {
        modules.push(import('./auditee-results'), import('./auditee-result-modal'),
            import('./auditee-evidence-requests'));
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
