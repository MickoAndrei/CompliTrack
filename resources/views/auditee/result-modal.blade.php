    <dialog id="audit-result-modal" class="audit-result-modal auditee-results" aria-labelledby="audit-result-modal-title">
        <div class="audit-result-modal-layout">
            <header class="audit-result-modal-header"><div data-result-modal-heading><h2 id="audit-result-modal-title">Audit Result Details</h2></div><button type="button" data-result-modal-close aria-label="Close audit result details" class="result-modal-close">&times;</button></header>
            <div class="audit-result-modal-tabs" data-result-modal-tabs></div>
            <div class="audit-result-modal-body" data-result-modal-body tabindex="-1"></div>
            <footer class="audit-result-modal-footer"><p data-result-modal-message role="status"></p><button type="button" data-result-modal-close class="workflow-secondary">Close</button></footer>
        </div>
        <template data-result-modal-skeleton>
            <span class="sr-only" role="status">Loading audit result…</span>
            <div class="result-modal-skeleton" aria-hidden="true">
                <span class="results-skeleton result-skeleton-heading"></span>
                <div class="result-skeleton-fields">
                    @for($field = 0; $field < 4; $field++)
                    <div><span class="results-skeleton result-skeleton-label"></span><span class="results-skeleton"></span></div>
                    @endfor
                </div>
                @for($block = 0; $block < 3; $block++)
                <div class="result-skeleton-block"><span class="results-skeleton result-skeleton-heading"></span><span class="results-skeleton"></span><span class="results-skeleton result-skeleton-line"></span></div>
                @endfor
            </div>
        </template>
    </dialog>
@include('auditee.evidence-preview-modal')
