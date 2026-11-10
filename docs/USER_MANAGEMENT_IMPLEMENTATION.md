# Super Admin User Management

Implemented on November 10, 2026. The existing Users controller, MongoDB `users`
collection, `resourcePage` component, primary roles, password-reset service, and
authorization services remain the account-management system.

## Interface

- The existing page header contains the User Management title and description;
  the repeated title and description above the cards were removed.
- Compact Total Users, Active Users, Auditors, and Auditees cards use database
  counts for the Super Admin's full user scope, independently of list filters.
- The list emphasizes full name, with the existing user code beneath it, and
  displays email, primary role, organizational responsibility, assigned
  organization, status, and a three-dot actions menu.
- Name/email/code search and role, department/office, responsibility, and status
  filters run on the server. Pagination remains server-side. Stale search
  responses cannot replace newer results.
- Native dialogs provide keyboard focus containment, Escape dismissal, and
  Create/Edit sections for Account Information, Organizational Assignment, and
  Security and Permissions. Requests show saving states and validation errors.
- View User Details shows stored account timestamps, primary role, responsibility,
  organizational names, archive scope/counts from the existing access service,
  explicitly assigned audits, ATL review counts, and up to 20 available audit-log
  entries. Missing activity and timestamps are explicitly reported as unavailable.
  The activity list scrolls with a hidden scrollbar and accepts keyboard focus.
- Action menus support keyboard navigation. Reset, deactivation, and reactivation
  require confirmation. Password reset emails use the existing service.
- Skeletons cover loading; error/retry and empty states are distinct. Desktop uses
  a table, tablet contains table scrolling, and mobile uses labeled account rows.
  Navy, yellow, and white styling and the existing Lucide icons are retained.

## Backend behavior and preservation

The existing `GET /api/v1/users` accepts the new filters. With `management=1`,
which is restricted to Super Admin, it also returns counts and organizational
options. `GET /api/v1/users/{id}` returns additional access/activity information
to Super Admin only. Existing create, patch, deactivate, reset-password, and
reactivate endpoints are reused.

`UserOrganization` validates the existing `archiveResponsibility`,
`archiveUnitIds`, and `archiveCollegeIds` fields for both the Users endpoint and
the existing archive responsibility editor. Validation completes before saving
account edits, so an invalid scope does not partially save account information.

- Department Heads and Department Secretaries require exactly one active,
  verified academic department matching the authorized CET code/name mapping.
- College Dean and College Secretary responsibilities require exactly one active
  CET college and no organizational unit IDs. New college-level accounts do not
  need a home department; a retained home office must belong to that college.
- Faculty/staff can belong to a verified office without becoming college officers.
- Auditor organizational responsibilities are rejected. Existing audit and archive
  access services continue to require explicit audit assignment and evidence
  sharing; department membership does not grant unassigned audit access.
- Admins cannot escalate primary roles or assign organizational responsibilities.
- Self-deactivation is rejected. The existing stronger policy protects **all**
  Super Admin accounts from deactivation/demotion, protecting the last active
  Super Admin without a count/check race.
- Existing Admin accounts referenced as Audit Team Leaders cannot be deactivated,
  demoted, or have their home/eligible-department assignments changed through
  User Management.
- Direct PATCH activation cannot bypass the inactive-department check used by
  reactivation.
- Listing accounts no longer deactivates users as a side effect of a GET request.
- New credentials are randomly generated and hashed through the existing User
  model's configured password policy. Reset links do not change credentials until
  successfully used, and existing password validation and expiry rules remain.
- Edits send changed fields only. Unchanged legacy organizational relationships,
  BSON references, user codes, passwords, and role values are retained. Legacy
  assignments are not silently reclassified.

## Database changes

**No migration, index change, collection replacement, bulk update, reseed, or
credential change was applied to the configured `complitrack` database.**

A read-only inspection found 30 users (2 Super Admins, 1 Admin, 7 Auditors,
20 Auditees), four primary roles, five verified CET academic departments, the
Dean's office, and two unclassified legacy units. No account identities, password
hashes, or tokens are printed by the inspection script.

Existing fields and collections are reused. `officeName` and `roleSlug`, already
written by the controller, are now included in the User model's fillable fields.
The existing `lastLoginAt` timestamp is cast as a datetime for reliable display.
Integration tests create and remove only their randomly named test databases;
emails are faked. Browser account writes are in-memory API fixtures.

## Modified and added files for this request

| File | Purpose |
| --- | --- |
| `app/Http/Controllers/Api/V1/UserController.php` | Filters, counts, organization updates, account safeguards, serialization, preservation |
| `app/Http/Controllers/Api/V1/QmsArchiveController.php` | Shared organizational validation for the existing editor |
| `app/Models/User.php` | Persist existing officeName/roleSlug fields; format the existing login timestamp |
| `app/Services/UserOrganization.php` | Shared organizational assignment validation |
| `app/Services/UserManagementPresenter.php` | Organizational options and actual access/activity details |
| `resources/js/pages.js` | Connect the enhancement to the existing resourcePage |
| `resources/js/user-management.js` | User list, filters, dialogs, actions, request ordering, changed-field edits |
| `resources/views/components/resource-page.blade.php` | Opt-in enhanced User Management rendering |
| `resources/views/super-admin/users.blade.php` | Existing route's page and single header |
| `resources/views/super-admin/partials/user-management.blade.php` | Cards, list, filters, menus, dialogs |
| `public/css/user-management.css` | Scoped responsive styling |
| `tests/Fixtures/super-admin-dashboard.php` | Render the Users page without database writes |
| `tests/Feature/UserManagementMongoTest.php` | Isolated real MongoDB API and authorization coverage |
| `tests/Browser/user-management.cjs` | Browser acceptance checks with mocked APIs |
| `scripts/inspect-user-management.php` | Read-only schema/count inspection |
| `README.md` | Link to this report |
| `docs/USER_MANAGEMENT_IMPLEMENTATION.md` | Implementation and validation report |

Earlier workspace changes to startup, DNS-related diagnostics, navigation,
dashboard lists, department skeletons, and unrelated untracked files were retained.

## Validation

- Full backend regression: **64 tests, 1,117 assertions passed**.
- Final targeted Users/archive validation: **18 tests, 367 assertions passed**,
  including completed reset, one-use/expired tokens, office membership, and the
  final organizational validation. Results are recorded in
  `storage/framework/testing/user-management-final.xml`.
- Browser acceptance: **1440, 1024, 768, 390, and 320px passed**, including search,
  every filter, pagination, skeletons, escaped user text, keyboard action menus,
  details, responsive editors, create/edit, field errors, reset confirmation,
  deactivation/reactivation, and failed-load retry.
- Existing Super Admin browser regression passed for dashboards, department
  skeletons, archive responsibility navigation, and profile dialogs.
- Production frontend build, Blade compilation, and whitespace checks passed.
- Browser screenshots are saved under
  `storage/framework/testing/super-admin-browser/users-1440.png` and
  `users-390.png`.

## Remaining limitations

- Real outbound mail delivery was not exercised; tests fake mail and verify the
  existing reset-token flow. Delivery depends on the deployment's mail settings.
- The two legacy units remain unclassified. New organizational assignments must
  pass the authorized scope validation; no ownership migration was performed.
- Vite still reports its existing main-bundle-size warning.
- Qodo review was unavailable: no connected Qodo tools or installed native CLI
  were found. Local source review and automated tests were completed instead.
