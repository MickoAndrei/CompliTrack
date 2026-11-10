document.addEventListener('alpine:init', () => {
    Alpine.data('atlWorkspace', () => ({
        data: null, loading: true, error: '', page: 1,
        init() { this.load(); },
        label(value) { return String(value || '').replaceAll('_', ' ').replace(/^./, letter => letter.toUpperCase()); },
        async load() {
            if (this.requesting) return;
            this.requesting = true; this.loading = true; this.error = '';
            try {
                const response = await window.axios.get('/api/v1/admin/workspace', { params: { page: this.page } });
                this.data = response.data.data;
                this.$nextTick(() => window.renderIcons?.());
            } catch (error) { this.error = error.response?.data?.message || 'Unable to load your ATL workspace. Use Refresh to retry.'; }
            finally { this.loading = false; this.requesting = false; }
        },
    }));
});

document.addEventListener('DOMContentLoaded', () => {
    const dialog = document.querySelector('#atl-review-dialog');
    if (!dialog) return;
    const body = dialog.querySelector('[data-atl-review-body]');
    const feedback = dialog.querySelector('[data-atl-review-feedback]');
    const close = dialog.querySelector('[data-atl-review-close]');
    let opener, currentUrl, controller, saving = false, changed = false;
    const notice = (message, error = false) => {
        feedback.textContent = message; feedback.hidden = !message;
        feedback.classList.toggle('atl-error', error);
        feedback.setAttribute('role', error ? 'alert' : 'status');
    };
    const load = async () => {
        controller?.abort(); const request = new AbortController(); controller = request;
        body.setAttribute('aria-busy', 'true'); body.textContent = 'Loading audit results…';
        const url = new URL(currentUrl, window.location.origin); url.searchParams.set('review', '1');
        try {
            const response = await fetch(url, { signal: request.signal, credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'text/html' } });
            if (!response.ok || response.redirected) throw Error('This audit is unavailable or your session expired. Close this popup and reload the page.');
            const html = await response.text();
            if (!dialog.open || request.signal.aborted) return;
            body.innerHTML = html;
        } catch (error) {
            if (error.name !== 'AbortError') { body.textContent = ''; notice(error.message || 'Unable to load the audit. Please retry.', true); }
        } finally { if (controller === request) body.setAttribute('aria-busy', 'false'); }
    };
    document.addEventListener('click', event => {
        const link = event.target.closest('a[data-atl-review]');
        if (!link || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || event.button !== 0) return;
        const url = new URL(link.href);
        if (url.origin !== location.origin || !/^\/audits\/[a-f0-9]{24}$/i.test(url.pathname)) return;
        event.preventDefault(); opener = link; currentUrl = url.href; changed = false;
        notice(''); dialog.showModal(); load();
    });
    close.addEventListener('click', () => { if (!saving) dialog.close(); });
    dialog.addEventListener('cancel', event => { if (saving) event.preventDefault(); });
    dialog.addEventListener('close', () => {
        controller?.abort(); body.textContent = ''; notice('');
        const focusTarget = opener?.isConnected ? opener : [...document.querySelectorAll('a[data-atl-review]')].find(link => link.href === currentUrl);
        focusTarget?.focus();
        if (changed && location.pathname === '/audits') window.location.reload();
    });
    dialog.addEventListener('submit', async event => {
        const form = event.target.closest('[data-atl-review-form]');
        if (!form) return;
        event.preventDefault(); if (saving) return;
        const action = event.submitter?.value;
        if (!['accept', 'return'].includes(action)) return;
        const payload = new FormData(form); payload.set('action', action);
        saving = true; close.disabled = true; notice('Recording review decision…');
        form.querySelectorAll('button,textarea').forEach(control => { control.disabled = true; });
        try {
            // The existing workflow's buttons are named "action", which masks form.action.
            const response = await fetch(form.getAttribute('action'), { method: 'POST', credentials: 'same-origin', body: payload,
                headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' } });
            const data = await response.json();
            if (!response.ok) {
                if (response.status === 409) await load();
                throw Error(Object.values(data.errors || {}).flat().join(' ') || data.message || 'Review could not be saved.');
            }
            changed = true; await load(); notice(data.message || 'Audit review recorded.');
            window.dispatchEvent(new CustomEvent('atl-review-saved'));
        } catch (error) { notice(error.message || 'Review could not be saved. Please retry.', true); }
        finally {
            saving = false; close.disabled = false;
            form.querySelectorAll('button,textarea').forEach(control => { control.disabled = false; });
        }
    });
});
