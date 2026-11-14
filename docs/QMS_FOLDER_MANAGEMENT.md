# QMS folder management

The existing browser now offers **+ New → New Folder / Upload File** using independent current-location capabilities. The compact native dialog shows the parent location, focuses the name field and reports validation errors. User subfolders have rename, move and confirmed empty-only delete actions. Campus/college roots remain navigation roots; users select an authorized department or office for ownership.

## Existing hierarchy and additive schema

Organizational roots come from existing active, verified college/department/office records. CET retains its five academic departments and separate Office of the Dean. No organizational records, mandatory categories, document IDs or encrypted files are recreated.

User subfolders extend `qms_categories` with `userManaged`, `departmentId`, `normalizedName`, `classification`, and `createdByUserId`. Existing college, unit type, parent, code, active and timestamp fields are reused. Missing `userManaged` identifies a system category. Documents continue to use `archive_documents.categoryId`; there is no extra folder collection or file copying.

The additive migration `2026_11_14_150000_index_qms_user_subfolders.php` creates a unique partial index on department, parent and normalized name for active user folders, excluding existing system categories. Folder changes and QMS uploads serialize with a per-department `qmsFolderRevision` inside MongoDB transactions, preventing concurrent creation/upload into a just-deleted folder. Transactions require the replica set specified in README.

Names are limited to 100 characters, normalize sibling-name casing/whitespace, and reject path separators, control characters and traversal names. Depth is limited to 12. Moves cannot cross department/office boundaries or change classification, create cycles, or exceed depth limits. Moving populated structures also requires `manage`. Deletion requires an empty folder and sets `isActive=false`; documents and files are never deleted.

## Authorization

| Responsibility | Default folder-management scope |
| --- | --- |
| Department Head / Chair | Own authorized department |
| Department Secretary | Own authorized department |
| College Secretary | Explicitly assigned active college and its departments/offices |
| College Dean | Read-only; management requires an explicit scoped grant |
| Admin / ATL | Departments of current reviewer assignments; no global folder-management authority |
| Cluster Administrator | Assigned cluster's active colleges |
| Auditor | No department-archive management; existing audit evidence workspace stays in use |
| Ordinary faculty/staff | Read-only folders in existing organizational scope |
| Super Admin without a scoped responsibility | Existing aggregate/folder reporting; no automatic management |

Capabilities are `view`, `create`, `rename`, `move`, `delete`, `manage`, and `upload`. Organizational and mandatory category roots cannot be renamed, moved or deleted through folder APIs. The legacy category rename endpoint now rejects writes: changing a shared template would otherwise change every department's hierarchy. Taxonomy changes belong to controlled organizational configuration/migrations; user subfolders use the scoped operations.

Ownership and classification are inherited from the validated parent. Clients cannot supply college, department ownership, classification, system flag or audit assignment. Default management covers `department` classification; restricted and college-classified locations require explicit matching classification grants. User-folder uploads and permission edits cannot broaden visibility beyond folder classification. Document authorization excludes inaccessible user folders for listing, metadata, history, editors, previews and downloads. Ownership and saved stars do not bypass revoked folder access.

Cluster mappings are explicit in `config/qms.php`: Cluster A maps to Claveria and Cluster B to Cagayan de Oro. Active colleges may instead carry `clusterId`. Other campuses are not inferred from group names; institutions must provision mappings for them. Legacy explicit college assignments work when no cluster is assigned.

## API and grants

- `POST /api/v1/documents/folders`: `unitId`, optional `parentId`, `name`.
- `PATCH /api/v1/documents/folders/{id}`: `unitId`, `operation=rename` and `name`, or `operation=move` and destination `parentId` (null means the same department/office root).
- `DELETE /api/v1/documents/folders/{id}`: `unitId`; empty user folders only.
- `PATCH /api/v1/documents/folder-grants/{userId}`: Super Admin only, replacing scoped grants. Each entry has `unitId`, `actions`, and `classifications`. Target units must already belong to the account's organizational/audit scope. Grants cannot manufacture organizational authority or give archive-management rights to Auditors or ordinary faculty/staff.

Example explicit Dean grant:

```json
{"grants":[{"unitId":"<existing authorized unit ObjectId>","actions":["view","create","rename","upload"],"classifications":["department"]}]}
```

An entry replaces defaults for its unit. Removing a Dean grant restores read-only defaults. To disable all capabilities for a default manager in a unit, retain a grant entry with empty `actions`; removing the entry restores responsibility defaults. A dedicated grant editor is not included; the authenticated API supports administrative provisioning. Successful folder changes and grant changes use existing activity logs.

## Verification and deployment

Implementation files:

- New `app/Services/QmsFolderAccess.php` and `app/Http/Controllers/Api/V1/QmsFolderController.php` implement the scoped capability policy and transactional folder operations.
- `QmsArchiveController.php`, `ArchiveController.php`, `QmsArchiveAccess.php`, and `QmsCategory.php` integrate tree capabilities, protected templates, folder-aware uploads/document access and ownership casting.
- `routes/api.php` and `config/qms.php` add authenticated operations and explicit cluster mappings.
- `resources/js/qms-archives.js`, the shared `qms-archives.blade.php` component, and `public/css/qms-archives.css` implement the menu, dialog and folder actions.
- The additive migration supplies duplicate-name protection. `QmsArchivesMongoTest.php`, `QmsDocumentRoutesTest.php`, and `tests/Browser/qms-archives.cjs` contain the regression coverage. README links this document.

Tests use isolated, randomly named MongoDB databases and temporary storage. New checks cover responsibilities, department/college/cluster isolation, Dean grants, inheritance, revocation, spoofed ownership, protected roots, duplicate names, traversal, cycles, nonempty deletion and activity logs. Browser checks cover four portal wrappers and 320/390/768/1440px layouts, New-menu permissions, dialog focus/size, folder creation, uploads and existing previews/navigation/pagination.

Apply just the additive index migration:

```powershell
php artisan migrate --path=database/migrations/2026_11_14_150000_index_qms_user_subfolders.php --force
```

Actual results:

- Additive index migration applied successfully to the configured database. No production document, folder or user records were created by the implementation work.
- QMS archive and document-route regression run: **13 tests, 465 assertions passed**.
- Final focused run after tightening legacy root protection and inactive-college handling: **5 tests, 157 assertions passed**.
- Admin portal/document-route checks: **7 tests, 133 assertions passed**.
- Browser checks passed for all four roles and 320/390/768/1440px layouts, including New Folder creation, modal focus/size, Upload File, details, previews and pagination.
- Production Vite build passed; PHP syntax and diff-whitespace checks passed.
- Qodo automated review remains unavailable because its previously attempted review could not obtain repository credentials. That service instructed against an automatic retry; no completed Qodo review is claimed.

No reset, seeding, document deletion, Firebase or duplicate folder store is included. Existing mixed worktree edits are preserved. The tests created and removed only their isolated temporary databases and storage.
