# Auditee workflow implementation report

## Starting point and reuse

Read the complete root README, then the attached `plan.md`, before implementation. Actual architecture is Laravel 12/PHP 8.2 with MongoDB application models, SQLite framework support, Blade/Alpine/Tailwind/Vite, Sanctum/JWT, Node authenticated Socket.IO/polling and Flutter. README scaffold descriptions remain outdated; the actual modules are implemented. No dependency, Firebase integration, parallel checklist module or replacement auth system was added.

Existing Auditor assignments/checklist/evaluation/review, CET Admin scoping, legacy ComplianceCheck/CAR records, ArchiveDocument encryption/storage, role middleware and persisted notifications were reused. The Auditee previously had legacy result lists, file-only corrective upload, a table notification view, upload archives and brief standards help. It had no auditee-facing active-audit evidence-request relationship. That missing relationship is now embedded in existing audits.

## Implemented workflow and pages

- Dashboard: real owned legacy results, explicitly shared accepted checklist results, open/closed CAR counts, pending evidence requests, next actions, active request/result previews and persisted notifications. Descriptive counts replace the ambiguous personal compliance percentage. No fabricated records or score.
- My Audit Results: preserves `/auditee/my-findings`; separate shared accepted results from legacy C/N records. Search, result and actionable/closed filters; legacy observations are not invented. Detail routes are server-scoped, show permitted result/remarks, standards, responsible auditor, existing dates, accessible files, response/history and corrective action only for open/in-progress nonconforming checks.
- During Audit: new Evidence Requests page shows only explicitly addressed requests in the auditee's current department. Search/status filters and pagination. Audit header and auditor name are visible; internal questions, full checklists, interviews and private audit remarks are not exposed. Public request text is authored separately by the assigned auditor.
- Provide Evidence: retains `/auditee/upload` and existing secure upload API. The two evidence purposes have contextual navigation. During-audit requests support digital references, physical-original availability, interview/process descriptions, and explicitly authorized encrypted files. Drafts resume; saved draft files can be reused without another upload. Corrective references and physical originals are recorded from the related open result, independently of during-audit requests.
- Auditor integration: existing workspace can create recipient-scoped requests, inspect submitted responses, return for information or record a factual review. A reviewed response links to the existing item evidence array, then the auditor separately records N/C/O. Auditees cannot self-certify signatures. Requested items cannot be silently edited/deleted. Submitted/accepted audits remain locked for evidence edits.
- Accepted result sharing: after designated reviewer acceptance, the assigned auditor may explicitly share the saved N/C/O result and a separately written permitted summary with the request recipient. This adds public metadata only, preserves the accepted checklist and does not expose internal remarks/interviews, automatically register a finding, or create a CAR. Shared metadata is immutable through this action. Sharing has recipient notification and audit log.
- Notifications: responsive wrapping feed, full timestamps, read/unread text, type/search/unread filters, pagination, mark-read and related-record destinations. Existing authenticated HTTP refresh and Socket.IO polling remain. Request/response/review/publication events persist through NotificationDispatchService and its dedupe index. No Firebase or closed-app OS push.
- Archives: own authorized uploaded files remain accessible, read-only, with search/year filtering, encrypted/read-only labels and a link to closed audit results. Files are not mislabeled as archived audits; no retention rule is imposed.
- Help & Standards: practical preparation, evidence methods, two workflow purposes, draft/submission/review guidance, N/C/O meanings and controlled-document permissions. No protected forms or unlicensed standards text copied. External institutional reference links/policies are not invented.

## Data and authorization

No new collection is needed. New optional `audits.evidenceRequests` embeds stable request UUIDs, item UUID, auditee ID, permitted description/reference, allowUpload, status, draft, response history and optional published result. Request lifecycle is narrowly requested → response_submitted → reviewed/needs_information; draft is private working data, not compliance. Responses retain author/time and auditor feedback/verification separately. Existing audits without these fields remain valid.

Legacy checks optionally embed `evidenceResponses`; uploads retain existing linkedArchiveIds. ArchiveDocument optionally stores auditId/requestId for during-audit files. Notifications optionally store an internal destination. No record backfill or relational migration required.

