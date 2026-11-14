let originatingControl;
let detailUrl;
let detailRequest;
let scrollLocks = [];
let modalSession = 0;
let uploadUrl;
let uploadRequest;
let requestedTab = 'overview';

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
    uploadRequest?.abort();
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
        body.innerHTML = '<div x-page-notice role="alert"><p>This audit result could not be loaded. It may no longer be available to you.</p><button type="button" class="workflow-secondary" data-result-modal-retry>Retry</button></div>';
    } finally {
        if (detailRequest === controller) body.setAttribute('aria-busy', 'false');
    }
}

async function loadUpload(url) {
    const dialog = dialogElement();
    const slot = dialog.querySelector('[data-result-upload-slot]');
    if (!slot) return;
    selectTab(dialog, 'corrective');
    uploadUrl = url;
    uploadRequest?.abort();
    const controller = new AbortController();
    uploadRequest = controller;
    dialog.querySelector('[data-corrective-reference]').hidden = true;
    slot.hidden = false;
    slot.setAttribute('aria-busy', 'true');
    slot.innerHTML = '<p role="status">Loading authorized file upload…</p>';
    try {
        const response = await fetch(url, { credentials: 'same-origin', signal: controller.signal,
            headers: { Accept: 'text/html', 'X-Requested-With': 'XMLHttpRequest' } });
        if (!response.ok || response.redirected) throw new Error('Upload form unavailable');
        const page = new DOMParser().parseFromString(await response.text(), 'text/html');
        const upload = page.querySelector('[data-evidence-upload]');
        if (!upload) throw new Error('Upload form missing');
        if (controller.signal.aborted || !dialog.open) return;
        upload.dataset.checkId = new URL(url, window.location.href).searchParams.get('checkId') || '';
        const back = document.createElement('button');
        back.type = 'button';
        back.className = 'workflow-secondary result-upload-back';
        back.dataset.resultUploadBack = '';
        back.textContent = 'Use a reference or original instead';
        slot.replaceChildren(back, upload);
        back.focus({ preventScroll: true });
    } catch (error) {
        if (error.name !== 'AbortError' && dialog.open) {
            slot.innerHTML = '<div x-page-notice role="alert"><p>The upload form could not be loaded.</p><button type="button" class="workflow-secondary" data-result-upload-retry>Retry</button><button type="button" class="workflow-secondary" data-result-upload-back>Use a reference or original instead</button></div>';
        }
    } finally {
        if (uploadRequest === controller) slot.setAttribute('aria-busy', 'false');
    }
}

function hideUpload() {
    uploadRequest?.abort();
    const dialog = dialogElement();
    const slot = dialog?.querySelector('[data-result-upload-slot]');
    if (!slot) return;
    slot.replaceChildren();
    slot.hidden = true;
    dialog.querySelector('[data-corrective-reference]').hidden = false;
    dialog.querySelector('#corrective-evidence a[href^="/auditee/upload"]')?.focus({ preventScroll: true });
}

document.addEventListener('auditee-evidence-upload-cancel', hideUpload);

function openDetail(control, tabOverride) {
    const dialog = dialogElement();
    originatingControl = control;
    modalSession += 1;
    detailUrl = control.href;
    const tab = tabOverride || control.dataset?.resultModalTab || new URL(control.href, window.location.href).hash.slice(1);
    requestedTab = ['overview', 'evidence', 'corrective'].includes(tab) ? tab : 'overview';
    dialog.querySelector('[data-result-modal-heading]').innerHTML = '<h2 id="audit-result-modal-title">Audit Result Details</h2>';
    dialog.querySelector('[data-result-modal-message]').textContent = '';
    if (!scrollLocks.length) {
        scrollLocks = [...new Set([document.documentElement, document.body, document.querySelector('#main-content')])]
            .filter(Boolean).map(element => ({ element, overflow: element.style.overflow }));
    }
    scrollLocks.forEach(({ element }) => { element.style.overflow = 'hidden'; });
    dialog.showModal();
    dialog.querySelector('[data-result-modal-close]').focus({ preventScroll: true });
    loadDetail(requestedTab);
}

document.addEventListener('click', event => {
    const upload = event.target.closest('#audit-result-modal a[href^="/auditee/upload"]');
    if (upload && event.button === 0 && !event.ctrlKey && !event.metaKey && !event.shiftKey && !event.altKey) {
        event.preventDefault();
        loadUpload(upload.href);
        return;
    }
    if (event.target.closest('[data-result-upload-back]')) hideUpload();
    if (event.target.closest('[data-result-upload-retry]')) loadUpload(uploadUrl);
    const control = event.target.closest('[data-result-modal-open]');
    if (control && dialogElement() && event.button === 0 && !event.ctrlKey && !event.metaKey && !event.shiftKey && !event.altKey) {
        event.preventDefault();
        openDetail(control);
        return;
    }
    if (event.target.closest('[data-result-modal-close]')) dialogElement()?.close();
    if (event.target.closest('[data-result-modal-retry]')) loadDetail(requestedTab);
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
    if (document.querySelector('#evidence-preview-modal')?.open) return;
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
        if (dialog.open) return;
        document.querySelector('#evidence-preview-modal')?.close();
        uploadRequest?.abort();
        modalSession += 1;
        detailRequest?.abort();
        scrollLocks.forEach(({ element, overflow }) => { element.style.overflow = overflow; });
        scrollLocks = [];
        dialog.querySelector('[data-result-modal-body]').replaceChildren();
        dialog.querySelector('[data-result-modal-tabs]').replaceChildren();
        if (originatingControl?.isConnected) originatingControl.focus({ preventScroll: true });
    });
    const requestedDetail = new URL(window.location.href).searchParams.get('detail');
    if (/^[a-f\d]{24}$/i.test(requestedDetail || '')) {
        const control = [...document.querySelectorAll('[data-result-modal-open]')]
            .find(element => new URL(element.href).pathname === `/auditee/finding-details/${requestedDetail}`);
        openDetail(control || { href: `/auditee/finding-details/${requestedDetail}` }, window.location.hash.slice(1));
        const url = new URL(window.location.href);
        url.searchParams.delete('detail');
        window.history.replaceState(window.history.state, '', url);
    }
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
        // The reused evidence form has a submit button named "action".
        const response = await fetch(form.getAttribute('action'), { method: 'POST', credentials: 'same-origin',
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
