// Keep results filtering and pagination inside the existing, authorized card.
let pendingUrl;

async function updateResults(url, focusValue) {
    const card = document.querySelector('#auditee-results-card');
    if (!card || card.getAttribute('aria-busy') === 'true') return;
    card.querySelector('[data-results-error]').hidden = true;
    // Reserve the current card/table space before swapping rows for skeletons.
    // Shorter results must not collapse the page or move the surrounding cards.
    card.style.minHeight = `${card.getBoundingClientRect().height}px`;
    const tableWrapper = card.querySelector('.results-table-wrapper');
    tableWrapper.style.height = `${tableWrapper.getBoundingClientRect().height}px`;
    tableWrapper.style.setProperty('--results-head-height', `${card.querySelector('thead').getBoundingClientRect().height}px`);
    pendingUrl = url;
    card.setAttribute('aria-busy', 'true');
    card.querySelector('[data-results-loading]').hidden = false;
    card.querySelector('[data-results-rows]').hidden = true;
    card.querySelector('[data-results-status]').textContent = 'Loading results';
    card.querySelectorAll('button').forEach(button => { button.disabled = true; });
    try {
        const response = await fetch(url, {
            credentials: 'same-origin',
            headers: { Accept: 'text/html', 'X-Requested-With': 'XMLHttpRequest' },
        });
        if (!response.ok || response.redirected) throw new Error('Results request failed');
        const page = new DOMParser().parseFromString(await response.text(), 'text/html');
        const updated = page.querySelector('#auditee-results-card');
        if (!updated) throw new Error('Results card missing');
        card.replaceChildren(...updated.childNodes);
        if (focusValue !== undefined) {
            [...card.querySelectorAll('.results-filter-pill')]
                .find(button => button.value === focusValue)?.focus({ preventScroll: true });
        }
    } catch {
        // Keep the last successful results and let the user retry in place.
        card.querySelector('[data-results-error]').hidden = false;
    } finally {
        card.setAttribute('aria-busy', 'false');
        card.querySelector('[data-results-loading]').hidden = true;
        card.querySelector('[data-results-rows]').hidden = false;
        card.querySelector('.results-table-wrapper').style.height = '';
        card.querySelector('[data-results-status]').textContent = '';
        card.querySelectorAll('button').forEach(button => { button.disabled = false; });
    }
}

document.addEventListener('submit', event => {
    const form = event.target.closest('#auditee-results-card .results-filters');
    if (!form) return;
    event.preventDefault();
    const data = new FormData(form);
    if (event.submitter?.name) data.set(event.submitter.name, event.submitter.value);
    const url = new URL(form.action, window.location.href);
    url.search = new URLSearchParams(data).toString();
    updateResults(url.href, event.submitter?.classList.contains('results-filter-pill') ? event.submitter.value : undefined);
});

// Let the layout adapt naturally when the viewport changes.
window.addEventListener('resize', () => {
    const card = document.querySelector('#auditee-results-card');
    if (card?.getAttribute('aria-busy') === 'false') card.style.minHeight = '';
});

document.addEventListener('click', event => {
    const retry = event.target.closest('#auditee-results-card [data-results-retry]');
    if (retry && pendingUrl) {
        updateResults(pendingUrl);
        return;
    }
    const link = event.target.closest('#auditee-results-card .results-pagination a[href]');
    if (!link || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
    event.preventDefault();
    updateResults(link.href);
});
