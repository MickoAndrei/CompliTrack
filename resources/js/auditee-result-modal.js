let originatingControl;
let detailUrl;
let detailRequest;
let scrollLocks = [];
let modalSession = 0;
let uploadUrl;

function selectTab(root, key) {
    root.querySelectorAll('[data-result-tab]').forEach(tab => {
        const selected = tab.dataset.resultTab === key;
        tab.setAttribute('aria-selected', String(selected));
        tab.tabIndex = selected ? 0 : -1;
    });
    root.querySelectorAll('[data-result-panel]').forEach(panel => {
        panel.hidden = panel.dataset.resultPanel !== key;
    });
}

function dialogElement() { return document.querySelector('#audit-result-modal'); }

async function loadDetail(selectedTab = 'overview') {
    const dialog = dialogElement();
    detailRequest?.abort();
    const controller = new AbortController();
    detailRequest = controller;
    const body = dialog.querySelector('[data-result-modal-body]');
    dialog.querySelector('[data-result-modal-tabs]').replaceChildren();
    body.setAttribute('aria-busy', 'true');
    body.replaceChildren(dialog.querySelector('[data-result-modal-skeleton]').content.cloneNode(true));
    try {
        const response = await fetch(detailUrl, { credentials: 'same-origin', signal: controller.signal,
            headers: { Accept: 'text/html', 'X-Requested-With': 'XMLHttpRequest' } });
        if (!response.ok || response.redirected) throw new Error('Detail request failed');
        const page = new DOMParser().parseFromString(await response.text(), 'text/html');
        const detail = page.querySelector('[data-result-detail]');
        const header = detail?.querySelector('[data-result-detail-header]');
        if (!detail || !header) throw new Error('Details missing');
        if (controller.signal.aborted || !dialog.open) return;
        header.querySelector('[data-result-title]').id = 'audit-result-modal-title';
        dialog.querySelector('[data-result-modal-heading]').replaceChildren(...header.childNodes);
        header.remove();
        dialog.querySelector('[data-result-modal-tabs]').replaceChildren(detail.querySelector('.result-detail-tabs'));
        body.replaceChildren(detail);
        selectTab(dialog, selectedTab);
        if (!dialog.contains(document.activeElement)) {
            dialog.querySelector(`[data-result-tab="${selectedTab}"]`).focus({ preventScroll: true });
        }
    } catch (error) {
        if (error.name === 'AbortError' || !dialog.open) return;
        body.innerHTML = '<div role="alert"><p>This audit result could not be loaded. It may no longer be available to you.</p><button type="button" class="workflow-secondary" data-result-modal-retry>Retry</button></div>';
    } finally {
        if (detailRequest === controller) body.setAttribute('aria-busy', 'false');
    }
}

async function loadUpload(url) {
    const dialog = dialogElement();
    uploadUrl = url;
    detailRequest?.abort();
    const controller = new AbortController();
    detailRequest = controller;
    const body = dialog.querySelector('[data-result-modal-body]');
    body.setAttribute('aria-busy', 'true');
    body.replaceChildren(dialog.querySelector('[data-result-modal-skeleton]').content.cloneNode(true));
    dialog.querySelector('[data-result-modal-tabs]').innerHTML = '<button type="button" class="workflow-secondary result-upload-back" data-result-upload-back>← Back to audit result</button>';
    try {
        const response = await fetch(url, { credentials: 'same-origin', signal: controller.signal,
            headers: { Accept: 'text/html', 'X-Requested-With': 'XMLHttpRequest' } });
        if (!response.ok || response.redirected) throw new Error('Upload form unavailable');
        const page = new DOMParser().parseFromString(await response.text(), 'text/html');
        const upload = page.querySelector('[data-evidence-upload]');
        if (!upload) throw new Error('Upload form missing');
        if (controller.signal.aborted || !dialog.open) return;
        upload.dataset.checkId = new URL(url, window.location.href).searchParams.get('checkId') || '';
        body.replaceChildren(upload);
        dialog.querySelector('[data-result-upload-back]').focus({ preventScroll: true });
    } catch (error) {
        if (error.name !== 'AbortError' && dialog.open) {
            body.innerHTML = '<div role="alert"><p>The upload form could not be loaded.</p><button type="button" class="workflow-secondary" data-result-upload-retry>Retry</button></div>';
        }
    } finally {
        if (detailRequest === controller) body.setAttribute('aria-busy', 'false');
    }
}

