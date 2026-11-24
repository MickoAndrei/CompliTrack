let scriptPromise;
let scriptUrl;
const editors = new WeakMap();

export async function openDocumentEditor(container, response, onMessage) {
    closeDocumentEditor(container);
    if (!scriptPromise || scriptUrl !== response.scriptUrl) {
        scriptUrl = response.scriptUrl;
        scriptPromise = new Promise((resolve, reject) => {
            const script = document.createElement('script');
            const timer = setTimeout(() => { scriptPromise = undefined; script.remove(); reject(new Error('The document server connection timed out. Check the configured editor URL.')); }, 20000);
            script.src = response.scriptUrl;
            script.onload = () => { clearTimeout(timer); if (window.DocsAPI?.DocEditor) resolve(); else { scriptPromise = undefined; reject(new Error('The document server did not return the Docs API.')); } };
            script.onerror = () => { clearTimeout(timer); scriptPromise = undefined; script.remove(); reject(new Error(`The document server could not be reached at ${response.scriptUrl}. Check the existing ONLYOFFICE service and reverse proxy.`)); };
            document.head.append(script);
        });
    }
    await scriptPromise;
    if (!container.closest('dialog')?.open) return;
    const slot = document.createElement('div');
    slot.id = 'document-editor-'+response.sessionId;
    container.replaceChildren(slot);
    const config = { ...response.config, events: {
        onAppReady: () => onMessage(response.config.editorConfig.mode === 'view' ? 'Read-only document. Editing is not permitted.' : 'Editor connected. Earlier versions are preserved. Save status is confirmed by CompliTrack after a revision is stored.'),
        onDocumentReady: () => onMessage(response.config.editorConfig.mode === 'view' ? 'Read-only document ready. Editing is not permitted.' : 'Document ready. Earlier versions are preserved. Saved revisions are confirmed by CompliTrack.'),
        onDocumentStateChange: event => { if (event.data) onMessage('Unsaved changes. Saving in ONLYOFFICE is pending confirmation from CompliTrack.'); },
        onError: event => onMessage(`ONLYOFFICE connection or document error (${event.data?.errorCode ?? 'unknown'}). Keep this window open and check the document service connection.`),
    } };
    editors.set(container, new window.DocsAPI.DocEditor(slot.id, config));
}

export function closeDocumentEditor(container) {
    editors.get(container)?.destroyEditor();
    editors.delete(container);
    container.replaceChildren();
}
