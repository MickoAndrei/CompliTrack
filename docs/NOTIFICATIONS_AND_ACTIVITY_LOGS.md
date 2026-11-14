# Notifications and System Activity Logs

The notification page places its title and role-specific description in the portal
header. Search shares one toolbar row with Mark all read and the authorized Broadcast
action on desktop, stacking responsively on mobile. All/Unread/Read filters, an unread count, and readable cards provide
a title, message excerpt, category, timestamp, read state, and View details action.
The existing category filter and authorized department broadcast remain available.
All four portals use the same Blade inbox and existing Alpine `resourcePage`
controller. The current navy/gold portal styling is retained.

Notifications and historical activity now have separate Super Admin navigation
entries. The bell reads only the signed-in user's notifications; it no longer
requests `/api/v1/logs`, invents unread states for audit history, or stores local
read/dismissed audit-log IDs.

## Data and authorization

- The existing MongoDB `notifications` and `audit_logs` collections are reused.
  No notification records are migrated, replaced, or deleted.
- Inbox filtering, search, pagination, and unread totals run on the server through
  `RoleScopeService::notifications`. Mark Read and Mark All Read retain their
  existing PATCH endpoints and recipient boundary. Repeated Mark Read requests
  do not generate additional read events once the notification is already read.
- Notification details use `GET /api/v1/notifications/{id}`. Related-record actions
  use `GET /api/v1/notifications/{id}/open`: ownership is checked before resolving
  a local, role-appropriate destination. Audit assignments and compliance-check
  scopes are rechecked before redirecting; destination controllers enforce their
  usual permissions too. External and malformed destinations are rejected.
- System Activity Logs retain the Super Admin-only route and backend scope.
  `GET /api/v1/logs/{id}` powers a large native, read-only details dialog. No log
  update or delete endpoints are added. Log output is an explicit allowlist of
  audit metadata, excluding request payloads, headers, file contents, and secrets.
- Event category, actor name/ID, date range, outcome, search, sorting, and pagination
  are server-side. Inclusive end-date behavior and the existing CSV export remain.
  Summary counts reflect authorized records matching the current filters.
- New audit events store category and outcome. Legacy records are categorized at
  query/read time without altering history; legacy events without an outcome are
  displayed as Success, consistent with the previous successful-operation logger.
  Existing account, organization, QMS, audit, and permission logging is reused.
  Sign-in/sign-out/password events and rejected authentication/permission/rate-limit
  requests now use that same service. Failed credential attempts do not log the
  supplied email, password, token, or request body.
- The existing Firebase-free Node.js polling leases and Socket.IO authenticated
  recipient rooms are unchanged. The web inbox retains authenticated HTTP refresh
  on focus and every ten seconds. Stale responses are ignored and repeated IDs are
  displayed once; pending detail responses cannot restore a closed dialog.

## Query indexes

`database/mongodb/init.js` includes additive query indexes for the all-status inbox
and activity history. Existing deployments can run:

```powershell
php scripts/apply-attention-indexes.php
```

The script adds `notifications_recipient_created_id`, `audit_logs_created`, and
`audit_logs_category_outcome_created`. It does not alter records or remove indexes.
The inbox index avoids sorting all recipient records through the existing
read-status index. Log indexes support newest-first browsing and category/outcome
filters. Legacy category expressions and case-insensitive name/search filters can
still require scanning matching records; existing history is not rewritten to
optimize those filters.

## Verification

```powershell
php artisan test --compact
php vendor/phpunit/phpunit/phpunit -c phpunit.audit.xml
node --test tests/Unit/*.test.cjs
npm.cmd run build
node tests/Browser/attention.cjs
$env:RUN_MONGO_NOTIFICATION_TESTS='1'
npm.cmd --prefix realtime test
```

MongoDB integration tests create random temporary databases and remove only their
own databases. The default PHPUnit configuration also selects a test database to
prevent accidental writes to application data. Browser fixtures render production
Blade templates without database writes and use local API fixtures. Browser checks
cover all four role inboxes at 1440, 390, and 320 pixels; filters, read actions,
details, loading/error/empty states, log separation, and responsive read-only log
dialogs. Screenshots are saved under `storage/framework/testing/attention-browser`.

Qodo's independent review requires a signed-in CLI; it was unavailable in this
session. Local regression and authorization tests provide the recorded validation.

Recorded results: the full PHP/MongoDB regression run passed 130 tests with 1,886
assertions. The final targeted notification/security/index run passed seven tests
with 114 assertions, including the subsequently added index-installer test. All 30
frontend unit tests, all 16 realtime tests (including real MongoDB delivery), the
production build, PHP formatting checks, and the final responsive browser checks
passed. The requested Admin dashboard copy change also passed the existing Admin
portal regression checks. The additive index installer was verified in temporary
test databases; applying it to the deployment database remains a deployment step.

## Changed files

- Shared UI: `resources/views/components/notification-inbox.blade.php`,
  `activity-logs.blade.php`, `partials/attention-pagination.blade.php`,
  `partials/notification-broadcast.blade.php`, and `notification-dropdown.blade.php`.
- Portal views: the four role `notifications.blade.php` views,
  `resources/views/super-admin/logs.blade.php`, `partials/navbar.blade.php`, and
  `partials/sidebar.blade.php`. The requested Admin dashboard copy change moves
  its sentence into the header subtitle in `admin/dashboard.blade.php` and removes
  the duplicate content line from `admin/atl-dashboard.blade.php`.
- Frontend: `resources/js/pages.js`, `resources/js/icons.js`,
  `resources/css/attention.css`, and its import in `resources/css/app.css`.
- Backend: `app/Http/Controllers/Api/V1/NotificationController.php`,
  `AuditLogController.php`, `AuthController.php`, and `UserController.php`;
  `app/Http/Controllers/Web/AuthController.php`; `app/Services/AuditLogService.php`;
  `app/Support/AuditLogFields.php`; `app/Models/AuditLog.php`;
  `app/Http/Middleware/AuditLogMiddleware.php`; `bootstrap/app.php`; and both route files.
- Index definitions/installer: `database/mongodb/init.js` and
  `scripts/apply-attention-indexes.php`.
- Verification: `tests/Feature/NotificationActivityMongoTest.php`,
  `AdminPortalViewTest.php`, `AuditAccessTest.php`, `ProfileTest.php`,
  `tests/Unit/attention.test.cjs`, `notification-badge.test.cjs`,
  `AuditLogDateRangeTest.php`, `tests/Fixtures/attention.php`,
  `tests/Browser/attention.cjs`, and the isolated test database in `phpunit.xml`.
- Documentation: `README.md`, `DOCUMENTATION.md`, and this implementation report.

The earlier browser-tab session changes in the working tree are retained.
