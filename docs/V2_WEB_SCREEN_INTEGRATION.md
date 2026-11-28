# CompliTrack V2 — Web screen integration patch

This is an incremental Laravel source patch based on the V2 Figma web design, not a claim of complete design parity. It targets the previously delivered CompliTrack-V2.zip. It leaves the existing environment, database, archive files, dependencies, mobile source and original documentation unchanged.

Design reference: https://www.figma.com/design/etHw0CB49ZW6n2XTzHSl7N/CompliTrack-V2-UI-Redesign

## Page integration

| Figma reference | Laravel implementation | Coverage |
|---|---|---|
| 4:227 | auth/login.blade.php | Split sign-in page with existing authentication |
| 4:264, 4:694, 6:1187, 6:1043 | Four role dashboard views, components/v2/overview.blade.php | Rebuilt page layouts with dynamic role-scoped metrics, charts, cycle selection and action links; previous detailed dashboards remain accessible |
| 4:480 | super-admin/organizations.blade.php | Read-only university hierarchy and department navigation; campus/college creation and editing not implemented |
| 6:1333 | super-admin/users.blade.php and user-management partial | Compact user directory with real management actions |
| 13:5263 | super-admin/departments.blade.php; shared resource page | Partial adaptation of existing directory |
| 13:5100 | super-admin/roles.blade.php | Role cards, workflow permissions and decision rules |
| 8:1543 | super-admin/logs.blade.php; activity-logs component | Compact activity register |
| 8:1701 | super-admin/settings.blade.php | Settings cards; existing permission checks and confirmations retained |
| 8:1165, 8:2753 | audit-plans/index.blade.php | Plan register; existing draft and approval operations |
| 4:1275 | auditor/audits.blade.php; assigned-audit partials | Assignment table and next task |
| 4:1083 | admin/audit-reviews.blade.php | Review register and report detail/history using current records |
| 8:4636 | auditee/my-findings.blade.php | Findings register and existing authorized detail actions |
| 8:2901 | admin/corrective-monitor.blade.php | CAR register and real detail link |
| 13:6589 | admin/reports.blade.php | Annual report overview and department table with existing filters and exports |
| 9:4579 | components/qms-archives.blade.php | Shared QMS browser layout across roles; individual folder/viewer designs not fully compared |
| 13:5796 | components/notification-inbox.blade.php | Shared notification cards and authorized clear/read controls |
| 9:4275 | auth/profile.blade.php | Account and security panels |
| 13:6106 | help/index.blade.php | Partial adaptation of existing help content |
| 8:6183 | auditor/audit-workspace.blade.php | Partial adaptation; existing checklist and evidence workflow remains |

Other existing role screens also receive consistent shared styling, headings and pagination. These changes are not equivalent to individually rebuilding every mockup.

## Remaining work and limitations

- Exact screen-specific parity for all remaining web frames and dialogs, including full multi-step audit-plan wizard, approved-plan assignment generation, participants, separate report submission/publication, specialized CAR verification and QMS viewer interactions, and email features, is not complete.
- Existing workflows and security checks have been retained instead of simulating unavailable functionality with decorative buttons. The new organization screen is deliberately read-only and links to the existing department management screen.
- The exact Figma logo asset could not be downloaded in this environment; existing local logo and icons are used. No runtime dependency on temporary Figma asset URLs is introduced.
- PHP syntax checks for changed controllers and routes, JavaScript syntax check, and 16 existing JavaScript unit tests passed. Laravel Blade compilation, full frontend build, browser screenshot comparison, database-backed feature tests and all-role functional validation have not been run here. Some existing UI-specific test assertions may require updates for intentional markup changes.
- Do not treat this patch as a production release or verified full Figma implementation.

## Apply to an existing V2 installation

1. Stop the running application using the normal local shutdown method. Back up the current project files or commit them to your local Git branch first.
2. Extract the patch ZIP into a temporary folder. Copy its `app`, `public`, `resources`, `routes`, and `docs` contents into the root of your existing CompliTrack-V2 installation, merging directories and replacing the listed changed files. Do not delete other files. If you customized a changed file locally, merge its changes manually.
3. Run `php artisan view:clear`, `php artisan route:clear`, and `npm run build`. Run `php artisan view:cache` to validate Blade compilation, then `php artisan view:clear` for development. Start your usual development services and refresh the browser.
4. Do not reset `.env`, change encryption keys, run migrations or reseed solely for this UI patch. The patch does not include database changes. Test each role before using with important data.
