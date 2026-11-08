# Changelog

All notable changes to CompliTrack are documented here.

## [Unreleased] - 2026-11-09

### Added

- Added CET QMS Archives with authorized department and college folders, document categories, version history, controlled audit sharing, and digital-reference and physical-original records.
- Added backup-first MongoDB migration, mock DOCX import, verification tools, and idempotent development scenarios that reuse the existing Admin/Audit Team Leader and equivalent accounts without changing their credentials.
- Added a corrective-action timeline using recorded status and submission history, including revision cycles, current actions, authorized closure, and animated gold checks for completed steps.
- Added stacked evidence previews for PDF, images, and formatted Word documents, preserving tables, images, headings, and page breaks without replacing the audit-result popup.
- Added a local development proxy and ngrok setup instructions for the web and realtime services.

### Changed

- Redesigned Auditee evidence requests with responsive cards, actual summary counts, search, status filters, and request-detail popups while retaining corrective-evidence workflows.
- Kept corrective uploads inside Audit Result Details and made dashboard actions open the appropriate tab directly.
- Updated profile forms, password-change handling, and responsive profile styling.
- Added browser, view, workflow, and MongoDB authorization regression coverage for the archive and evidence interfaces.

### Fixed

- Restricted archive browsing, downloads, evidence sharing, and organizational summaries to authorized records; private auditor drafts and restricted documents remain protected.
- Preserved existing MongoDB identifiers, organizational relationships, stored CAR statuses, and the distinction between the Admin/Audit Team Leader and Cluster Head responsibilities.
- Corrected Auditee View actions, hover styling, nested-popup focus and cleanup, and mobile evidence-preview sizing.

### Removed

- Removed the separate Auditee finding-details page in favor of the existing Audit Result Details popup.

## [Unreleased] - 2026-11-06

### Added

- Added Admin audit assignments and an Auditor workspace with checklist preparation, evidence reviews, N/C/O evaluations, findings, interviews, submission, and reviewer acceptance or return for revision.
- Added scoped Auditor dashboard metrics, assignment previews, progress, and audit-history activity alongside existing checks and corrective actions.
- Added Auditee evidence requests, private response drafts, submitted references and files, physical-original availability, auditor feedback, and explicitly shared accepted result summaries.
- Added Auditee result details with tabs, in-place filtering and pagination, loading states, and a result dialog with contextual evidence actions.
- Added MongoDB audit assignment, evidence-request lookup, notification deduplication, and pending-dispatch indexes, with guarded backup-first maintenance and inspection scripts.
- Added isolated MongoDB workflow and access-control coverage, real Socket.IO notification transport tests, and Auditee dashboard, results, and password-reset regression tests.

### Changed

- Refreshed mobile Super Admin and Admin management screens, system logs, settings, profile editing, profile-photo dialogs, role badges, and shared navigation styling.
- Updated Auditee dashboards, archives, notifications, evidence forms, and help pages to use scoped records and actionable workflow states.
- Replaced change-stream-dependent notification delivery with polling of persisted notifications, expiring worker leases, recipient-specific Socket.IO rooms, and stable event identifiers. Standalone MongoDB no longer requires change-stream support.
- Added deduplicated notifications for audit assignment, submission, review decisions, standalone check creation, and corrective-action status changes; web notification lists refresh while visible and mobile clients suppress replayed event IDs.
- Updated realtime configuration to prefer the root application's MongoDB and JWT settings, with service-file fallback and explicit process overrides.
- Required saved evidence reviews before checklist evaluation and verification remarks before corrective-action closure; physical signature outcomes remain separate from N/C/O results, and checklist findings do not automatically create corrective actions.

### Fixed

- Corrected string/ObjectId comparisons in department scoping, audit assignment selectors, notification ownership, archive access, corrective upload links, and atomic corrective-action transitions.
- Added duplicate-assignment protection, stale-update conflict checks, and submitted or accepted audit editing restrictions.
- Strengthened evidence-upload authorization and DOCX content validation, and blocked corrective uploads for closed corrective actions.
- Corrected the demo IT department's college value to CET while preserving existing relationships.
- Corrected password-reset links copied with HTML-escaped query parameters, redirected incomplete links to request a replacement, and preserved current link values after validation errors.
- Fixed realtime startup's package path, removed the hard-coded replica-set name, corrected the MongoDB change-stream diagnostic, and enforced JWT expiry on connected sockets.
- Corrected mobile local-development API connectivity and adjusted Sanctum authentication handling.

### Removed

