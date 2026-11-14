const modal = document.querySelector('#evidence-request-modal');
let trigger;
let detailUrl;
let pendingDetail;
let session = 0;
let scrollLocks = [];
let showResponse = false;

async function loadRequest() {
    pendingDetail?.abort();
    const controller = new AbortController();
    pendingDetail = controller;
    const body = modal.querySelector('[data-evidence-body]');
    body.setAttribute('aria-busy', 'true');
    body.innerHTML = '<div class="evidence-loading" role="status"><span></span><span></span><span></span><p>Loading your evidence request…</p></div>';
    try {
        const response = await fetch(detailUrl, { credentials: 'same-origin', signal: controller.signal,
            headers: { Accept: 'text/html', 'X-Requested-With': 'XMLHttpRequest' } });
        if (!response.ok || response.redirected) throw new Error('Request unavailable');
        const page = new DOMParser().parseFromString(await response.text(), 'text/html');
        const detail = page.querySelector('[data-evidence-detail]');
        if (!detail) throw new Error('Details missing');
        if (controller.signal.aborted || !modal.open) return;
        body.replaceChildren(detail);
        body.scrollTop = 0;
        if (showResponse) {
            // Wait for Alpine to initialize the reused response form before focusing its controls.
            requestAnimationFrame(() => {
                if (!modal.open || controller.signal.aborted) return;
                const section = body.querySelector('[data-evidence-response-section]');
                section.scrollIntoView({ block: 'start' });
                (section.querySelector('select') || body).focus({ preventScroll: true });
            });
        }
    } catch (error) {
        if (controller.signal.aborted || !modal.open) return;
        body.innerHTML = '<div x-page-notice class="evidence-load-error" role="alert"><h3>Request unavailable</h3><p>This request could not be loaded. It may no longer be available to you.</p><button type="button" class="workflow-secondary" data-evidence-retry>Retry</button></div>';
    } finally {
        if (pendingDetail === controller) body.setAttribute('aria-busy', 'false');
    }
}

function openRequest(url, control, respond = false) {
    if (!modal) return;
    trigger = control;
    detailUrl = url;
    showResponse = respond;
    session += 1;
    modal.querySelector('[data-evidence-message]').textContent = '';
    if (!modal.open) {
        scrollLocks = [...new Set([document.documentElement, document.body, document.querySelector('#main-content')])]
            .filter(Boolean).map(element => ({ element, overflow: element.style.overflow }));
        scrollLocks.forEach(({ element }) => { element.style.overflow = 'hidden'; });
        modal.showModal();
    }
    modal.querySelector('[data-evidence-close]').focus({ preventScroll: true });
    loadRequest();
}

async function refreshCards() {
    const url = new URL(window.location.href);
    url.searchParams.delete('requestId');
    url.searchParams.delete('auditId');
    url.hash = '';
    const response = await fetch(url, { credentials: 'same-origin', headers: { Accept: 'text/html', 'X-Requested-With': 'XMLHttpRequest' } });
    if (!response.ok || response.redirected) throw new Error('List refresh failed');
    const page = new DOMParser().parseFromString(await response.text(), 'text/html');
    const selectors = ['[data-evidence-summary]', '[data-evidence-count]', '[data-evidence-list]'];
    if (selectors.some(selector => !page.querySelector(selector))) throw new Error('List missing');
    selectors.forEach(selector => document.querySelector(selector).replaceChildren(...page.querySelector(selector).childNodes));
    window.renderIcons?.();
}

async function refreshConflict(form, activeSession) {
    // Refresh request details without replacing the response form or its selected File.
    const response = await fetch(detailUrl, { credentials: 'same-origin',
        headers: { Accept: 'text/html', 'X-Requested-With': 'XMLHttpRequest' } });
    if (!response.ok || response.redirected) throw new Error('Request unavailable');
    const page = new DOMParser().parseFromString(await response.text(), 'text/html');
    if (activeSession !== session || !modal.open) return '';
    const detail = page.querySelector('[data-evidence-detail]');
    const latestForm = detail?.querySelector('.workflow-form');
    const version = latestForm?.querySelector('[name=version]')?.value;
    const method = form.querySelector('[name=method]').value;
    if (!version || ![...latestForm.querySelector('[name=method]').options].some(option => option.value === method)) {
        return 'This request is now read-only or its evidence permissions changed. Your response and selected file are still here. Contact your auditor before retrying.';
    }
    const current = modal.querySelector('[data-evidence-detail]');
    const section = current.querySelector('[data-evidence-response-section]');
    [...current.children].filter(child => child !== section).forEach(child => child.remove());
    [...detail.children].filter(child => !child.matches('[data-evidence-response-section]')).forEach(child => current.insertBefore(child, section));
    form.querySelector('[name=version]').value = version;
    window.renderIcons?.();
    return 'This audit was updated. The request details have been refreshed; your response and selected file are kept. Review the updated details, then retry.';
}

