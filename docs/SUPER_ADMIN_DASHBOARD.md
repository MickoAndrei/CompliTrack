# Super Admin dashboard and organization management

The existing Laravel / MongoDB / Blade / Alpine architecture supplies all displayed records. The navy, yellow, and white theme and the seven Super Admin destinations remain in place. Existing profile and shared-sidebar work from earlier requests is retained. No Admin/ATL, Auditor, or Auditee audit workflow was changed.

## Definitions and access

`GET /api/v1/system/dashboard` and `GET /api/v1/system/dashboard/health` require authenticated Super Admin role `0`, behind the existing active-account middleware. The dashboard service also checks its actor. Responses are not cached. Other roles cannot use these endpoints or the existing organizational assignment endpoints.

- Total Users counts registered MongoDB user records, including inactive accounts.
- CET Departments counts verified CET academic departments with approved names, including inactive departments. All five appear in the comparison. The Office of the Dean remains a college office; unclassified legacy units remain separate.
- Active Audits counts `audits` in `draft`, `ready`, `submitted`, or `returned`, rather than reusing CAR counts. Accepted audits are completed.
- Open CARs counts `compliance_checks` with `result=non_compliant` and `correctiveStatus` of `open` or `in_progress`. A checklist finding alone does not create a CAR for reporting purposes.
- Archived Documents counts records authorized by `QmsArchiveAccess`, including retained versions, physical originals, and digital references. Unverified ownership remains excluded.
- Archive Storage Usage sums file sizes on authorized file records, including retained versions, excluding physical originals and digital references. This is recorded file content size, not MongoDB database/index allocation or disk capacity. The UI chooses B, KB, MB, GB, or TB using powers of 1024.

Department comparison uses only checklist item results from accepted audits: **C / (C + N)**. O observations are displayed independently and do not enter that denominator. Empty, pending-only, draft, submitted, returned, and observations-only evaluations cannot fabricate 0% compliance. A department without applicable completed C/N items displays **Not Yet Assessed**; a completed N-only department legitimately displays 0%. Closing a CAR does not rewrite the original N evaluation. Legacy binary checks never enter this chart.

The dashboard keeps the cumulative six-month user growth display, adds the six latest audit record updates, and shows up to six open/in-progress CARs ordered overdue first, then earliest due. These are read-only summaries of records the Super Admin can access. Existing audit-editing restrictions are preserved. Refresh timestamps identify the snapshot, including when a later refresh fails.

User Growth Trend is presented as a responsive navy line graph with month labels, integer user-count ticks, and hover/keyboard point values. The six statistics reuse the existing `metric-stat-card`, `metric-label`, `metric-number`, and colored `metric-icon-box` styles from the dashboard stylesheet. The card layout adapts to screen width without changing any metrics or backend calculations. The graph and shared-card presentation passed the dashboard browser checks at all five supported test widths, plus the production build and Blade compilation.

Dashboard loading states use reusable layout-matched skeletons for metric values, five department rows, record totals, audit activity, CAR activity, the user graph, and four health rows. Content and empty states wait for their respective requests; health loading remains independent of the dashboard request. Decorative placeholders are hidden from assistive technology, with screen-reader status announcements and the existing busy attributes retained. Shimmer animation is disabled for reduced-motion preferences. Delayed-response browser checks cover initial skeletons, independent completion, replacement by actual data, retry, and reduced motion at all five widths. The reusable template is `resources/views/components/dashboard-skeleton.blade.php`; loading screenshots are saved beside the dashboard screenshots as `skeleton-1440.png` and `skeleton-390.png`.

Health reports actual Laravel response availability, MongoDB ping, and the existing Node `/health` response. Laravel calls the configured `NODE_SOCKET_URL` with bounded connection/request timeouts; Node is available only when its response confirms both service and database health. Failed checks show unavailable or unknown, rather than a hardcoded healthy state. Health checks do not restart services or change configuration.

### Integration of the old CompliTrack System Health panel

The old panel's service rows, colored status dots, refresh icon/spinner, updated timestamp, and 60-second automatic refresh are integrated into the current dashboard. The current Laravel check remains alongside **Node.js Service**, **MongoDB Cluster**, and **FCM Push Service**. Timers are cleared when the component is destroyed, and overlapping health requests are suppressed.

The existing `/api/v1/system/dashboard/health` endpoint now includes the old-style `components` list while retaining its original fields. The old `/api/v1/dashboard/health` URL maps to the same implementation and Super Admin authorization. Optional `REALTIME_HEALTH_URL` overrides the Node health URL; otherwise the existing `NODE_SOCKET_URL` is used. No environment-file changes are needed.

The current Node service uses persisted notification polling and does not configure Firebase. A successful health response without `pushConfigured: true` therefore displays **Not Configured** for FCM. An explicit true value displays **Configured**, which does not claim a successful push-delivery probe. When Node health cannot be verified, FCM displays **Unknown**. HTTP error responses and unrecognized payloads cannot mark Node operational.

Integration verification passed: **3 backend tests, 80 assertions**; dashboard browser checks at **1440, 1024, 768, 390, and 320 px**, including timer scheduling/cleanup and manual refresh; production build, Blade compilation, PHP syntax, routes, and whitespace checks. Live verification returned Laravel, Node, and MongoDB **Operational**, and FCM **Not Configured**. This follow-up introduces no database changes or Firebase dependencies.

## Organization and navigation