- Removed Firebase Admin, Flutter Firebase dependencies, unused messaging services, device-token handling, and obsolete configuration and settings. Persisted in-app notifications and foreground Socket.IO delivery remain supported; closed-app OS push is no longer supported.

## [Unreleased] - 2026-11-04

### Added

- Added a protected MongoDB health endpoint and live database statistics for the Super Admin dashboard.

### Changed

- Configured Laravel services to use MongoDB and updated the startup script to migrate and seed each configured database target once.
- Updated mobile system health checks to use the authenticated API, and refreshed notification lists and unread counts every 30 seconds.
- Refined the Super Admin user-growth trend and removed the dashboard offline banner.

### Fixed

- Updated the logout-all command to clear sessions stored in MongoDB.

## [Unreleased] - 2026-11-02

### Added

- Added MongoDB-backed authentication support for the Laravel web app, including environment and database configuration for MongoDB-backed user storage.
- Added role-based web portal routing for Super Admin, Admin, Auditor, and Auditee users, plus password reset and profile update flows.

### Changed

- Updated the app configuration to support database-backed sessions, cache, queue setup, and MongoDB-aware user model behavior.
- Refined authentication and profile handling to align with the portal structure and active-user role permissions.

### Fixed

- Corrected the browser-session authentication flow and protected-route setup for password reset and authenticated profile actions.

## [Unreleased] - 2026-10-23

### Added

- Added web password reset pages and submission handlers, including reset email links, password updates from the profile, and a 10-minute reset-token lifetime.
- Added reactivation actions for users and departments, with audit entries for activation changes.
- Added encrypted demo archive documents to the database seeder.
- Added profile-photo upload and display support to the web profile and navigation.

### Changed

- Expanded resource management dialogs with confirmation flows, success notifications, and user or department reactivation controls.
- Added sortable audit-log columns and updated the Super Admin charts to show user growth and department compliance views.
- Updated archive and avatar uploads to validate file signatures and read or write files through the configured storage paths.
- Improved profile, reset-password, evidence-upload, and Super Admin layouts for clearer responsive presentation.

### Fixed

- Prevented deactivation or role demotion of Super Admin accounts, and ensured Super Admin accounts remain active.
- Deactivated active users assigned to a department when that department is deactivated; blocked user reactivation while an assigned department remains inactive.
- Corrected audit-log MongoDB identifier handling and normalized active-status values in user listings.
- Fixed stateful browser-session logout and inactive-account handling for Sanctum authentication.
- Corrected reset-token expiration handling and reset-email URL generation.

## [Unreleased] - 2026-10-22

### Added

- Added live System Health checks for the API, realtime service, database, and push configuration on the Super Admin dashboard.

### Changed

- Updated the Super Admin dashboard to show recent audit activity and a six-month cumulative user-growth trend from current data.
- Tightened the Users toolbar with a compact role filter and create-user action, and reduced the notification overlay close control size.

### Fixed

- Reverted the recent Super Admin create-user modal and keyboard-handling experiments, restoring the earlier role-filter dropdown presentation.
- Removed persistent chart tooltip overlays so department compliance bars display clearly in USTP gold; replaced the placeholder crest and malformed notification glyph with the USTP emblem and a rounded bell icon.



## [Unreleased] - 2026-10-20

### Added

- Added Laravel Blade pages and navigation for the Super Admin, Admin, Auditor, and Auditee portals, including role dashboards and their user, department, archive, compliance, notification, report, and settings screens.
- Added login, forgot-password, and profile views, plus web logout handling and stateful Sanctum authentication for browser sessions.
- Added authenticated profile-photo upload and retrieval. Accepted formats are JPG, PNG, and WebP, up to 2 MB.
- Added demo data seeding to the main database seeder.
- Added a shared resource page for searchable, paginated records and common user and department management actions.

### Changed

- Updated portal layouts and styling to improve responsive spacing, sidebar navigation, dashboard cards, forms, tables, dialogs, and button affordances.
- Improved the Super Admin Users and Departments dialogs with clear headers, vertically stacked fields, and distinct action footers.
- Standardized role badges across the Roles and Users pages.
- Added archive search by file name or code, and completed report metrics for total and compliant checks.
- Improved login handling with remember-me support and a five-attempt-per-minute throttle.

### Fixed

- Corrected search labels and icon spacing for Users, Archive Repository, and Audit Logs.
- Centered the Archives and Audit Logs empty states with consistent icon spacing and padding.
- Improved spacing and responsive layout on Roles and Settings pages.
- Refined sign-out button sizing and neutral colors.
- Updated sidebar and header profile images to use each signed-in user’s uploaded photo, with initials as the fallback.