if (modal) {
    document.addEventListener('click', event => {
        const control = event.target.closest('[data-evidence-open]');
        if (control && event.button === 0 && !event.ctrlKey && !event.metaKey && !event.shiftKey && !event.altKey) {
            event.preventDefault();
            openRequest(control.href, control, control.hasAttribute('data-evidence-response'));
        }
        if (event.target.closest('[data-evidence-close]')) modal.close();
        if (event.target.closest('[data-evidence-retry]')) loadRequest();
    });
    modal.addEventListener('close', () => {
        if (modal.open) return; // A queued close must not clear a newly reopened response.
        session += 1;
        pendingDetail?.abort();
        scrollLocks.forEach(({ element, overflow }) => { element.style.overflow = overflow; });
        modal.querySelector('[data-evidence-body]').replaceChildren();
        const replacement = [...document.querySelectorAll('[data-evidence-open]')].find(control => control.href === trigger?.href);
        (trigger?.isConnected ? trigger : replacement || document.querySelector('.evidence-toolbar input'))?.focus({ preventScroll: true });
    });
    modal.addEventListener('keydown', event => {
        if (event.key !== 'Tab') return;
        const controls = [...modal.querySelectorAll('a[href], button:not(:disabled), input:not(:disabled):not([type="hidden"]), select:not(:disabled), textarea:not(:disabled), [tabindex="0"]')]
            .filter(control => control.getClientRects().length > 0);
        const first = controls[0], last = controls.at(-1);
        if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last?.focus(); }
        else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first?.focus(); }
    });
    modal.addEventListener('submit', async event => {
        const form = event.target.closest('.workflow-form');
        if (!form) return;
        event.preventDefault();
        if (form.dataset.saving) return;
        const activeSession = session;
        const message = modal.querySelector('[data-evidence-message]');
        const data = new FormData(form);
        if (event.submitter?.name) data.set(event.submitter.name, event.submitter.value);
        data.set('action', data.get('action') || 'submit');
        form.dataset.saving = 'true';
        form.querySelectorAll('button').forEach(button => { button.disabled = true; });
        message.setAttribute('role', 'status');
        message.textContent = data.get('action') === 'draft' ? 'Saving draft…' : 'Submitting evidence…';
        try {
            // Buttons named "action" shadow the form.action DOM property.
            const response = await fetch(form.getAttribute('action'), { method: 'POST', credentials: 'same-origin',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body: data });
            if (activeSession !== session || !modal.open) return;
            const result = await response.json();
            if (activeSession !== session || !modal.open) return;
            if (!response.ok) {
                message.setAttribute('role', 'alert');
                if (response.status === 409) {
                    message.textContent = 'This audit changed. Refreshing request details while keeping your response…';
                    try {
                        const explanation = await refreshConflict(form, activeSession);
                        if (activeSession === session && modal.open) message.textContent = explanation;
                    } catch {
                        if (activeSession === session && modal.open) message.textContent = 'The latest request could not be loaded. Your response and selected file are kept. Retry when your connection is available.';
                    }
                    return;
                }
                message.textContent = result.errors ? Object.values(result.errors).flat().join(' ') : response.status === 403 ? 'This request is read-only or your access changed. Close and reopen it to refresh.'
                    : 'Evidence could not be saved. Your response is still here; please retry.';
                return;
            }
            message.textContent = result.message;
            try { await refreshCards(); } catch { message.textContent = `${result.message} Refresh the page to update request counts.`; }
            if (activeSession !== session || !modal.open) return;
            showResponse = data.get('action') === 'draft';
            await loadRequest();
        } catch {
            if (activeSession === session && modal.open) {
                message.setAttribute('role', 'alert');
                message.textContent = 'Evidence could not be saved. Check your connection and retry. Your response is still here.';
            }
        } finally {
            delete form.dataset.saving;
            form.querySelectorAll('button').forEach(button => { button.disabled = false; });
        }
    });
    const openLinkedRequest = () => {
        if (modal.dataset.initialRequest) {
            openRequest(modal.dataset.initialRequest, null, window.location.hash === '#response');
            return;
        }
        const match = /^#request-([a-f\d-]{36})$/i.exec(window.location.hash);
        if (!match) return;
        const control = document.getElementById(`request-${match[1]}`)?.querySelector('[data-evidence-open]');
        openRequest(control?.href || `/auditee/evidence-requests?requestId=${encodeURIComponent(match[1])}`, control);
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', openLinkedRequest, { once: true });
    else openLinkedRequest();
    window.addEventListener('hashchange', openLinkedRequest);
}
