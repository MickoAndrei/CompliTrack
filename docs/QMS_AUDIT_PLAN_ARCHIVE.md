# Audit Plan archive hierarchy

The Audit Evidence browser exposes logical folders in this order:

`Audit Plan → Assigned Auditor → Campus → College → Department/Office → Authorized Evidence`

Plans remain existing `archive_documents` with accepted `auditPlan` approval and authorized `assignmentAuthorization`. Assignments remain existing `audits`, linked by `planId` and their approval snapshot. No audit-cycle record, copied document, physical folder tree, or season naming convention is introduced.

The plan folder displays the source document's `title` (or existing `originalName`) and official `documentReference`, falling back to its existing source document code or archive file code. Assignment titles and cached `planTitle` never supply the folder name.

## Identity and authorization

Every node uses its source MongoDB identifier: plan document, auditor user, campus, college, and department/office. Nodes are deduplicated by identifier within their parent path, so repeated assignments do not create duplicate folders. Only units covered by the viewer's authorized assignments appear.

`QmsAuditArchive` validates current organizational scope, the assignment's approved plan snapshot, and its current auditor/reviewer. Department officers use their authorized unit IDs; college officers use authorized colleges; cluster administrators use their cluster scope. Admin/ATL browsing uses assigned reviewer scopes. Auditors must have both the live assignment and authorized department scope. Existing Super Admin document restrictions remain in place.

Evidence queries intersect existing document authorization, ownership verification, visibility/classification and folder restrictions with exact audit evidence references or sharing grants and the assignment's department. Navigation never changes a document's owner. The same context is checked for listing, metadata/version history, preview/download, and integrity verification. Folder paths are validated on every request; changing a URL cannot widen access.

Accepted historical assignments remain available while their authorization remains valid. Plan revision history can verify the assignment's original approval snapshot after the plan enters a new draft. Reassigning an audit revokes the former auditor's access; this does not delete historical audit records or evidence references.

## Additive campus migration

`2026_11_14_160000_add_qms_campus_references.php` adds `campuses` with a unique `campusKey`, plus missing `campusId` references on existing colleges and matching departments. This is necessary because campuses previously had labels only. It recognizes configured official campus names and their existing USTP label variants. Unknown or conflicting organization labels are not guessed or overwritten. Existing identifiers are retained when rerun. Rollback deliberately retains identity references.

Run with:

```powershell
php artisan migrate --path=database/migrations/2026_11_14_160000_add_qms_campus_references.php --force
```

The migration was applied successfully to the configured application database. It does not reset records, alter document storage, or rename existing documents. New organization imports should call `QmsCampusReferences::attach` after creating a college, or explicitly rerun the idempotent reference migration service.

Legacy audits without a verified `planId` are not assigned invented cycles. Their permitted documents remain in My Documents and existing audit workflows. Audit Evidence displays a notice when no verified plan assignments are available.

## API and interface

Authenticated `GET /api/v1/documents/audit-folders` returns the next folder level and breadcrumbs. Optional path parameters must be contiguous: `planId`, `auditorId`, `campusId`, `collegeId`, `unitId`. Evidence endpoints accept the complete path and enforce its authorization. Existing `auditId` filtering also requires the viewer's actual assignment scope.

The shared Google Drive-style browser retains its list/grid views, dialogs, file previews, downloads and navigation. Auditors initially open Audit Evidence; other users retain My Documents. Audit folders are logical, protected navigation nodes rather than user-managed QMS subfolders. Revoked paths clear previously displayed evidence.

## Regression coverage

`QmsAuditArchiveMongoTest` uses a disposable isolated MongoDB database and temporary encrypted storage. It covers official naming/reference, stable IDs, deduplication, plan approval, assignment changes, department/college/cluster/reviewer isolation, historical approval, encrypted downloads and checksum verification, unauthorized metadata and direct file requests. Existing archive, plan approval and document-route suites provide regression coverage. `tests/Browser/qms-archives.cjs` checks the hierarchy, contextual previews, navigation and revocation at desktop and mobile widths alongside existing browser flows.

External Qodo review was unavailable because its repository credentials failed; its response instructed against automatic retries. Local regression checks and manual authorization review were used instead.

Focused plan-hierarchy and route checks passed: 5 tests, 117 assertions. Browser regression checks passed for all four roles at 1440, 768, 390 and 320 pixels, including hierarchy navigation and contextual preview URLs. The production frontend build and PHP syntax checks passed.

The broader archive, Audit Plan approval, plan-hierarchy and document-route regression run also passed: 25 tests, 712 assertions. The focused run above includes the final organizational-scope refinement and additional reviewer/college/cluster test.
