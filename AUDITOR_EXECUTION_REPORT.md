# Auditor execution report

## Scope, baseline, and architecture

The entire README was read before source inspection. Actual stack: Laravel 12 / PHP 8.2, MongoDB application models, SQLite framework support, Blade / Alpine / Tailwind / Vite, Sanctum and JWT, Node realtime service, and Flutter clients. The README's scaffold status is outdated. No new dependencies or parallel audit module were introduced. Existing uncommitted work was preserved.

Before edits: `php artisan test` passed 10 tests / 18 assertions, with the opt-in MongoDB test skipped. `npm.cmd run build` passed. The existing implementation already supplied CET Admin assignment creation, assigned-auditor/reviewer scoping, draft item CRUD/order, ready-state question locking, evidence review records, N/C/O, findings, interviews, submission, and reviewer accept/return.

## Changes by phase

Assignment integrity:

- `app/Http/Controllers/Web/AuditController.php`: preserve explicit ObjectId assignment/reviewer comparisons and model-key CET projection. Require active departments. Reject exact repeat submissions with a stable assignment fingerprint and a validation message; handle unique-key races when the documented index is installed. Distinct audits for an auditor remain allowed. No college/department membership was changed.
- Existing draft → ready → submitted → accepted / returned lifecycle retained. There is no separate audit plan entity or assignment join table: `audits` owns the header and assignment.

Checklist and evidence:

- Same controller: require a saved evidence review before selecting N/C/O; uploads remain optional. Physical-original signature outcomes now include verified, missing/incomplete, not yet verified, and not required, independently of N/C/O. Signature selection is required for physical-original reviews and removed for other methods.
- `resources/views/auditor/audit-workspace.blade.php`: add the missing signature outcome without redesigning the form. Existing fields, private archives, optional interviews, and finalized-record locks remain.
- Findings remain recorded descriptions on their stable checklist items, sharing requirement references and evidence. N results do not create CARs. N findings require descriptions at submission; O does not create CARs. No personal scoring introduced.

Dashboard integration:

- Same controller: return the latest three real assignments, evaluation progress, explicit observation count, and authorized audit-history activity alongside existing CAR activity. Metrics remain scoped to the actor and current assigned departments.
- `resources/js/auditor-overview.js`: consume assignment previews.
- `resources/views/auditor/dashboard.blade.php`: display actual assignments or a genuine empty state; activity links open the appropriate audit/check workspace. Replace static stage labels with truthful links to the existing assignments, checks, and corrective-action screens. Preserve compact six-card layout, icons, N/C/O donut, colors, sidebar, and header.
- `public/css/auditor-ui.css`: small styling addition for assignment preview rows; no broad redesign.

Compatibility fixes uncovered by regression tests:

- `app/Services/RoleScopeService.php`: normalize auditor/auditee foreign keys for legacy checks and recipient IDs for notifications to ObjectId. Ownership restrictions remain intact; previously stored BSON references now match string authenticated keys correctly.
- `app/Services/ComplianceCheckService.php`: normalize the raw MongoDB `_id` filter used by atomic CAR status transitions. Without this, a valid transition returned 409 because it compared a string with stored BSON `_id`. Verification remarks and the existing authorized transition sequence remain required.

Tests and setup files:

- `phpunit.audit.xml` (new): dedicated test configuration, forces a safe bootstrap database name and enables opt-in integration tests. Does not edit `.env`.
- `tests/Feature/AuditWorkflowMongoTest.php`: each test switches the MongoDB connection to a newly generated `complitrack_audit_test_*` database before fixture writes, asserts the selected name, installs the assignment uniqueness index there, and cleans up only its exact generated database. No test seeds/resets/drops `complitrack`.
- `database/mongodb/audit-indexes.js` (new): additive audit indexes only, targets the database explicitly selected in Compass/mongosh.
- `database/mongodb/init.js`: includes the sparse unique assignment fingerprint index for new environments. The full init script was not run against the main database.

## Metrics and data definitions

Total Checks counts saved standalone compliant/non_compliant evaluations plus assigned workspace N/C/O evaluations. Compliant counts standalone compliant plus workspace C. Pending counts unanswered workspace items. N/C/O donut excludes unanswered items; O is the difference between all evaluated items and C+N. Nonconformities counts legacy non_compliant findings plus workspace N items with recorded nonblank finding descriptions, once per item. An N with no finding does not increment that card. Open CARs counts existing open/in_progress records; Closed CARs counts existing closed records, not inferred verification of historic data. No sample counts were inserted.

## Database safety and setup for normal application use

No main-database records were created, changed, seeded, reset, deleted, or replaced during this execution. `.env` SHA-256 remained identical before and after edits. Tests reuse connection credentials but switch database names in process through the dedicated test configuration; normal application requests continue to use the unchanged `.env` configuration.

