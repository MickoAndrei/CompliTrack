document.addEventListener('DOMContentLoaded', () => {
    const dialog = document.querySelector('#car-history-dialog');
    if (!dialog) return;
    const body = dialog.querySelector('[data-car-history-body]');
    let opener, controller, currentUrl;
    const load = async () => {
        controller?.abort();
        const request = new AbortController(); controller = request;
        body.setAttribute('aria-busy', 'true'); body.textContent = 'Loading CAR history…';
        try {
            const url = new URL(currentUrl); url.hash = ''; url.searchParams.set('history', '1');
            const response = await fetch(url, { credentials: 'same-origin', signal: request.signal,
                headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'text/html' } });
            if (!response.ok || response.redirected) throw Error('CAR history could not be loaded. Your access or session may have changed.');
            const html = await response.text();
            if (!dialog.open || request.signal.aborted) return;
            body.innerHTML = html;
        } catch (error) {
            if (request.signal.aborted || !dialog.open) return;
            window.notify?.(error.message || 'CAR history could not be loaded.', 'error');
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
    dialog.querySelector('[data-car-history-close]').addEventListener('click', () => dialog.close());
    dialog.addEventListener('close', () => { controller?.abort(); opener?.focus(); });
    if (dialog.dataset.autoOpen === 'true') dialog.showModal();
});
