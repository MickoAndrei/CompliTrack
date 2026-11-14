import { onDocumentReady } from './dom-ready';

document.addEventListener('alpine:init', () => {
    Alpine.data('atlWorkspace', () => ({
        data: null, loading: true, error: '', page: 1, perPage: 5, attentionPage: 1,
        get meta() { return { lastPage: this.data?.assignmentPages || 1 }; },
        get attentionPages() { return Math.max(1, Math.ceil((this.data?.attention.length || 0) / 5)); },
        get visibleAttention() { return (this.data?.attention || []).slice((this.attentionPage - 1) * 5, this.attentionPage * 5); },
        init() { this.load(); },
        label(value) { return String(value || '').replaceAll('_', ' ').replace(/^./, letter => letter.toUpperCase()); },
        async load(page = this.page) {
            if (this.requesting) return;
            this.requesting = true; this.loading = true; this.error = '';
            try {
                const response = await window.axios.get('/api/v1/admin/workspace', { params: { page, perPage: this.perPage } });
                this.data = response.data.data;
                this.page = this.data.assignmentPage || page;
                this.attentionPage = Math.min(this.attentionPage, this.attentionPages);
                this.$nextTick(() => window.renderIcons?.());
            } catch (error) { this.error = error.response?.data?.message || 'Unable to load your ATL workspace. Use Refresh to retry.'; }
            finally { this.loading = false; this.requesting = false; }
        },
    }));
});

onDocumentReady(() => {
    const form = document.querySelector('[data-review-filters]');
    if (!form) return;
    const results = document.querySelector('[data-review-results]');
    const error = document.querySelector('[data-review-filter-error]');
    const skeleton = document.querySelector('[data-review-skeleton]');
    let controller, timer, previousResults = results.innerHTML;
    const syncForm = url => {
        for (const control of form.querySelectorAll('input:not([type=hidden]),select')) {
            control.value = url.searchParams.get(control.name) || (control.name === 'status' ? 'submitted' : '');
        }
    };
    const load = async (url, push = true, focus = false) => {
        clearTimeout(timer); controller?.abort();
        const request = new AbortController(); controller = request;
        results.setAttribute('aria-busy', 'true'); error.hidden = true;
        results.replaceChildren(skeleton.content.cloneNode(true));
        try {
            const response = await fetch(url, { signal: request.signal, credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'text/html' } });
            if (!response.ok || response.redirected) throw Error('Unable to load audit reviews. Change a filter or use Reset Filter to retry.');
            const document = new DOMParser().parseFromString(await response.text(), 'text/html');
            const fresh = document.querySelector('[data-review-results]');
            if (!fresh) throw Error('Your session may have expired. Reload this page to continue.');
            if (request.signal.aborted) return;
            results.innerHTML = fresh.innerHTML; previousResults = fresh.innerHTML;
            if (push) history.pushState({}, '', url);
            if (focus) results.focus();
        } catch (exception) {
            if (exception.name !== 'AbortError') {
                results.innerHTML = previousResults; error.textContent = exception.message; error.hidden = false;
            }
        } finally { if (controller === request) results.setAttribute('aria-busy', 'false'); }
    };
    const filter = () => {
        const url = new URL(location.href);
        for (const [name, value] of new FormData(form)) {
            if (value) url.searchParams.set(name, value); else url.searchParams.delete(name);
        }
        url.searchParams.delete('page'); load(url);
    };
    form.addEventListener('submit', event => { event.preventDefault(); filter(); });
    form.addEventListener('change', event => { if (event.target.matches('select')) filter(); });
    form.addEventListener('input', event => { if (event.target.name === 'search') { clearTimeout(timer); timer = setTimeout(filter, 300); } });
    document.querySelector('.review-queue').addEventListener('click', event => {
        if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || event.button !== 0) return;
        const link = event.target.closest('[data-review-reset],.review-pagination a,.review-summary a');
        if (!link) return;
        event.preventDefault(); const url = new URL(link.href);
        syncForm(url); load(url, true, link.closest('.review-pagination') !== null);
    });
    window.addEventListener('popstate', () => { const url = new URL(location.href); syncForm(url); load(url, false); });
});

onDocumentReady(() => {
    const dialog = document.querySelector('#atl-review-dialog');
    if (!dialog) return;
    const body = dialog.querySelector('[data-atl-review-body]');
    const feedback = dialog.querySelector('[data-atl-review-feedback]');
    const close = dialog.querySelector('[data-atl-review-close]');
    const confirmation = document.querySelector('#atl-review-confirm');
    const preview = document.querySelector('#atl-review-preview');
    preview.querySelector('button').addEventListener('click', () => preview.close());
    preview.addEventListener('close', async () => {
        const { closeDocumentEditor } = await import('./document-editor');
        closeDocumentEditor(preview.querySelector('[data-preview-body]'));
    });
    dialog.addEventListener('click', async event => {
        const button = event.target.closest('[data-review-preview]');
        if (!button) return;
        button.disabled = true;
        try {
            const { data } = await window.axios.post(`/api/v1/documents/${button.dataset.reviewPreview}/editor`, { mode: 'view' });
            const { openDocumentEditor } = await import('./document-editor');
            preview.showModal();
            await openDocumentEditor(preview.querySelector('[data-preview-body]'), data, message => { preview.querySelector('[role=status]').textContent = message; });
        } catch (error) { notice(error.response?.data?.message || error.message || 'Preview unavailable.', true); }
        finally { button.disabled = false; }
    });
    let opener, currentUrl, controller, saving = false, confirming = false, changed = false;
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
        event.preventDefault(); if (saving || confirming) return;
        const action = event.submitter?.value;
        if (!['accept', 'return'].includes(action)) return;
        const notes = form.querySelector('[name=reviewNotes]');
        notes.setCustomValidity(action === 'return' && (notes.value.trim().length < 10 || !/\p{L}.*\p{L}/u.test(notes.value.trim()))
            ? 'Explain the required revision in at least 10 characters.' : '');
        if (!form.reportValidity()) return;
        confirmation.querySelector('p').textContent = action === 'accept'
            ? 'Accept these audit results? Evaluations become read-only. Existing CAR states are preserved.'
            : 'Return this audit to the assigned auditor with your revision feedback?';
        confirming = true; confirmation.returnValue = ''; confirmation.showModal();
        const confirmed = await new Promise(resolve => confirmation.addEventListener('close', () => resolve(confirmation.returnValue === 'confirm'), { once: true }));
        confirming = false; if (!confirmed) return;
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
    dialog.addEventListener('input', event => { if (event.target.name === 'reviewNotes') event.target.setCustomValidity(''); });
});
