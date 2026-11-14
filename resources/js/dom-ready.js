export function onDocumentReady(callback) {
    const run = () => globalThis.window?.complitrackTabReady
        ? window.complitrackTabReady.then(callback)
        : callback();
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', run, { once: true });
    else run();
}
