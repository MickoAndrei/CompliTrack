<dialog id="atl-review-dialog" class="atl-review-dialog" aria-labelledby="atl-review-title">
    <header class="atl-modal-header"><h2 id="atl-review-title">Audit Results Review</h2><button type="button" data-atl-review-close aria-label="Close audit review">&times;</button></header>
    <p x-page-notice class="atl-review-feedback" data-atl-review-feedback role="status" aria-live="polite" hidden></p>
    <div class="atl-review-body" data-atl-review-body aria-busy="false"></div>
</dialog>
<dialog id="atl-review-confirm" class="review-confirm" aria-labelledby="review-confirm-title">
    <h2 id="review-confirm-title">Confirm review decision</h2><p></p>
    <form method="dialog" class="atl-actions"><button value="cancel" class="atl-secondary" autofocus>Keep reviewing</button><button value="confirm" class="atl-primary">Confirm decision</button></form>
</dialog>
<dialog id="atl-review-preview" class="atl-review-dialog" aria-labelledby="review-preview-title">
    <header class="atl-modal-header"><h2 id="review-preview-title">Evidence preview · Read only</h2><button type="button" aria-label="Close evidence preview">&times;</button></header>
    <p role="status" aria-live="polite"></p><div data-preview-body style="flex:1;min-height:0"></div>
</dialog>
