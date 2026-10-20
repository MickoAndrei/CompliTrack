# Changelog

All notable changes to CompliTrack are documented here.

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
