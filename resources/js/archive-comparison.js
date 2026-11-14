// Each pane owns its requests, object URLs and observers independently.
const sessions = new WeakMap();

export function clearComparisonPane(container) {
    const session = sessions.get(container);
    session?.controller.abort();
    session?.observer?.disconnect();
    if (session?.url) URL.revokeObjectURL(session.url);
    sessions.delete(container);
    container.replaceChildren();
    container.removeAttribute('aria-busy');
}

export async function renderComparisonPane(container, version) {
    clearComparisonPane(container);
    if (!version) return;
    const session = { controller: new AbortController() };
    sessions.set(container, session);
    const { signal } = session.controller;
    const current = () => !signal.aborted && sessions.get(container) === session;
    container.setAttribute('aria-busy', 'true');
    const notice = document.createElement('p');
    notice.setAttribute('role', 'status');
    notice.textContent = 'Loading version…';
    container.append(notice);
    try {
        const response = await fetch(`/api/v1/documents/${encodeURIComponent(version._id)}/download`, {
            credentials: 'same-origin', signal, headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });
        if (!response.ok || response.redirected) throw new Error('Version unavailable');
        const blob = await response.blob();
        if (!current()) return;
        const mime = blob.type.split(';')[0].toLowerCase();
        const frame = document.createElement('iframe');
        frame.title = `Version ${version.versionNumber}: ${version.originalName || version.title}`;
        frame.referrerPolicy = 'no-referrer';
        if (mime === 'application/pdf') {
            session.url = URL.createObjectURL(blob);
            frame.src = session.url;
            container.replaceChildren(frame);
        } else if (mime === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document') {
            const { renderAsync } = await import('docx-preview');
            if (!current()) return;
            frame.setAttribute('sandbox', 'allow-same-origin');
            frame.srcdoc = `<!doctype html><html><head><meta charset="utf-8"><meta http-equiv="Content-Security-Policy" content="default-src 'none'; img-src data:; font-src data:; style-src 'unsafe-inline'; base-uri 'none'; form-action 'none'"><style>html,body{margin:0;background:#eef2f7}.docx-wrapper{padding:12px!important}*{box-sizing:border-box}</style></head><body></body></html>`;
            const loaded = new Promise((resolve, reject) => {
                frame.addEventListener('load', resolve, { once: true });
                signal.addEventListener('abort', () => reject(new DOMException('Aborted', 'AbortError')), { once: true });
            });
            container.replaceChildren(frame);
            await loaded;
            if (!current()) return;
            const doc = frame.contentDocument;
            await renderAsync(await blob.arrayBuffer(), doc.body, doc.head, {
                useBase64URL: true, renderAltChunks: false, renderComments: false, ignoreLastRenderedPageBreak: false,
            });
            if (!current()) return;
            doc.addEventListener('click', event => { if (event.target.closest('a')) event.preventDefault(); });
            const fit = () => {
                const wrapper = doc.querySelector('.docx-wrapper');
                if (!wrapper) return;
                const width = Math.max(1, ...[...wrapper.querySelectorAll('section.docx')].map(page => page.offsetWidth));
                wrapper.style.zoom = Math.max(0.1, Math.min(1, (frame.clientWidth - 24) / (width + 24)));
            };
            session.observer = new ResizeObserver(fit);
            session.observer.observe(frame);
            fit();
            frame.dataset.wordReady = 'true';
        } else if (['image/png', 'image/jpeg'].includes(mime)) {
            const image = document.createElement('img');
            session.url = URL.createObjectURL(blob);
            image.src = session.url;
            image.alt = frame.title;
            container.replaceChildren(image);
        } else {
            throw new Error('Unsupported comparison format');
        }
    } catch {
        if (!current()) return;
        notice.setAttribute('role', 'alert');
        notice.textContent = 'This version could not be displayed. Your access may have changed. Choose a version again to retry.';
        container.replaceChildren(notice);
    } finally {
        if (current()) container.setAttribute('aria-busy', 'false');
    }
}