No relational migration or record backfill is required. Existing audit documents continue to work; new documents receive `assignmentKey`. Existing documents without it are permitted by the sparse index. The duplicate check cannot detect an exact older assignment lacking a fingerprint; do not backfill blindly.

To apply the optional main-database indexes safely:

1. Take your normal backup/snapshot and select the intended `complitrack` database in Compass or mongosh.
2. Review `database/mongodb/audit-indexes.js`. It only creates indexes on `audits` and does not edit documents or drop collections/indexes.
3. Check existing auditCode and non-null assignmentKey values for duplicates. If duplicates exist, stop and resolve through authorized review; do not delete records to force index creation.
4. Execute that small script against the selected database. Repeating identical createIndex calls is safe. A duplicate or incompatible existing index causes an error; do not use dropIndex or destructive migration as a workaround.
5. Run `npm.cmd run build` when serving production assets. Refresh/restart the normal Laravel process as needed. No `.env` database change is needed.

The live demo department's previously verified college label differs from exact `CET`. It was not corrected. An authorized administrator must verify the real organizational membership, active department and auditor, and assignedDepartmentIds before intentionally creating an assignment through the existing CET Admin form. Do not replace the label solely to bypass scope. The workflow can be verified automatically using CET fixtures without changing that membership.

## Verification

Commands:

- Baseline `php artisan test`: 10 passed / 18 assertions, 1 opt-in test skipped.
- Baseline and final `npm.cmd run build`: passed, Vite transformed 1605 modules.
- `php artisan view:cache`: passed.
- `php -l app/Http/Controllers/Web/AuditController.php`: passed.
- `git diff --check`: no whitespace errors; Git reports an existing CRLF normalization warning for the dashboard.
- Use `php vendor/phpunit/phpunit/phpunit -c phpunit.audit.xml` for the isolated full suite. `php artisan test --configuration=phpunit.audit.xml` produced a duplicate-configuration warning from the Artisan wrapper, so the direct PHPUnit entry point is used instead.

Coverage includes authorized POST assignment creation; exact duplicates; proper ObjectId storage; Admin department projection; department membership without an audit giving an empty list; reviewer/auditor list and workspace access; past dates/all existing statuses; unassigned/revoked access denial; draft add/edit/delete/reorder and refresh; multiple digital/physical/restricted evidence reviews without uploads; saved remarks and signature state; C/N/O counts; finding/CAR separation; incomplete-submission refusal; submitted immutability; return/resubmission/acceptance and notification retrieval; stale-version conflicts; OFI refusal; legacy check/archive list access; CAR verification and closure; private archive download denial; interview persistence; and existing Auditor page rendering.

An intermediate test run passed the core workflow but found the legacy CAR raw-ID conflict, which was fixed. Another assertion needed an evidence review before testing invalid N/C/O input; the fixture was updated to follow the intended workflow. **Final isolated full-suite result: 14 tests passed, 171 assertions, exit code 0 (39.469 seconds).**

## Gates and remaining acceptance work

Gates 1–8 are validated to the extent of the isolated automated workflow: baseline, authorized creation/list/open, checklist persistence, evidence references/signatures, N/C/O metrics, submission/review, and real dashboard integration. Main-database manual acceptance remains pending confirmed organizational membership and a deliberately created authorized audit. No browser end-to-end pass is claimed.

| Gate | Status | Evidence |
| --- | --- | --- |
| 1 Baseline and authorization | Passed | Standard tests/build, role denial, ID normalization regression |
| 2 Admin assignment integration | Passed in isolation | Authorized POST, duplicate rejection, wrong-college denial; live membership unchanged |
| 3 Assigned workspace | Passed | List/open, alternate auditor denial, revoked department denial |
| 4 Checklist preparation | Passed | Draft CRUD/reordering/persistence, prepared question locks |
| 5 Evidence review | Passed for reference workflow | Digital/physical/restricted records persist without uploads; private download denied |
| 6 N/C/O | Passed | C/N/O = 1/1/1, total 3, pending 0, finding count 1, CAR count 0 |
| 7 Submission/review | Passed | Incomplete rejection, finalized locks, return/resubmit/accept, notifications |
| 8 Dashboard data | Passed | Scoped metrics, real assignment preview and history activity payload |

For manual acceptance: authorized CET Admin creates an assignment; auditor opens it, prepares three questions, records permitted evidence and C/N/O with remarks, saves/refreshes, confirms counts against any existing legacy records, submits, and reviewer returns or accepts. Verify an unassigned account cannot open it. Capture only application UI, never protected QMS documents.

Limits: automated archive coverage checks scope/list/unauthorized download, not a new encryption/upload round trip. No Flutter workspace implementation or realtime push guarantee was introduced. Logs/notifications are written after the atomic audit update and are not protected by a transactional outbox. Embedded history is suitable for the current bounded checklists but very large revision histories may eventually require separate revision storage. No official university policy, independent finding approval lifecycle, or controlled form reproduction is asserted.
