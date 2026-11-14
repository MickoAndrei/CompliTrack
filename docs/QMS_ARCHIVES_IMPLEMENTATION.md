# CET QMS Archives implementation

Implemented in the current Laravel app on port 8000. The old app is unchanged. No Firebase, additional dependencies, or `.env` changes were introduced.

Subsequent authorized mock imports and scenario seeding are documented in
[QMS_DEVELOPMENT_DATA.md](QMS_DEVELOPMENT_DATA.md). Counts below describe the original migration snapshot; the later seed adds 55 verified archive records and representative accounts.

## Completed features

All four Archives pages now use a shared responsive explorer with the existing navigation and navy, gold, and white design. It includes authorized college/department/office folders, categories and subcategories, breadcrumbs, search, document-type filters, pagination, metadata, versions, and access/activity history. The folder tree collapses behind **Browse folders** on mobile.

**Add record** supports encrypted digital files (PDF, DOCX, PNG, JPEG; maximum 20 MB), digital document references, and metadata-only physical originals. Physical originals require a location and display pending signature verification. An upload is not mandatory; users cannot claim signature verification through this form. Existing audit evidence requests, corrective submissions, CAR statuses, authentication, and notification workflows remain in place.

File previews reuse the separate evidence viewer. Opening/closing a preview preserves the underlying document or Audit Result Details popup. File access is authorized again for each preview/download. Document managers can set visibility, choose explicitly permitted accounts, disable file preview/download, and share evidence with a named audit reference. Earlier document versions/files remain intact; new versions inherit permissions and require their own explicit audit share. Version conflicts return HTTP 409 and clean up only the unsuccessful new file.

Audit Team Leaders manage QMS records, organizational responsibilities, and category names. An active Admin with an existing audit reviewer assignment is recognized as an Audit Team Leader. Super Admins can browse folders and see aggregate document counts and file-storage usage, while file lists, details, previews, downloads, and management actions are denied by the API. Other accounts retain their existing scoped read access.

## Applied migration and organization

Applied `2026_11_08_qms_archives_v1` to **complitrack**, after isolated integration tests. Before any writes, the migration saves an encrypted, BSON-preserving snapshot of affected collections and their index definitions. The on-disk backup was decrypted and its SHA-256 verified after application. Its location is recorded in the existing MongoDB `migrations` record, under `storage/app/private/qms-migration-backups/`.

Existing IDs, passwords, user assignments, archive owners, checksums, file paths, and audit links were preserved. No records were deleted, merged, or reassigned. Re-running the migration reports that it has already been applied.

The official Information Technology department retains ObjectId `6aeac5b48ce89fe741003b36`; its college name was standardized to `CET`. Four departments and a separate Dean office were added:

| Code | Name | Unit type |
| --- | --- | --- |
| CET-ABE | Agricultural and Biosystem Engineering | Academic department |
| CET-ENV | Environmental Engineering | Academic department |
| CET-FPT | Food Processing and Technology | Academic department |
| CET-HM | Hospitality Management | Academic department |
| CET-IT | Information Technology | Academic department |
| CET-DEAN | Office of the Dean | College office |

Department lists and assignment controls exclude college offices and unclassified legacy units. Backend validation rejects extra CET academic departments and duplicate names. Seeders use the approved organization and preserve ambiguous existing demo data instead of recreating additional CET departments.

### Collections and fields

| Collection | Changes |
| --- | --- |
| `colleges` | New organizational parent: `collegeCode`, `collegeName`, `campusName`, `isActive`. CET has one record. |
| `departments` | Adds `collegeId`, `unitType`, `ownershipVerified`; preserves existing IDs. |
| `qms_categories` | New configurable taxonomy: `collegeId`, `parentId`, `code`, `name`, `unitType`, `provisional`, `isActive`. Eighteen initial group/category records are shared by the appropriate units. |
| `users` | Supports `archiveResponsibility`, `archiveUnitIds`, `archiveCollegeIds`. No responsibilities or broader scopes were inferred for existing accounts. |
| `archive_documents` | Reuses the existing collection. Adds verified ownership, title/description, category, record type, document reference, physical location, signature state, visibility, permitted accounts, download permission, explicit audit shares, version group/number/previous version, and schema version. Encryption fields remain private. |
| `audit_logs` | Reused for views, file access, permission changes, versions, sharing, and responsibility/category changes. |
| `migrations` | Reused for the applied migration, backup path, and unresolved legacy IDs. |

Indexes cover unique college codes, college/unit ownership, unique category codes per college, archive unit/category/date, permitted accounts, explicit audit shares, and unique version numbers within ObjectId version groups. No duplicate evidence, CAR, or notification collection was introduced.

## Authorization