Every new route checks actor role/active state, owning auditor or explicit auditee recipient, department, parent audit and request/item identity. Auditors require current department assignment. Cross-recipient access is denied even within the same department. Auditees cannot access full Auditor workspace, issue requests, review signatures or publish results. Evidence mutations use existing audit version conflict checks and lifecycle locks. Stable response keys suppress normal retries. Draft notes are never rendered to auditors; submitted responses and explicit feedback are retained. File download still uses server-side archive scope, with normalized string/ObjectId ownership.

Upload safeguards: reuse private AES-256-GCM storage, random IV/tag and SHA-256 verification, 20 MB validation and allowed signatures. Fixed the raw MongoDB string-vs-ObjectId link bug in corrective upload. Closed CAR uploads are denied. Auditee department is checked for corrective uploads. DOCX now verifies required OOXML ZIP entries instead of accepting arbitrary ZIP signatures. During-audit uploads require both auditor upload permission and auditee copy authorization. Existing mobile/legacy corrective upload API remains compatible; the web form adds a copy-permission confirmation. No automatic change to result/CAR status after response/upload.

## Exact files

Created:

- `app/Services/AuditeeEvidenceService.php`: scoped parent/request lookup and versioned saves.
- `app/Http/Controllers/Web/AuditeeController.php`: dashboard, results/details/archives, request/response/review/publication and corrective references.
- `resources/views/auditee/evidence-requests.blade.php`: recipient-scoped audit/request list and responses.
- `resources/views/auditee/evidence-response-form.blade.php`: reusable evidence-method/draft/file form.
- `resources/views/auditor/evidence-requests.blade.php`: minimal workspace integration for request, review and accepted-result sharing.
- `scripts/apply-auditee-evidence-index.php`: guarded backup-first additive index maintenance.
- This report.

Modified:

- `routes/web.php`: reuse existing Auditee paths with scoped controllers; add request/response/review/publication routes.
- `app/Http/Controllers/Web/AuditController.php`: mixed-type departmental auditee lookup and retain requested items; existing review/submission unchanged.
- `app/Http/Controllers/Api/V1/ArchiveController.php`: reuse uploader for authorized requests, fix corrective linking, validate locked status/departments and OOXML files.
- `app/Http/Controllers/Api/V1/ComplianceCheckController.php`: additive actionable filter for existing upload selection.
- `app/Http/Controllers/Api/V1/NotificationController.php`: unread/type filters and scoped related-check destinations.
- `app/Services/NotificationDispatchService.php` and `app/Models/Notification.php`: optional internal destinations.
- `app/Services/RoleScopeService.php`: normalize archive ownership and constrain auditee downloads to own department.
- `app/Models/ArchiveDocument.php`: optional audit/request metadata for existing private archive collection.
- `resources/views/auditee/dashboard.blade.php`, `my-findings.blade.php`, `finding-details.blade.php`, `upload.blade.php`, `archives.blade.php`, `notifications.blade.php`, `help.blade.php`: proposed layouts in the existing theme, accessible states/forms and relevant actions. New content saved as UTF-8.
- `resources/views/auditor/audit-workspace.blade.php`: include request panel; `check-details.blade.php`: show submitted corrective references for existing authorized CAR review.
- `resources/views/partials/sidebar.blade.php`: truthful labels, one Evidence Requests entry and one Provide Evidence entry, original paths retained.
- `resources/js/pages.js`: notification filters, actionable upload selection and contextual finding preselection.
- `public/css/auditor-ui.css`: scoped workflow cards/forms/feed/stacking styles, no reset/framework change.
- `database/mongodb/init.js`: reproducible auditee request lookup index.
- `tests/Feature/AuditWorkflowMongoTest.php`: expanded isolated database/storage setup and request, privacy, encryption, response and publication coverage.

## Main database operation

Applied only `audits_auditee_requests` with key `{departmentId:1,"evidenceRequests.auditeeId":1,updatedAt:-1}` for the new recipient-scoped multikey query. The four existing audit indexes and `_id_` were verified unchanged. Backup-first command:

