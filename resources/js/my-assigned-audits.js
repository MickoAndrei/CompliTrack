import { onDocumentReady } from './dom-ready.js';

export function assignedAuditFilters({ root, document, window, fetch, DOMParser }) {
    const form = root.querySelector('[data-my-audit-filters]');
    const results = root.querySelector('[data-my-audit-results]');
    const loading = root.querySelector('[data-my-audit-loading]');
    const error = root.querySelector('[data-my-audit-error]');
    const message = root.querySelector('[data-my-audit-error-text]');
    if (!form || !results) return;
    let pending, timer, retryUrl, disposed = false;
    const sync = url => {
        ['search', 'departmentId', 'status'].forEach(name => { form.elements.namedItem(name).value = url.searchParams.get(name) || ''; });
        for (const tab of results.querySelectorAll('.maa-pills a')) {
            if ((new URL(tab.href).searchParams.get('status') || '') === (url.searchParams.get('status') || '')) tab.setAttribute('aria-current', 'page');
            else tab.removeAttribute('aria-current');
        }
    };
    const load = async (value, history = true, animateTab = false) => {
        clearTimeout(timer);
        const url = new URL(value, window.location.href);
        pending?.abort();
        const request = new AbortController(); pending = request; retryUrl = url;
        sync(url);
        const showSkeleton = () => {
            if (disposed || request.signal.aborted || pending !== request) return;
            loading.hidden = false; results.querySelector('[data-my-audit-content]').hidden = true;
        };
        // Let the tab's highlight transition lead the card loading state.
        loading.hidden = true; results.querySelector('[data-my-audit-content]').hidden = false;
        const skeletonTimer = animateTab ? setTimeout(showSkeleton, 160) : undefined;
        if (!animateTab) showSkeleton();
        error.hidden = true; results.setAttribute('aria-busy', 'true');
        try {
            const response = await fetch(url, { signal: request.signal, credentials: 'same-origin',
                headers: { Accept: 'text/html', 'X-Requested-With': 'XMLHttpRequest' } });
            if (!response.ok || response.redirected) throw Error('Assignments unavailable');
            const page = new DOMParser().parseFromString(await response.text(), 'text/html');
            const next = page.querySelector('[data-my-audit-results]');
            if (!next) throw Error('Assignments missing');
            if (disposed || request.signal.aborted || pending !== request) return;
            results.replaceChildren(...next.childNodes);
            sync(url);
            if (history && url.href !== window.location.href) window.history.pushState(window.history.state, '', url);
            window.renderIcons?.();
        } catch {
            if (disposed || request.signal.aborted || pending !== request) return;
            sync(new URL(window.location.href));
            message.textContent = 'Assigned audits could not be updated. Your previous results are still shown.';
            error.hidden = false;
        } finally {
            clearTimeout(skeletonTimer);
            if (!disposed && pending === request) { loading.hidden = true; results.querySelector('[data-my-audit-content]').hidden = false; results.setAttribute('aria-busy', 'false'); }
        }
    };
    const submit = event => {
        event?.preventDefault();
        const url = new URL(form.getAttribute('action'), window.location.href);
        // Preserve the browser-tab session context without carrying unrelated query parameters.
        const tab = new URL(window.location.href).searchParams.get('_tab');
        if (tab) url.searchParams.set('_tab', tab);
        for (const name of ['search', 'departmentId', 'status']) {
            const value = form.elements.namedItem(name).value.trim(); if (value) url.searchParams.set(name, value);
        }
        return load(url);
    };
    const navigate = event => {
        const reset = event.target.closest('[data-my-audit-reset]');
        if (reset) {
            event.preventDefault();
            for (const name of ['search', 'departmentId', 'status']) form.elements.namedItem(name).value = '';
            return submit();
        }
        const link = event.target.closest('a[href]');
        if (!link || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        const url = new URL(link.href);
        if (url.origin !== window.location.origin || url.pathname !== '/audits') return;
        event.preventDefault(); return load(url, true, !!link.closest('.maa-pills'));
    };
    form.addEventListener('submit', submit);
    form.elements.namedItem('search').addEventListener('input', () => {
        pending?.abort(); pending = undefined;
        loading.hidden = true; results.querySelector('[data-my-audit-content]').hidden = false; results.setAttribute('aria-busy', 'false');
        clearTimeout(timer); timer = setTimeout(submit, 300);
    });
    for (const name of ['departmentId', 'status']) form.elements.namedItem(name).addEventListener('change', submit);
    root.addEventListener('click', navigate);
    root.querySelector('[data-my-audit-retry]').addEventListener('click', () => retryUrl && load(retryUrl));
    const pop = () => load(window.location.href, false);
    window.addEventListener('popstate', pop);
    const dispose = () => { disposed = true; pending?.abort(); clearTimeout(timer); window.removeEventListener('popstate', pop); };
    window.addEventListener('pagehide', dispose, { once: true });
    return { load, dispose };
}

if (typeof document !== 'undefined') onDocumentReady(() => {
    const root = document.querySelector('[data-my-audits]');
    if (root) assignedAuditFilters({ root, document, window, fetch: window.fetch.bind(window), DOMParser });
});