function openDetail(control) {
    const dialog = dialogElement();
    originatingControl = control;
    modalSession += 1;
    detailUrl = control.href;
    dialog.querySelector('[data-result-modal-heading]').innerHTML = '<h2 id="audit-result-modal-title">Audit Result Details</h2>';
    dialog.querySelector('[data-result-modal-message]').textContent = '';
    scrollLocks = [...new Set([document.documentElement, document.body, document.querySelector('#main-content')])]
        .filter(Boolean).map(element => ({ element, overflow: element.style.overflow }));
    scrollLocks.forEach(({ element }) => { element.style.overflow = 'hidden'; });
    dialog.showModal();
    dialog.querySelector('[data-result-modal-close]').focus({ preventScroll: true });
    loadDetail();
}

document.addEventListener('click', event => {
    const upload = event.target.closest('#audit-result-modal a[href^="/auditee/upload"]');
    if (upload && event.button === 0 && !event.ctrlKey && !event.metaKey && !event.shiftKey && !event.altKey) {
        event.preventDefault();
        loadUpload(upload.href);
        return;
    }
    if (event.target.closest('[data-result-upload-back]')) loadDetail('corrective');
    if (event.target.closest('[data-result-upload-retry]')) loadUpload(uploadUrl);
    const control = event.target.closest('[data-result-modal-open]');
    if (control && dialogElement() && event.button === 0 && !event.ctrlKey && !event.metaKey && !event.shiftKey && !event.altKey) {
        event.preventDefault();
        openDetail(control);
        return;
    }
    if (event.target.closest('[data-result-modal-close]')) dialogElement()?.close();
    if (event.target.closest('[data-result-modal-retry]')) loadDetail();
    const tab = event.target.closest('[data-result-tab]');
    if (tab) selectTab(tab.closest('#audit-result-modal') || tab.closest('[data-result-detail]'), tab.dataset.resultTab);
});

document.addEventListener('auditee-evidence-uploaded', event => {
    const dialog = dialogElement();
    if (dialog?.open && dialog.contains(event.target)) {
        dialog.querySelector('[data-result-modal-message]').textContent = 'Evidence uploaded and encrypted successfully.';
        loadDetail('evidence');
    }
});

document.addEventListener('keydown', event => {
    const tab = event.target.closest('[data-result-tab]');
    if (tab && ['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) {
        event.preventDefault();
        const root = tab.closest('#audit-result-modal') || tab.closest('[data-result-detail]');
        const tabs = [...root.querySelectorAll('[data-result-tab]')];
        const index = event.key === 'Home' ? 0 : event.key === 'End' ? tabs.length - 1
            : (tabs.indexOf(tab) + (event.key === 'ArrowRight' ? 1 : -1) + tabs.length) % tabs.length;
        selectTab(root, tabs[index].dataset.resultTab);
        tabs[index].focus();
    }
    const dialog = dialogElement();
    if (!dialog?.open || event.key !== 'Tab') return;
    const controls = [...dialog.querySelectorAll('a[href], button:not(:disabled), input:not(:disabled):not([type="hidden"]), select:not(:disabled), textarea:not(:disabled), [tabindex="0"]')]
        .filter(element => element.getClientRects().length > 0);
    const first = controls[0], last = controls.at(-1);
    if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last?.focus(); }
    else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first?.focus(); }
});

const dialog = dialogElement();
if (dialog) {
    dialog.addEventListener('close', () => {
        modalSession += 1;
        detailRequest?.abort();
        scrollLocks.forEach(({ element, overflow }) => { element.style.overflow = overflow; });
        dialog.querySelector('[data-result-modal-body]').replaceChildren();
        dialog.querySelector('[data-result-modal-tabs]').replaceChildren();
        if (originatingControl?.isConnected) originatingControl.focus({ preventScroll: true });
    });
}

document.querySelectorAll('[data-result-detail]').forEach(root => selectTab(root, 'overview'));

document.addEventListener('submit', async event => {
    const form = event.target.closest('#audit-result-modal .workflow-form');
    if (!form) return;
    event.preventDefault();
    const message = dialog.querySelector('[data-result-modal-message]');
    const session = modalSession;
    const data = new FormData(form);
    form.querySelectorAll('button').forEach(button => { button.disabled = true; });
    message.textContent = 'Submitting evidence…';
    try {
        const response = await fetch(form.action, { method: 'POST', credentials: 'same-origin',
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body: data });
        const result = await response.json();
        if (session !== modalSession || !dialog.open) return;
        if (!response.ok) {
            message.textContent = result.errors ? Object.values(result.errors).flat().join(' ') : 'Evidence could not be submitted. Please try again.';
            return;
        }
        if (!dialog.open) return;
        message.textContent = result.message;
        await loadDetail('corrective');
    } catch {
        if (session === modalSession && dialog.open) message.textContent = 'Evidence could not be submitted. Check your connection and retry.';
    } finally {
        form.querySelectorAll('button').forEach(button => { button.disabled = false; });
    }
});
