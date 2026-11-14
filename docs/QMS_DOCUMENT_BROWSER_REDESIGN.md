# QMS document browser redesign

The shared QMS component follows the supplied folder-browser screenshots with an internal navigation rail, authorized folder tree, breadcrumbs, search, server-side sorting and document-type filters, list/grid layouts, document selection, action menus, existing details/history and previews, and upload progress. The existing navy/gold branding and all four portal wrappers remain in use. Layout preference is the only browser-local persisted state; document metadata and permissions are fetched from authenticated endpoints.

Loading placeholders match the new navigation, heading, controls, folder cards, and responsive file columns. Both initial loading and subsequent page/filter requests follow the selected list/grid layout. Shimmer honors reduced motion. Browser verification covers 320, 390, 768, and 1440 pixel widths and checks that skeleton and loaded list columns match.

The `view` query parameter narrows the existing authorized document query. My Documents is the authorized repository, Shared with Me contains explicit account grants, Audit Evidence contains existing shared or submitted evidence references, Recent contains records added in the last 30 days, and Starred resolves the current user's saved document IDs. Search, sorting and pagination operate on the server. A saved star does not grant access after a share, role, assignment or ownership change.

## Modified files

- `resources/views/components/qms-archives.blade.php`: shared document browser and top-level loading placeholders.
- `resources/views/components/qms-loading-skeleton.blade.php`: matching workspace and file-row/card placeholders.
- `public/css/qms-archives.css`: browser styling, responsive columns and skeleton geometry.
- `resources/js/qms-archives.js`: navigation views, layout preference, selection, sorting, stars and upload progress.
- `app/Http/Controllers/Api/V1/ArchiveController.php`: authorized server-side view filters, sorting and private response caching.
- `app/Http/Controllers/Api/V1/QmsArchiveController.php`: authorized star operation and private details responses.
- `app/Services/QmsArchiveAccess.php`: existing audit evidence reference lookup for filtered views.
- `app/Services/QmsArchivePresenter.php`: star state and modification timestamp metadata.
- `app/Models/ArchiveDocument.php`: modification timestamp tracking.
- `routes/api.php`: star route.
- `tests/Browser/qms-archives.cjs`: skeleton geometry, grid preference and responsive browser verification.
- `tests/Feature/QmsArchivesMongoTest.php`: view/star isolation and revocation regression.

## Storage and database impact

No migration, import, seeding or live database reset was performed. No files or archive records are copied for these views. The first star action adds `qmsStarredIds` to the acting user's existing MongoDB record using atomic set operations. Archive writes now record `updatedAt`; historical records fall back to `createdAt` for display. File encryption and private, no-store preview/download routes are retained. Listing and details responses also specify private, no-store caching.

## Existing authorization decisions

This redesign retains the repository's current institutional policy: ATL manages archives, department and college positions have their existing scoped read access, and Super Admin has aggregate folder reporting without document access. Auditors continue to require assigned and shared/submitted evidence. The five CET academic departments and the separate college-level Dean office are unchanged.

The supplied brief proposes broader management and cluster visibility than these existing policies allow. Changes to department upload authority, ATL scope, and Super Admin/cluster document access remain unresolved; this UI work does not establish those institutional grants. Category rename, version upload, permission edits, sharing and file access reuse the existing authorization services.

## Validation

- Existing isolated QMS MongoDB suite: 8 tests, 333 assertions passed before adding the new view/star regression.
- Browser suite: all four roles and 320/390/768/1440 pixel widths passed, including skeleton layout matching, folder navigation, pagination, search, metadata-only upload, details and stacked previews.
- Production frontend build passed.
- New isolated regression for view filtering, idempotent stars, no document duplication, scope isolation and access revocation: 1 test, 27 assertions passed. It caught an empty-array MongoDB filter issue in Audit Evidence; the filter now tests for an existing first shared-audit element.
- Qodo review could not complete because its service could not obtain repository credentials for the active workspace. No clean Qodo review is claimed. The service instructed against an automatic retry.

The workspace already contained substantial edits to QMS controllers, routes, views, scripts and tests when this task began. Those edits were preserved; task changes are not committed separately because the touched files contain mixed existing work.
