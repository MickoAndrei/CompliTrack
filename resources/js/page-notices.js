// One transient notification presentation for all portal pages.
const active = new Map();
let sequence = 0;

export function notify(message, type = 'info', actions = []) {
    message = String(message || '').trim();
    if (!message) return;
    const key = `${type}:${message}`;
    if (active.has(key)) return;
    const popup = document.createElement('div');
    popup.className = `page-notification is-${['error', 'warning', 'success'].includes(type) ? type : 'info'}`;
    popup.setAttribute('popover', 'manual');
    popup.setAttribute('role', type === 'error' ? 'alert' : 'status');
    popup.setAttribute('aria-live', type === 'error' ? 'assertive' : 'polite');
    popup.dataset.notificationId = String(++sequence);
    const text = document.createElement('span'); text.textContent = message;
    const close = document.createElement('button');
    close.type = 'button'; close.className = 'page-notification-close';
    close.setAttribute('aria-label', 'Dismiss notification'); close.textContent = '×';
    popup.append(text, close);
    if (actions.length) {
        const controls = document.createElement('div'); controls.className = 'page-notification-actions';
        for (const action of actions) {
            const button = document.createElement('button'); button.type = 'button';
            button.textContent = action.textContent.trim();
            button.addEventListener('click', () => { action.click(); dismiss(); }); controls.append(button);
        }
        popup.append(controls);
    }
    const reposition = () => {
        let offset = 24;
        for (const item of active.values()) { item.popup.style.top = `${offset}px`; offset += item.popup.offsetHeight + 10; }
    };
    let timer;
    const dismiss = () => { clearTimeout(timer); active.delete(key); popup.remove(); reposition(); };
    close.addEventListener('click', dismiss);
    const dialogs = document.querySelectorAll('dialog[open]');
    (dialogs[dialogs.length - 1] || document.body).append(popup); active.set(key, { popup, dismiss });
    popup.showPopover?.(); reposition();
    timer = setTimeout(dismiss, 3000);
    return dismiss;
}

window.notify = notify;
document.addEventListener('alpine:init', () => {
    const Alpine = window.Alpine;
    Alpine.addInitSelector(() => '[x-page-notice]');
    Alpine.directive('page-notice', (element, { expression }, { evaluate, effect, cleanup }) => {
        // A parent notice already includes this content and its retry controls.
        if (element.parentElement?.closest('[x-page-notice]')) return;
        element.classList.add('page-notice-source');
        let previous = '', pending = false;
        const visible = () => {
            for (let node = element; node && node !== document.body; node = node.parentElement) {
                const condition = node.getAttribute('x-show');
                if (condition && !Alpine.evaluate(node, condition)) return false;
                if (node.hidden) return false;
                if (node.tagName === 'DIALOG' && !node.open) return false;
            }
            return element.isConnected;
        };
        const publish = () => {
            pending = false;
            if (!visible()) { previous = ''; return; }
            const clone = element.cloneNode(true);
            clone.querySelectorAll('button, a, [aria-hidden="true"], template').forEach(node => node.remove());
            const message = clone.textContent.replace(/\s+/g, ' ').trim();
            const type = expression ? evaluate(expression) : element.getAttribute('role') === 'alert' || /(?:error|alert)/.test(element.className) ? 'error'
                : /warning/.test(element.className) ? 'warning' : /success|notice/.test(element.className) ? 'success' : 'info';
            const signature = `${type}:${message}`;
            if (message && signature !== previous) {
                previous = signature;
                notify(message, type, [...element.querySelectorAll('button, a')].filter(node => !node.getAttribute('aria-label')?.includes('Dismiss')));
            }
        };
        const schedule = () => { if (!pending) { pending = true; queueMicrotask(publish); } };
        effect(() => { visible(); if (expression) evaluate(expression); schedule(); });
        const observer = new MutationObserver(schedule);
        observer.observe(element, { childList: true, subtree: true, characterData: true, attributes: true, attributeFilter: ['hidden', 'class', 'role'] });
        for (let parent = element.parentElement; parent && parent !== document.body; parent = parent.parentElement) {
            observer.observe(parent, { attributes: true, attributeFilter: ['hidden', 'open'] });
        }
        schedule(); cleanup(() => observer.disconnect());
    });
});