The Super Admin sidebar groups existing destinations under collapsible **Organization** and **Records & System** sections. The current page's section opens automatically; Dashboard shows both sections open. Other roles keep their existing navigation organization.

Departments retains its existing CRUD controls and adds an assignment summary for Department Heads, Department Secretaries, CET Dean, and College Secretary. Departments and Users link to `/super-admin/documents?manage=responsibilities`, which opens the existing archive responsibility dialog for authorized Super Admins. No duplicate organizational editor, authentication role, or archive permission system was introduced. Existing explicit unit/college scope, auditor evidence-sharing restrictions, document permissions, and audit logging still apply.

Resource lists and QMS Archives share the QMS-style Previous/Next controls through `resources/views/components/pagination-controls.blade.php` and `public/css/pagination.css`. Existing resource-list navigation behavior is retained. QMS now disables both controls during requests, commits page numbers only after successful responses, honors the server's current page, retains its records/page on failure, and preserves active organizational/search/type filters. Single-page controls show Page 1 of 1 with disabled buttons. Pagination and archive regressions passed at 1440, 768, 390, and 320 px; the build, Blade compilation, dashboard, organization, and profile browser checks also passed.

## Database verification

No migration, seeding, reassignment, or production-data update is required or performed by this implementation. The existing database already contains exactly five CET academic departments and one college office. The read-only verification command is:

```powershell
php scripts/inspect-super-admin-dashboard.php
php scripts/verify-qms-schema.php
```

The checked development snapshot contains 30 users, 9 active audits, 7 open/in-progress CARs, 55 authorized archive records, and 2,014,103 file bytes (about 1.92 MB). These are observed records, not constants in application code. Existing mock provenance remains visible. Three departments are not yet assessed; FPT and IT each have 50% among completed C/N evaluations. All three services reported available when checked.

Eight legacy archives and their two unclassified organizational units still await an approved ownership mapping. Their original records and relationships remain intact, and their files remain excluded from authorized archive totals. This task does not infer mappings or convert those units into extra CET departments.

## Modified files for this request

- Backend: `app/Services/SuperAdminDashboard.php`, `app/Http/Controllers/Api/V1/SuperAdminDashboardController.php`, `app/Http/Controllers/Api/V1/QmsArchiveController.php`, `config/services.php`, `routes/api.php`.
- Frontend: `resources/views/super-admin/dashboard.blade.php`, `departments.blade.php`, `users.blade.php`; `resources/views/partials/sidebar.blade.php`; `resources/js/super-admin-dashboard.js`, `app.js`, `qms-archives.js`; `public/css/super-admin-dashboard.css`, plus Super Admin disclosure styles in `public/css/sidebar.css`.
- Verification: `scripts/inspect-super-admin-dashboard.php`; `tests/Feature/SuperAdminDashboardMongoTest.php`; `tests/Fixtures/super-admin-dashboard.php`; `tests/Browser/super-admin-dashboard.cjs`.
- Documentation: `README.md` and this report.

Earlier workspace changes to the profile, common layout, and shared sidebar are preserved. Build bundles and screenshots remain generated local artifacts.

## Validation

The dashboard integration tests use random isolated MongoDB databases with guarded cleanup. They exercise completed/draft/observation-only/zero-result calculations, actual CAR state changes, archive authorization and byte totals, role denial, all seven existing Super Admin pages, and real MongoDB plus simulated Node health outcomes. Browser tests render production templates with isolated API fixtures; no fixtures are inserted into the main database. They cover six statistics, five department rows, KB/MB formatting, 0% versus unassessed, health errors/retry, collapsible navigation, organization summaries, and navigation into the existing responsibility dialog at 1440, 1024, 768, 390, and 320 pixels.

Commands:

```powershell
php vendor/phpunit/phpunit/phpunit -c phpunit.audit.xml
node tests/Browser/super-admin-dashboard.cjs
node tests/Browser/qms-archives.cjs
npm.cmd run build
php artisan view:cache
php artisan route:list --path=system/dashboard
git diff --check
cd realtime
npm.cmd test
```

Vite retains its existing main-bundle warning above 500 kB. The optional realtime MongoDB integration test is opt-in; the regular realtime suite and live service health are checked separately.

Final results:

- New dashboard integration tests: **3 passed, 68 assertions**, including the final CAR-closure regression.
- Full backend regression run: **52 tests, 964 assertions**, with **51 passing and one MongoDB socket timeout** in the existing backup-first migration test while creating indexes in its isolated test database. Its isolated retry **passed with 35 assertions**. No application assertion failed in that run; the initial full run is not represented as an uninterrupted pass.
- Dashboard browser checks passed at **1440, 1024, 768, 390, and 320 px**; existing archive browser regressions passed at **1440, 768, 390, and 320 px**. No JavaScript exceptions or horizontal overflow were found.
- Realtime tests: **13 passed, 1 optional MongoDB integration test skipped**. Live Node health, including its database check, returned available.
- Final production build, Blade compilation, PHP syntax checks, route registration, and whitespace checks passed. Vite reports the existing approximately 615 kB main JavaScript bundle warning.
- Read-only main-database schema verification confirmed the five academic departments, separate college office, preservation of original records/relationships, and continued isolation of eight legacy archives.

Dashboard screenshots are generated under `storage/framework/testing/super-admin-browser/dashboard-1440.png` and `dashboard-390.png`. No new credentials, production demo records, indexes, collections, or schema migrations were introduced.
