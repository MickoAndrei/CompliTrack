# Admin Audit Reviews redesign

Implemented November 15, 2026. README.md was read first. The existing audit assignment controller, MongoDB Audit/ComplianceCheck records, checklist evaluations, evidence requests, ATL scope, archive access and document preview authorization were inspected and reused.

## Result

- `/audits?view=reviews` displays compact database-derived Awaiting Review, Returned and Reviewed summary cards and a responsive review table.
- Search, audit plan, auditor, department/office and status narrow only the assigned reviewer's existing CET audit scope. Submission time comes from persisted submit history, with missing dates explicitly identified and sorted last. Awaiting reviews use oldest submissions first.
- Per the subsequent user requests, duplicate category tabs and Apply filters were removed. Dropdowns filter automatically, search waits 300 milliseconds after typing, and **Reset Filter** clears filters. Requests show a five-row skeleton; superseded requests are cancelled. Errors retain the last successful results. Pagination and browser Back/Forward preserve filter state.
- The existing native review dialog uses 85% desktop width. It displays Overview, Checklist Results, Evidence Reviewed, Findings and Corrective Actions, and Review History. Questions lead; references are secondary. C/N/O and auditor remarks remain read-only. History resolves persisted actor identities and timestamps.
- Revisions compare stable question IDs and recorded evaluation/evidence fields against the latest return snapshot. Missing history or unmatched question IDs are identified without asserting unproven differences. Returning a first submission does not itself label it a resubmission.
- Evidence previews reuse the existing ONLYOFFICE read-only session API. Document visibility, classification, download permission and live scope checks remain in its existing backend. Preview failures are surfaced to the reviewer.
- Accept Results and Return for Revision require a native confirmation dialog. Revision feedback requires at least ten characters and letters in both client and server validation. Acceptance permits optional remarks. The original history, version/status conditional update, audit log and notification paths remain authoritative.
- Assigned, active ATL reviewers alone can decide within their existing scope. Role labels alone do not provide unrestricted access. Auditor evaluations and CAR states are preserved. Accepted audits expose no decision form or auditor editing permission.

## Files changed for this task

| Area | Files |
| --- | --- |
| Backend | `app/Services/AuditReviewQueue.php`, `app/Http/Controllers/Web/AuditController.php` |
| Views | `resources/views/admin/audit-reviews.blade.php`, `resources/views/admin/audit-review.blade.php`, `resources/views/components/atl-review-modal.blade.php` |
| Frontend | `resources/js/atl-workspace.js`, `public/css/admin-ui.css` |
| Tests | `tests/Feature/AtlWorkspaceMongoTest.php`, `tests/Feature/AuditWorkflowMongoTest.php`, `tests/Unit/AuditReviewSubmissionTest.php`, `tests/Browser/audit-reviews.cjs` |
| Report | `docs/ADMIN_AUDIT_REVIEWS_REDESIGN.md` |

Existing dashboard, sidebar, controller and workspace edits were preserved. The CSS, JavaScript and ATL test file already contained dashboard work before this task; those changes were retained. No commit or deployment was made.

## Verification

- Latest isolated ATL/review-view run: **9 tests, 181 assertions passed**. Covers submitted loading, returned/reviewed categories, search and scope filters, ordering/counts, modal records, revision feedback, acceptance boundaries, history, resubmissions, stale decisions, CAR preservation and evidence-title privacy.
- Additional assignment-locking and document-editor live-scope regression: **2 tests, 53 assertions passed**.
- Unit test for first submission, return and resubmission history: **1 test, 3 assertions passed**.
- Broader isolated MongoDB run: **37 tests, 1,278 assertions**, with one obsolete `Accept Audit Results` text expectation. That expectation was updated to `Accept Results`, and its targeted rerun passed in the nine-test run above. The broader run includes the existing audit workflow and QMS archive permission tests.
- Headless Edge review checks passed at **1440, 1024, 768, 390 and 320 pixels**: automatic dropdown filtering, skeleton visibility, reset/error recovery, queue/modal sizing, required feedback, confirmation/cancellation, simulated acceptance/read-only state, focus restoration, Escape and no JavaScript exceptions.
- Existing dashboard browser regression passed at the same five widths, including loading, pagination, empty/error states and layout.
- Production Vite build, Blade compilation, PHP/JavaScript syntax and whitespace checks passed.
- A separate ten-test view/access run had nine passing tests and one unrelated failure: `AdminPortalViewTest::test_admin_dashboard_has_summary_cards_without_quick_actions` expects `admin-stat-heading`, which was already absent from the pre-existing dashboard edits. That unrelated dashboard implementation was preserved.

All MongoDB test writes used randomly named isolated databases and guarded cleanup. Browser decisions used local simulations over HTML rendered from those isolated tests. No application records were reset, no live audit review decision was made, and Firebase was not introduced.

## Remaining limits

- Audit records do not store a direct CAR relationship. The modal shows recorded checklist findings and states that no CARs are directly linked; it does not infer CAR ownership from department membership or fabricate corrective-action counts.
- Preview rendering still depends on the configured document server and supported file type. Existing API permission regressions passed; this task did not run a new live ONLYOFFICE rendering session.
- Duplicate/stale decisions are tested through optimistic version conflicts and the existing atomic conditional write; a simultaneous two-process decision race was not exercised.
- Qodo review operation `6a040ce1-ec81-43ca-b01b-9850b2cca87a` failed because Qodo could not obtain repository credentials for the current workspace. No automatic retry was started. Local inspection and the checks above provide the completed verification; no clean Qodo review is claimed.
- Queue ordering derives legacy submission dates from embedded history and paginates the scoped collection in memory. An indexed submission timestamp would be a future scaling improvement for substantially larger queues.

Regenerate private browser fixtures with `WRITE_REVIEW_UI_FIXTURES=1` and the isolated `AtlWorkspaceMongoTest` suite, then run `node tests/Browser/audit-reviews.cjs`. Fixtures and screenshots stay under `storage/framework/testing/audit-reviews/`.
