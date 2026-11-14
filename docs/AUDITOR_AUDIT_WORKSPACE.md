# Auditor Audit Workspace

The existing `/audits/{id}` workspace now follows the supplied two-panel checklist wireframe and five-section navigation. The main sidebar and navy, yellow, and white branding are retained.

## Changed files

- `resources/views/auditor/audit-workspace.blade.php`: compact record-based header, Overview, searchable two-panel Audit Checklist, Auditee Interviews, readiness checklist, incomplete-item links, and readable history timeline.
- `resources/views/auditor/workspace-modals.blade.php`: Add/Edit Checklist Item, Add Evidence Review, and Add Interview dialogs.
- `resources/views/auditor/evidence-requests.blade.php`: request register and New Evidence Request dialog; existing response review and accepted-result publication forms are retained.
- `resources/js/audit-workspace.js`: Alpine workspace state, section/item/evidence deep links, browser history, keyboard tab navigation, modal focus wrapping and restoration, unsaved-form tracking, departure/discard warnings, and validation-error recovery. Form values stay in memory while navigating sections; sensitive notes are not copied into browser storage.
- `resources/js/app.js`: loads the workspace module only on workspace pages.
- `public/css/audit-workspace.css`: scoped responsive workspace styles; desktop/tablet use two panels, and narrow screens put the item selector above the detail panel.
- `app/Http/Controllers/Web/AuditController.php`: batches displayed user names, supplies readiness and authorized attachment metadata, and returns private non-cacheable workspace HTML.
- `app/Services/AuditChecklist.php`: calculates missing submission requirements using the same rules as the existing submission validator. Completion counts evaluated items, not compliance.
- `tests/Feature/AuditWorkflowMongoTest.php`, `tests/Unit/AuditChecklistTest.php`, `tests/Unit/audit-workspace.test.cjs`, and `tests/Browser/audit-workspace.cjs`: workspace-specific regression coverage and isolated rendered browser fixtures.

## Preserved behavior

All actions use the existing POST endpoints, CSRF protection, optimistic version checks, assignment scope, ownership checks, MongoDB references, and notification delivery. Draft questions remain editable; Ready/Returned evaluations require reviewed evidence; submission and acceptance lock results; the assigned reviewer can accept or return submissions. Existing evidence requests, authorized downloads/uploads, physical-original signature verification, restricted reference reviews, private interviews, and optional observations remain supported. Findings do not automatically open CARs.

Question preparation and evaluation are separate. Evidence reviews remain available during Draft because the existing backend permits preparation-time reviews. No new workflow or duplicate workspace was introduced, and no main database was reset or replaced.

## Verification

Activity & submission history shows recorded actions chronologically with USTP gold check circles, a larger blue circle for the current workflow stage, and empty gray circles for upcoming stages. Its viewport is capped at five nodes or the Submission readiness card height, whichever is smaller. The remaining nodes can be scrolled with mouse, touch, or keyboard without a visible scrollbar. Height is recalculated when the review panel opens or its contents resize. Browser coverage checks the markers, height limits, and scrolling at desktop, tablet, and mobile widths.

- `npm.cmd run build`: passed.
- `node --test tests/Unit/*.test.cjs`: 38 tests passed, including workspace deep-link checks and existing frontend regressions.
- `php vendor/phpunit/phpunit/phpunit -c phpunit.audit.xml --filter 'AuditWorkflowMongoTest|AuditAccessTest|AuditChecklistTest'`: initial regression run passed, 21 tests and 572 assertions.
- `WRITE_WORKSPACE_UI_FIXTURES=1` with the workspace integration test and `AuditChecklistTest`: passed, 7 tests and 62 assertions. Covers actual stored interviews/requests, phase controls, readiness, evidence/evaluation, and locked rendering. MongoDB tests create and remove randomly named isolated test databases.
- `node tests/Browser/audit-workspace.cjs`: passed at 1440px, 768px, and 390px. Uses isolated rendered HTML and a read-only fixture server. Checks panel layout, overflow, all four dialogs, required controls, focus trapping, Escape, keyboard tabs, search, retained remarks, departure/discard warnings, browser history, evidence gating, readiness links, and submitted locks. Screenshots are generated in `storage/framework/testing/workspace-ui/`.
- PHP syntax checks and `git diff --check`: passed.

Qodo review could not run: the installed CLI reports `not_logged_in`. No automated Qodo review is claimed. Local code inspection, regression checks, and screenshot review were completed instead. Existing unrelated working-tree edits were preserved and no commit was created.

## Evidence submission fixes

The evidence response buttons are named `action`, which shadows the DOM `form.action` property. Both the request response handler and corrective-evidence modal now read the form's action attribute. Browser regressions check the actual destination for both Submit and Save draft; the new destination assertion failed before the fix.

Audit versions still protect against overwriting concurrent changes. For a stale auditor request, the server now returns to the workspace with a validation message and old input, reopening the request modal with its description, recipient, criterion and upload permission retained. For an auditee's asynchronous response, a 409 refreshes authorized request details and the version while keeping the original form, notes, selected File and submission key. The auditee must review the new details and explicitly retry. A locked request or changed upload permission does not receive a usable refreshed version. A queued dialog-close event cannot clear a newly reopened response.

`AuditeeEvidenceService` checks the submitted version before processing uploads and again during the atomic save; the write also matches the audit status. Stale JSON requests still return 409. No version check, lock or authorization was bypassed.

Additional changed files: `app/Services/AuditeeEvidenceService.php`, `app/Http/Controllers/Web/AuditeeController.php`, `resources/js/auditee-evidence-requests.js`, `resources/js/auditee-result-modal.js`, `tests/Browser/evidence-requests.cjs`, and `tests/Fixtures/evidence-requests.php`. The fixture generator resolves the configured asset URL to local files so tunnel configuration cannot affect the browser checks.

Verification: production build passed; five targeted MongoDB integration tests passed (207 assertions across two runs); browser checks passed at 1440, 900, 768, 390 and 320px, including selected-file preservation, explicit conflict retry, read-only conflicts, drafts, validation, modal focus and deep links. Tests use isolated data. Browser security prevents retaining a selected File across a full page reload; the in-place conflict recovery preserves it without reloading.
