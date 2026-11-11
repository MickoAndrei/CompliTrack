<dialog id="evidence-preview-modal" class="evidence-preview-modal" aria-labelledby="evidence-preview-title">
    <header class="evidence-preview-header"><div><p>Evidence preview</p><h2 id="evidence-preview-title">Document preview</h2></div><button type="button" class="evidence-preview-close" data-evidence-preview-close aria-label="Close evidence preview">&times;</button></header>
    <div class="evidence-preview-body" data-evidence-preview-body aria-busy="false"></div>
    <footer class="evidence-preview-footer"><a class="workflow-secondary" data-evidence-preview-download hidden>Download file</a><button type="button" class="auditor-gold-button" data-evidence-preview-close>{{ $closeLabel ?? 'Back to audit result' }}</button></footer>
</dialog>
