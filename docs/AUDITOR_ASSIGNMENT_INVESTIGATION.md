# Auditor assignment investigation

Read README.md completely before inspecting code. Its scaffold descriptions and collection list are outdated relative to the current implementation.

## Root cause and database findings

Read-only inspection of the configured MongoDB found **zero documents in `audits`**, including zero assignments for `[DEMO] IT Auditor` using either ObjectId or string auditor IDs. The empty list is therefore accurate, not caused by date/status filtering. `DemoDataSeeder` creates department membership, users, standalone compliance checks and notifications; it does not create audit assignments. A department assignment or standalone check is not an audit assignment.

The demo auditor is active, roleId 2, primary key `6aeac5b88ce89fe741003b3b`; its department and assigned department are `6aeac5b68ce89fe741003b38` (`[DEMO] Information Technology`, active). This was a server-side inspection of that account, not direct inspection of the browser's login cookie.

That department currently has collegeName `Collage of Engineering and Technology`, whereas the existing Admin scope requires the exact value `CET`. Thus it is outside the existing Admin assignment scope. No live department data was changed and no alternative college labels were automatically authorized.

## Confirmed bugs fixed

1. `Audit` casts auditorId/reviewerId/departmentId to BSON ObjectId on storage, but User::getKey() returns a string. The installed driver does not convert arbitrary foreign-key query values. Assignment retrieval now explicitly parses auditor/reviewer IDs, retaining ownership and department checks.
2. Eloquent `pluck('_id')` returned null for departments with the installed MongoDB integration. This caused Admin assignment creation to fail during scope calculation. CET scope and reviewer assignment queries now fetch department models and use getKey(), then convert to ObjectId. The exact CET restriction is unchanged.

The list query checks active user, role 1/2, auditor ownership plus assigned departments, or reviewer ownership plus CET departments. It has no status or date predicate. Pagination is 20 records, newest first. Workspace reads and writes reuse the same query.

## Existing workflow and data map

- MongoDB `audits`: `App\Models\Audit`; audit header/assignment, embedded checklist, evidence reviews, findings, interviews, and history. No separate audit-plan or assignment table/model exists.
- MongoDB `users` and `departments`: active auditor, roleId, department membership, assignedDepartmentIds, college scope.
- MongoDB `compliance_checks`: standalone checks and CAR lifecycle, separate from audit assignments.
- MongoDB `audit_logs` records authorized assignment creation and changes; `counters` generates IQA references; `notifications` carries review notifications.
- SQLite contains framework support tables, not audit assignments.
- `App\Http\Controllers\Web\AuditController`: index/store/show/update and dashboard metrics.
- Authenticated role 1/2 routes: GET `/audits` lists; POST `/audits` creates (active Admin only); GET `/audits/{id}` opens workspace; POST `/audits/{id}` saves authorized workspace actions.
- GET `/api/v1/auditor/metrics` supplies dashboard metrics, not an assignment list. Assignment pages are server-rendered Blade and have no frontend list API to fail silently.
- The three dashboard stage links intentionally point to the same `/audits` page. They do not pass stage filters; this is not three separate assignment workflows. They were left unchanged.

## Creating a valid test assignment through the existing UI

Use a development/test environment and an authorized active CET Admin. Open **Audit Assignments** (`/audits`), select an active department whose collegeName is `CET`, select an active Auditor already assigned to that department, and provide title, schedule, scope, and criteria. Submit **Assign audit**. The Admin becomes reviewer, and the assignment starts as draft. Sign in as that assigned auditor, open **My Assigned Audits**, then select the assignment to open its existing Audit Workspace.

To use the current demo IT department specifically, an authorized administrator must first confirm its correct college membership and update the department through existing department management if it belongs to CET. Do not change a college value solely to bypass scope. No arbitrary assignment was inserted in the configured database. All test fixture writes used a new randomly named test database and the existing POST assignment route; the test database was removed afterward.

## Files changed for this investigation

- `app/Http/Controllers/Web/AuditController.php`: normalize stored foreign-key types for assignment retrieval; use actual department model keys for reviewer scope.
- `app/Services/RoleScopeService.php`: fix CET department ID projection without changing permitted colleges.
- `resources/views/auditor/audits.blade.php`: centered, explanatory empty state.
- `public/css/auditor-ui.css`: empty-state icon, spacing and typography.
- `scripts/inspect-audit-assignments.php` (new): read-only, selected-field diagnostic of the demo account, assignment counts, rendered list and metrics response. Does not print credentials or evidence.
- `tests/Feature/AuditWorkflowMongoTest.php` (new): opt-in regression test using a fresh isolated MongoDB database and guarded cleanup.
- This investigation report.

## Verification and limitations

- `php artisan route:list --path=audits`: all four existing routes registered.
- `php artisan test`: 10 passed (18 assertions); opt-in MongoDB test skipped by default.
- `$env:RUN_MONGODB_AUDIT_TESTS='1'; php artisan test --filter=AuditWorkflowMongoTest`: final run passed, 23 assertions. Covers authorized POST creation, ObjectId storage, reviewer list, assigned auditor list, workspace HTTP 200, visibility with a past date and ready/submitted/returned/accepted statuses, other-auditor denial, revoked department denial, and malformed ID validation. Test database cleanup runs in teardown.
- Integration testing initially found the department projection bug; an initial test database name also exceeded the connected service's length limit and was shortened. A malformed web ID redirects with validation errors rather than JSON 422; the test now checks the actual web contract.
- `php scripts/inspect-audit-assignments.php`: read-only configured-database inspection confirms zero audits, a rendered empty list, and metrics HTTP status 200 when invoked with the demo user. Direct controller invocation is not browser/network authentication testing.
- Existing backend logs contained historical MongoDB DNS/connection failures; the current diagnostic connected successfully. One diagnostic render initially lacked middleware's validation-error bag; the script now supplies it. This was a console diagnostic setup issue, not a page defect.
- No direct browser session/DevTools response inspection was available. Server-rendered HTTP routes were exercised by the integration test. Existing checklist validation and role regression tests pass; evidence/CAR workflows were not rewritten.