- Super Admin archive reporting uses a separate aggregate scope for verified folders. Folder/category counts, total archived documents, and digital file storage bytes remain available in QMS and on the dashboard. File lists, document details, versions, previews, downloads, and checksums remain inaccessible.
- Audit Team Leaders are active Admin accounts already assigned as an audit reviewer. They have full access to verified QMS records, including restricted and download-disabled files. Only they can add records, create versions, manage QMS responsibilities, configure categories, and update document access.
- Archive access requires an active account, verified ownership, and authorized organizational scope. Missing/unverified ownership is denied, including direct URLs.
- Department Heads/Secretaries use one explicitly assigned archive department, or their home assignment when no override is configured. An override does not also grant their former home department. Faculty/staff use organizational assignments plus document permissions.
- Deans/College Secretaries need one explicitly authorized college. Cluster Administrators need explicitly authorized colleges. A responsibility name or role ID does not grant unrestricted access.
- Document visibility never bypasses organizational scope. Owner-only/restricted records require ownership or explicit account permission; a management responsibility alone does not expose them.
- Auditors need both an active department assignment and evidence explicitly shared through their own audit, linked compliance-check evidence, or submitted audit evidence. Unsubmitted drafts do not grant access. Removing the assignment revokes access.
- List/search, folder counts, details, versions, checksums, previews, and downloads use the same scope. Download-disabled files cannot be fetched through the preview endpoint by scoped readers; Audit Team Leaders retain full access.
- Only Audit Team Leaders assign QMS organizational responsibilities, configure categories, or create/manage QMS records. Document managers can share only with an auditor assigned to the document's department. Unrelated account maintenance remains possible while legacy ownership is pending.

## Validation and commands

```powershell
php vendor/phpunit/phpunit/phpunit -c phpunit.audit.xml
node tests/Browser/qms-archives.cjs
node tests/Browser/evidence-preview.cjs
npm.cmd run build
php artisan view:cache
php artisan route:list --path=documents
php scripts/migrate-qms-archives.php
php scripts/migrate-qms-archives.php --apply
php scripts/verify-qms-schema.php
```

Integration tests create randomly named isolated databases and isolated encrypted storage/backup directories, with guarded cleanup. Browser fixtures render production views with mocked API responses without inserting sample main-database records.

- Full regression suite: **47 tests, 844 assertions passed**. A subsequent targeted run including the final legacy-account maintenance assertions also passed: **4 QMS tests, 175 assertions**.
- Explorer browser checks passed at **1440, 768, 390, and 320 pixels**, covering folders/categories, search, metadata-only submission, stacked previews, responsive dialogs, and no horizontal overflow.
- Existing evidence preview checks passed at **1440, 390, and 320 pixels**, covering PDF/image/Word rendering, focus restoration, authorization failure/retry, cleanup, and preserving Audit Result Details.
- Production build, PHP syntax checks, Blade compilation, route registration, and whitespace checks pass. Vite retains its existing warning about a main bundle larger than 500 kB.
- Main database verification confirmed preservation of **3 original departments, 8 users, and 8 archive records**, with exactly **5 academic departments**, **1 college office**, **18 provisional category records**, and the expected indexes.

## Ownership decisions requiring approval

The actual dataset currently has **0 verified archives and 8 archives awaiting ownership approval**. Empty archive counts reflect authorized database records; no sample documents/statistics were inserted.

| Preserved legacy unit | ObjectId | Dependencies | Required decision |
| --- | --- | --- | --- |
| [DEMO] Information Technology | 6aeac5b68ce89fe741003b38 | 3 users, 2 checks, 4 archives | Confirm whether these records should map to official Information Technology. |
| [DEMO] Engineering | 6aeac5b78ce89fe741003b39 | 2 users, 2 checks, 4 archives | Identify the correct approved department; generic Engineering is insufficient to infer ownership. |

These units and their archives are marked unverified, relationships remain intact, and archive access is denied pending approval. Affected accounts see an ownership-review notice. Merging/reassigning requires a separately reviewed mapping after confirmation. Existing authentication, audit results, and CAR records were not removed.

Real people must also be designated explicitly as Dean/Secretary/Head/Cluster Administrator by an Audit Team Leader. Final category/document-control and physical-signature approval requirements remain organizational decisions; the app does not invent those approvals.

## Files created or modified for this task

- Configuration/models: `config/qms.php`; `app/Models/College.php`, `QmsCategory.php`, `Department.php`, `User.php`, `ArchiveDocument.php`.
- Services: `app/Services/QmsArchiveAccess.php`, `QmsArchiveMigration.php`, `QmsArchivePresenter.php`, `RoleScopeService.php`.
- Controllers: `app/Http/Controllers/Api/V1/QmsArchiveController.php`, `ArchiveController.php`, `DepartmentController.php`, `UserController.php`; `app/Http/Controllers/Web/AuditeeController.php` (Archives action).
- Routes/seeders: `routes/api.php`; `database/seeders/DepartmentSeeder.php`, `DemoDataSeeder.php`, `ArchiveDemoSeeder.php`.
- Explorer: `resources/views/components/qms-archives.blade.php`; all four `resources/views/{admin,auditee,auditor,super-admin}/archives.blade.php`; `resources/js/qms-archives.js`, `app.js`; `public/css/qms-archives.css`.
- Shared preview: `resources/js/auditee-evidence-preview.js` (focus restoration in either context); `resources/views/auditee/evidence-preview-modal.blade.php` (contextual close label).
- Database tools: `scripts/inspect-qms-schema.php`, `migrate-qms-archives.php`, `verify-qms-schema.php`.
- Tests: `tests/Feature/QmsArchivesMongoTest.php`, `AuditWorkflowMongoTest.php` (verified fixtures and explicit evidence sharing); `tests/Fixtures/qms-archives.php`; `tests/Browser/qms-archives.cjs`.
- Documentation: `README.md` and this report. Frontend bundles and browser screenshots are generated local artifacts. Unrelated pre-existing workspace changes were retained.