`php scripts/apply-auditee-evidence-index.php`

Backup directory: `storage/app/private/database-backups/auditee-index-20261106-104526-e56266/` contains original Canonical Extended JSON, prior indexes and result metadata. Verification returned recordsUnchanged=true and existingIndexesPreserved=true. No live audit/request/response/test assignment was inserted. No membership change, reset or deletion occurred. `.env` checksum remains `8E5D78F0DB3EF5B1D2E0F7C52FFA9DBA5C1D0000B6E7A72956DDC2A23FB47A94`. Read-only main-database checks confirm CET and the existing notification indexes. Normal requests can use new embedded fields without changing environment configuration.

## Verification

- `php artisan test` baseline after initial integration: 15 passed / 33 assertions, opt-in MongoDB tests skipped. Tests were then expanded.
- `php vendor/phpunit/phpunit/phpunit -c phpunit.audit.xml`: first expanded run 22 tests / 297 assertions passed; draft/filter run 22 / 303 passed. **Final publication-inclusive result: 22 tests / 314 assertions passed**, exit 0, 83.326 seconds.
- In `realtime/`: `$env:RUN_MONGO_NOTIFICATION_TESTS='1'; npm.cmd test`: 11 passed, including actual isolated MongoDB and Socket.IO transport. `npm.cmd run check`: passed.
- In `mobile/`: `flutter.bat test`: 12 passed. `flutter.bat analyze`: no issues found.
- `php artisan view:cache`: passed. `npm.cmd run build`: passed, 1605 modules.
- PHP syntax checks for AuditeeController, AuditeeEvidenceService, ArchiveController: passed. `git diff --check`: no whitespace errors, CRLF normalization warnings only.
- `php scripts/inspect-notification-setup.php`: read-only main database check; CET retained, live audits still zero, original audit/notification indexes intact before the additional request index.

Integration tests exercise real application routes in generated MongoDB databases and fresh private storage directories with test encryption keys. They verify owned/other-recipient views, no private checklist text, draft persistence, replayed submission handling, response feedback, physical availability vs actual auditor signature, review-to-item linking, independent N/C/O, submission/acceptance, publication scope and immutability, page loads, notification filtering, private AES-encrypted file round trip and checksum, archive visibility, no CAR closure, malformed DOCX, oversized upload refusal and unauthorized downloads. Generated databases/storage are cleaned up with exact target/prefix guards.

## Manual checks, policy decisions and limits

No browser automation package is installed in the project; desktop/tablet/narrow layout is implemented with responsive styles but not visually verified. No manual logged-in browser/device acceptance or live audit assignment was created. Existing Node foreground transport was tested in isolation; new request notification persistence is covered by PHP tests.

An authorized Admin may deliberately create a real audit for manual acceptance. Auditor prepares items and requests evidence; addressed auditee saves/submits references/files/original availability; auditor reviews and records N/C/O; reviewer accepts/returns; assigned auditor explicitly shares permitted accepted summaries. Verify at desktop, tablet and narrow widths, mark-read navigation, archive download and forbidden cross-department access. No extra package or `.env` setup is required.

Institutional approval is still needed for actual permitted copies/metadata, official result-publication practice, reference links, retention/classification/filename policies, and any CAR escalation requirements. Conservative controls implemented here are application behavior, not a claim of official USTP QMS procedure. No new CAR is inferred from N or O. Public results are summaries addressed through requests; no general access to an entire accepted audit is granted. Existing legacy checks remain the established corrective-action records.

Scope is the web Auditee module; mobile new request forms are not added, but its existing workflows/regressions remain. Accepted shared results currently render separately from paginated legacy records. Request/response history is embedded and may need separate revision storage at very large scale. File creation and parent version save are multiple operations; a concurrent version conflict may leave an authorized private file in Archives without attaching a submitted response. Business writes/notification delivery retain the existing non-transactional notification architecture. These are not exactly-once/outbox guarantees. Closed-app OS push remains unsupported.
