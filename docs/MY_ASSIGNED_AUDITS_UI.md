# My Assigned Audits UI

The existing auditor `/audits` page now uses compact cards, four summary metrics, search, department and status filters, and phase-specific links into the existing Audit Workspace. The sidebar and navy, yellow and white theme are retained. No database reset, data replacement, lifecycle migration, new API or duplicate workspace is involved.

## Modified files

- `app/Http/Controllers/Web/AuditController.php`: auditor-only listing branch, input validation, HTML fragment responses, private/no-store caching and a retryable database error view. Existing admin branches and workflow mutations are preserved.
- `app/Services/AuditorAuditListing.php`: projects assignment-scoped MongoDB records into cards, metrics, permitted return feedback, department choices and 12-record pages.
- `resources/views/auditor/audits.blade.php`: page heading, metrics, filter controls, loading and error states.
- `resources/views/auditor/partials/assigned-audit-summary.blade.php`, `assigned-audit-results.blade.php`, and `assigned-audit-fragment.blade.php`: reusable metrics, compact cards, status links, filtered/empty results and pagination.
- `public/css/my-assigned-audits.css`: responsive styles scoped to the listing, including overrides for the shared portal's stacked form spacing.
- `resources/js/my-assigned-audits.js`: debounced filtering, cancellation of obsolete requests, partial updates, explicit retry, URL/history handling, browser-tab context and cleanup.
- `resources/js/app.js`: loads the listing module only on this page.
- `tests/Unit/AuditorAuditListingTest.php`, `tests/Unit/my-assigned-audits.test.cjs`, `tests/Feature/AssignedAuditsViewTest.php`, `tests/Feature/AuditWorkflowMongoTest.php`, and `tests/Browser/my-assigned-audits.cjs`: presentation, filtering, authorization, failure and responsive browser coverage.

## State and metric definitions

The persisted statuses remain Draft, Ready, Submitted, Returned and Accepted. A Ready audit with saved evidence reviews or evaluations displays as In Progress and offers Continue Audit. A Ready audit without those records offers Open Audit. No display mapping changes the database or the workspace's editing rules.

Total Assigned includes all records within the existing `AtlWorkspace::audits()` boundary, which requires both the authenticated auditor assignment and an authorized department. In Progress includes Ready, started and Returned audits; its help text explains this. Awaiting Review counts Submitted audits, and Completed counts Accepted audits. Metrics are computed before applying filters, across all authorized pages. Department choices also come only from these assignments.

Titles and references use stored values. Missing titles or schedules are explicitly labeled as unrecorded/unavailable. Progress counts saved N/C/O evaluations and is labeled as checklist completion, never compliance percentage. Submitted cards show Awaiting Audit Team Leader Review only when the audit is Submitted and has a recorded reviewer; missing reviewer assignments are identified separately. Editing locks remain enforced by the existing workspace and server.

Returned cards project only the designated reviewer's most recent return notes and local date. Evidence notes, interviews, previous-item snapshots and unrelated actors' notes are omitted. Long feedback has an expandable full text. All card output is Blade-escaped.

## Requests and failure handling

The register uses a compact layout with 12px above the summary cards, tighter metric padding and smaller gaps between cards. The page scrollbar is hidden only for this listing; overflow remains reachable on smaller screens and for longer results. Browser checks verify that the six-card fixture fits at 1440×1100 without page scrolling.

The initial page is server rendered and makes no listing API call or polling request. The listing uses a single scoped MongoDB projection without per-card user/department queries. Search is a case-insensitive literal match against stored title, reference and department, combined with department and status filters. Pagination and browser history preserve those filters and the tab's session context.

Subsequent filter requests reuse `/audits` and return only the metrics/results fragment. New input cancels older requests; stale responses cannot replace newer results. Failed updates keep the previous cards and show Retry. Initial database failures return a 503 page with Retry and no fabricated zero metrics. No account data is stored in persistent browser storage or a shared cache.

The filter row has one Clear Filters button. Department and status changes apply immediately, and search applies after a 300ms typing delay. Clearing resets all three filters and pagination while preserving the browser-tab context. Only the My Audits results are replaced; the summary metrics and filter controls remain in place. During requests, two animated card skeletons replace the cards and pagination, with a screen-reader loading announcement and reduced-motion support. The status tabs, record count, and completion note stay visible, and tabs remain clickable during requests. Failed requests restore the previous cards. Browser coverage checks the skeletons, visible interactive tabs, clear control, and retained summary/filter DOM nodes at all five viewport sizes.

## Verification

Status tab clicks select the new tab immediately with a 160ms color transition before showing card skeletons. The request starts immediately; fast responses skip the skeleton. Failed requests restore the selection matching the previous results, and rapid switches cancel obsolete requests. Reduced-motion preferences disable the tab transition.

- Production Vite build passed.
- All 41 JavaScript unit tests passed, including no redundant initial listing requests, filter encoding, stale-result protection, preserved history, failures and retries.
- New PHP presentation/error/access checks passed: six tests, 43 assertions.
- Broader audit workflow, assignment authorization, listing and failure regressions passed: 23 tests, 724 assertions using isolated MongoDB databases.
- Isolated MongoDB listing test passed: 49 assertions covering metrics, derived actions, literal and combined filtering, feedback privacy, pagination scope, private records, malformed inputs and revoked department assignments.
- Browser checks passed at 1440, 900, 768, 390 and 320px. Coverage includes compact cards, metrics, existing workspace links, feedback, checklist completion, keyboard filter order, search icon clearance, shared style isolation, filters, history, empty/error/retry states and no redundant initial requests. Screenshots are in `storage/framework/testing/my-audits-ui/`.
- PHP syntax checks and scoped `git diff --check` passed.

Qodo review remains unavailable because its installed CLI is not signed in. Existing unrelated local edits are preserved, and no commit was created.
