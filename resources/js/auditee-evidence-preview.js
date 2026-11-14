const preview = document.querySelector('#evidence-preview-modal');
let request;
let objectUrl;
let originatingLink;
let archiveUrl;
let fileName;
let wordResizeObserver;
let updateWordZoom;

function revokePreview() {
    wordResizeObserver?.disconnect();
    wordResizeObserver = undefined;
    updateWordZoom = undefined;
    if (preview) {
        preview.querySelector('[data-evidence-word-zoom]').hidden = true;
        preview.querySelector('[data-evidence-zoom]').value = 'fit';
    }
    if (objectUrl) URL.revokeObjectURL(objectUrl);
    objectUrl = undefined;
}

async function loadPreview() {
    request?.abort();
    const controller = new AbortController();
    request = controller;
    revokePreview();
    const body = preview.querySelector('[data-evidence-preview-body]');
    const download = preview.querySelector('[data-evidence-preview-download]');
    download.hidden = true;
    download.removeAttribute('href');
    body.setAttribute('aria-busy', 'true');
    body.innerHTML = '<p class="evidence-preview-notice" role="status">Loading authorized evidence…</p>';
    try {
        const response = await fetch(archiveUrl, { credentials: 'same-origin', signal: controller.signal,
            headers: { Accept: 'application/pdf,image/png,image/jpeg,application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'X-Requested-With': 'XMLHttpRequest' } });
        if (!response.ok || response.redirected) throw new Error('File unavailable');
        const blob = await response.blob();
        if (controller.signal.aborted || !preview.open) return;
        const mime = blob.type.split(';')[0].toLowerCase();
        objectUrl = URL.createObjectURL(blob);
        download.href = objectUrl;
        download.download = fileName;
        download.hidden = false;
        let content;
        if (mime === 'application/pdf') {
            content = document.createElement('iframe');
            content.title = `PDF preview: ${fileName}`;
            content.src = objectUrl;
            content.referrerPolicy = 'no-referrer';
        } else if (['image/png', 'image/jpeg'].includes(mime)) {
            content = document.createElement('img');
            content.alt = fileName;
            content.src = objectUrl;
        } else if (mime === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document') {
            const { renderAsync } = await import('docx-preview');
            if (controller.signal.aborted || !preview.open) return;
            // Isolate document styles and prohibit scripts, external resources and navigation.
            content = document.createElement('iframe');
            content.title = `Word document preview: ${fileName}`;
            content.setAttribute('sandbox', 'allow-same-origin');
            content.referrerPolicy = 'no-referrer';
            const loaded = new Promise((resolve, reject) => {
                content.addEventListener('load', resolve, { once: true });
                controller.signal.addEventListener('abort', () => reject(new DOMException('Aborted', 'AbortError')), { once: true });
            });
            content.srcdoc = `<!doctype html><html><head><meta charset="utf-8"><meta http-equiv="Content-Security-Policy" content="default-src 'none'; img-src data:; font-src data:; style-src 'unsafe-inline'; base-uri 'none'; form-action 'none'"><style>html,body{margin:0;background:#eef2f7}body{font-family:Arial,sans-serif}.docx-wrapper{padding:12px!important;gap:12px}.docx-wrapper>section.docx{margin-bottom:12px!important}*{box-sizing:border-box}</style></head><body></body></html>`;
            body.replaceChildren(content);
            await loaded;
            if (controller.signal.aborted || !preview.open) return;
            const doc = content.contentDocument;
            await renderAsync(await blob.arrayBuffer(), doc.body, doc.head, {
                useBase64URL: true, renderAltChunks: false, renderComments: false,
                ignoreLastRenderedPageBreak: false,
            });
            if (controller.signal.aborted || !preview.open) return;
            // Links in evidence are displayed without allowing them to replace the viewer.
            doc.addEventListener('click', event => {
                if (event.target.closest('a')) event.preventDefault();
            });
            const wrapper = doc.querySelector('.docx-wrapper');
            const fitPage = () => {
                if (!wrapper) return;
                const pages = [...wrapper.querySelectorAll('section.docx')];
                const pageWidth = Math.max(1, ...pages.map(page => page.offsetWidth));
                const zoom = preview.querySelector('[data-evidence-zoom]').value;
                wrapper.style.zoom = zoom === 'fit' ? Math.max(0.1, Math.min(1, (content.clientWidth - 24) / (pageWidth + 24))) : Number(zoom);
            };
            updateWordZoom = fitPage;
            preview.querySelector('[data-evidence-word-zoom]').hidden = false;
            wordResizeObserver = new ResizeObserver(fitPage);
            wordResizeObserver.observe(content);
            fitPage();
            content.dataset.wordReady = 'true';
            return;
        } else {
            content = document.createElement('p');
            content.className = 'evidence-preview-notice';
            content.textContent = 'A preview is unavailable for this file type. Download the authorized file to view it.';
        }
        body.replaceChildren(content);
    } catch (error) {
        if (controller.signal.aborted || !preview.open) return;
        body.innerHTML = '<div x-page-notice class="evidence-preview-notice" role="alert"><p>The evidence preview could not be loaded. The file may be unavailable or your access may have changed.</p><button type="button" class="workflow-secondary" data-evidence-preview-retry>Retry preview</button></div>';
    } finally {
        if (request === controller) body.setAttribute('aria-busy', 'false');
    }
}

if (preview) {
    preview.querySelector('[data-evidence-zoom]').addEventListener('change', () => updateWordZoom?.());
    preview.addEventListener('keydown', event => {
        if (event.key === 'Escape') {
            event.preventDefault();
            event.stopPropagation();
            preview.close();
        }
    });
    preview.addEventListener('cancel', event => {
        event.preventDefault();
        event.stopPropagation();
        preview.close();
    });
    document.addEventListener('click', event => {
        const link = event.target.closest('[data-evidence-preview]');
        if (link && event.button === 0 && !event.ctrlKey && !event.metaKey && !event.shiftKey && !event.altKey) {
            event.preventDefault();
            originatingLink = link;
            archiveUrl = link.href;
            fileName = link.dataset.fileName;
            preview.querySelector('#evidence-preview-title').textContent = fileName;
            preview.showModal();
            preview.querySelector('[data-evidence-preview-close]').focus({ preventScroll: true });
            loadPreview();
        }
        if (event.target.closest('[data-evidence-preview-close]')) preview.close();
        if (event.target.closest('[data-evidence-preview-retry]')) loadPreview();
    });
    preview.addEventListener('close', () => {
        if (preview.open) return; // A queued close event must not clear a newly opened preview.
        request?.abort();
        revokePreview();
        preview.querySelector('[data-evidence-preview-body]').replaceChildren();
        const download = preview.querySelector('[data-evidence-preview-download]');
        download.hidden = true;
        download.removeAttribute('href');
        if (originatingLink?.isConnected && (!originatingLink.closest('dialog') || originatingLink.closest('dialog').open)) {
            originatingLink.focus({ preventScroll: true });
        }
    });
}
