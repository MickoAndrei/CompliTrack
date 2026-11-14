import { onDocumentReady } from './dom-ready';

onDocumentReady(() => {
    const filters = document.querySelector('.car-filters');
    filters?.addEventListener('change', event => {
        if (event.target.matches('select, input[type="checkbox"]')) filters.requestSubmit();
    });
    filters?.querySelector('input[name="search"]')?.addEventListener('keydown', event => {
        if (event.key === 'Enter') { event.preventDefault(); filters.requestSubmit(); }
    });
    const dialog = document.querySelector('#car-history-dialog');
    if (!dialog) return;
    const body = dialog.querySelector('[data-car-history-body]');
    const title = dialog.querySelector('#car-dialog-title');
    const skeleton = dialog.querySelector('[data-car-details-skeleton]');
    const loadingStatus = dialog.querySelector('[data-car-loading-status]');
    const historyButton = dialog.querySelector('[data-car-view-history]');
    const selectTab = (key, focus = false) => {
        body.querySelectorAll('[data-car-tab]').forEach(tab => {
            const selected = tab.dataset.carTab === key;
            tab.setAttribute('aria-selected', String(selected)); tab.tabIndex = selected ? 0 : -1;
            if (selected && focus) tab.focus();
        });
        body.querySelectorAll('[data-car-panel]').forEach(panel => { panel.hidden = !panel.dataset.carPanel.split(' ').includes(key); });
        const main = body.querySelector('.car-detail-main');
        if (main) main.scrollTop = 0;
    };
    let opener, controller, currentUrl;
    const load = async () => {
        controller?.abort();
        const request = new AbortController(); controller = request;
        body.setAttribute('aria-busy', 'true');
        body.replaceChildren(skeleton.content.cloneNode(true));
        loadingStatus.textContent = 'Loading corrective action details.';
        historyButton.disabled = true;
        title.textContent = 'Corrective Action Details';
        try {
            const url = new URL(currentUrl); url.hash = ''; url.searchParams.set('history', '1');
            const response = await fetch(url, { credentials: 'same-origin', signal: request.signal,
                headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'text/html' } });
            if (!response.ok || response.redirected) throw Error('CAR history could not be loaded. Your access or session may have changed.');
            const html = await response.text();
            if (!dialog.open || request.signal.aborted) return;
            body.innerHTML = html;
            historyButton.disabled = false;
            loadingStatus.textContent = 'Corrective action details loaded.';
            const reference = body.querySelector('[data-car-reference]')?.dataset.carReference;
            title.textContent = reference ? `${reference} · Corrective Action Details` : 'Corrective Action Details';
        } catch (error) {
            if (request.signal.aborted || !dialog.open) return;
            window.notify?.(error.message || 'CAR history could not be loaded.', 'error');
            loadingStatus.textContent = 'Corrective action details could not be loaded. Please retry.';
            body.textContent = '';
            const retry = document.createElement('button'); retry.type = 'button';
            retry.className = 'atl-secondary'; retry.textContent = 'Retry loading CAR history';
            retry.addEventListener('click', load); body.append(retry);
        } finally {
            if (controller === request) body.setAttribute('aria-busy', 'false');
        }
    };
    document.addEventListener('click', event => {
        const link = event.target.closest('a[data-car-history]');
        if (!link || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        const url = new URL(link.href);
        if (url.origin !== location.origin || url.pathname !== '/admin/compliance-records') return;
        event.preventDefault(); opener = link; currentUrl = url.href;
        if (!dialog.open) dialog.showModal();
        load();
    });
    dialog.querySelectorAll('[data-car-history-close]').forEach(button => button.addEventListener('click', () => dialog.close()));
    dialog.querySelector('[data-car-view-history]').addEventListener('click', () => selectTab('history', true));
    body.addEventListener('click', event => {
        const tab = event.target.closest('[data-car-tab]');
        if (tab) selectTab(tab.dataset.carTab);
    });
    body.addEventListener('keydown', event => {
        const tab = event.target.closest('[data-car-tab]');
        if (!tab || !['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
        event.preventDefault();
        const tabs = [...body.querySelectorAll('[data-car-tab]')];
        const index = event.key === 'Home' ? 0 : event.key === 'End' ? tabs.length - 1
            : (tabs.indexOf(tab) + (event.key === 'ArrowRight' ? 1 : -1) + tabs.length) % tabs.length;
        selectTab(tabs[index].dataset.carTab, true);
    });
    dialog.addEventListener('close', () => { controller?.abort(); opener?.focus(); });
    if (dialog.dataset.autoOpen === 'true') dialog.showModal();
});
