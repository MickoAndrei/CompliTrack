let scriptPromise;
let scriptUrl;
const editors = new WeakMap();

export async function openDocumentEditor(container, response, onMessage) {
    closeDocumentEditor(container);
    if (!scriptPromise || scriptUrl !== response.scriptUrl) {
        scriptUrl = response.scriptUrl;
        scriptPromise = new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = response.scriptUrl;
            script.onload = resolve;
            script.onerror = () => { scriptPromise = undefined; script.remove(); reject(new Error('The local document server could not be reached.')); };
            document.head.append(script);
        });
    }
    await scriptPromise;
    if (!container.closest('dialog')?.open) return;
    const slot = document.createElement('div');
    slot.id = 'document-editor-'+response.sessionId;
    container.replaceChildren(slot);
    const config = { ...response.config, events: {
        onAppReady: () => onMessage('Use Save to create a revised archive version. Earlier versions are preserved.'),
        onDocumentStateChange: event => { if (event.data) onMessage('Changes pending. Use Save to save a revised version.'); },
        onError: () => onMessage('The document editor reported an error. Keep this window open and retry saving.'),
    } };
    editors.set(container, new window.DocsAPI.DocEditor(slot.id, config));
}

export function closeDocumentEditor(container) {
    editors.get(container)?.destroyEditor();
    editors.delete(container);
    container.replaceChildren();
}
