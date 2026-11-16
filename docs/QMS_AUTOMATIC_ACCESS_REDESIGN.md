# QMS Documents: manual folders and automatic access

See [QMS Document Manager Improvements](QMS_DOCUMENT_MANAGER_IMPROVEMENTS.md) for the latest browsing, actions, metadata, version selection and scrolling behavior. Later user instructions retain the direct New Folder button.

This implementation extends the existing QMS browser, `qms_categories`, encrypted `archive_documents`, and Docker ONLYOFFICE integration. It follows Figma nodes `2009:2` and `2009:117` in the supplied CompliTrack file. No audit-cycle folders, sample auditor names, departments, users, or documents are seeded by the change.

Audit Cycles shows manually created folders. Assigned Audit Folders narrows these to evidence and notes. My Department narrows the existing authorized document query to the user's department responsibility. My Documents, Shared With Me, Recent, Starred, search, list/grid views, selection, preview, details, versions, uploads, and action menus continue through the existing endpoints.

## Authorization

`QmsArchiveAccess` remains the document authorization entry point. `QmsRepositoryPolicy` supplies manual-folder policies and current assignment eligibility; `QmsFolderAccess` delegates to it for these folders. Permission decisions are independent of folder names.

- Active CET ATL accounts identified by an existing CET reviewer assignment manage the Claveria CET repository, including restricted records and internal-note oversight.
- Department Chairs retain permitted department management even when their authentication role is Auditor. Their other audit assignments grant review access only to explicitly authorized evidence.
- Department auditees browse permitted department records. Scoped folder grants can authorize uploads; upload permission alone does not grant management or editing of existing records.
- Evidence folders bind to an actual assignment, its department, and its auditor. Assignment eligibility alone does not grant document access. Existing explicit audit shares, submitted responses, and evidence references supply document authorization.
- Notes bind to a current assignment and its auditor. Department membership does not grant access. Reassignment or cancellation revokes the original auditor's folder and document access while retaining records.
- Administrative containers are college scoped. Their authorized children may establish separate department/assignment policies. Other subfolders retain their parent's policy and binding. Moves preserve scope, reject cycles and excessive depth, and never delete contents. Deletion is restricted to empty folders.

Navigation returns authorized descendant shortcuts when their ancestors are confidential. These shortcuts omit confidential parent IDs and names. Opening one never authorizes sibling enumeration. Every document query still applies current document and folder authorization, including search, metadata, history, direct downloads, editor sessions, source retrieval, and callbacks. Request-local permission caches do not survive subsequent requests.

## Additive metadata and concurrency

New manual folders reuse `qms_categories` and add `folderPolicy` (`department`, `evidence`, `notes`, `administration`), `auditAssignmentId`, and `auditorId`. Existing parent, college, department, classification, creator, active flag, normalized sibling name, timestamps, and user-managed metadata remain in use. Organizational and assignment references are existing ObjectIds. No existing category is converted or relocated.

Repository mutations and uploads serialize on the existing college's additive `qmsFolderRevision` inside MongoDB transactions. Legacy folder operations retain their department lock. A repository folder cannot be entered through legacy operations to evade its policy. The existing unique college/category-code index is preserved; new folders receive internal unique `RF-` codes.

The targeted index migration is `database/migrations/2026_11_16_190000_index_manual_qms_repository_folders.php`. It adds a partial unique college/parent/normalized-name index for active manual folders and an assignment/auditor lookup index. It does not rewrite records. Apply only this migration when deploying this feature, rather than unrelated pending migrations.

## Editing and evidence versions

The existing Document Server is reused. An Auditor who is also a Chair may edit permitted files in their chair department; assigned evidence in another department remains read-only. Assigned auditors can edit their own authorized internal notes. Current permissions are rechecked for source fetches and saves.

Each upload/editor save creates a new encrypted version. The original bytes and audit references remain intact. New revisions do not inherit `sharedAuditIds`; the exact historical evidence revision remains authorized until an explicit new evidence grant is made. Repository callbacks join the existing save transaction and acquire the same college lock used by folder operations.

## Changed implementation files

- `app/Services/QmsRepositoryPolicy.php`
- `app/Services/QmsArchiveAccess.php`
- `app/Services/QmsFolderAccess.php`
- `app/Services/DocumentEditorAccess.php`
- `app/Http/Controllers/Api/V1/QmsFolderController.php`
- `app/Http/Controllers/Api/V1/QmsArchiveController.php`
- `app/Http/Controllers/Api/V1/ArchiveController.php`
- `app/Models/QmsCategory.php`
- The targeted index migration above.
- `resources/js/qms-archives.js`
- `resources/views/components/qms-archives.blade.php`
- `public/css/qms-archives.css`
- `tests/Feature/QmsArchivesMongoTest.php`
- `tests/Feature/OnlyOfficeMongoTest.php`
- `tests/Browser/qms-archives.cjs`
- `tests/Unit/AuditReviewSubmissionTest.php` (bootstraps Laravel for its existing MongoDB model assertions).
- `README.md`, `docs/QMS_FOLDER_MANAGEMENT.md`, `docs/QMS_DOCUMENT_BROWSER_REDESIGN.md`, and `docs/ONLYOFFICE_INTEGRATION.md` link the current policy and identify superseded policy descriptions.

The workspace contained unrelated and overlapping edits at task start; those edits are preserved. No commit isolates this feature from those existing changes.

## Validation

Completed on 2026-11-16:

- QMS archive, document-route, ONLYOFFICE, audit-archive, audit-workflow, and CAR regressions: **52 tests, 1,645 assertions passed**. The last focused policy run additionally verifies the ATL landing view and auditee upload without management: **1 test, 34 assertions passed**.
- Unit suite: **20 tests, 86 assertions passed**. The existing audit-review submission unit test now boots Laravel before constructing its MongoDB model. The development-data regression explicitly provisions its existing CAR verify/close permissions in its isolated fixture; no production role permission is changed.
- Browser suite passed at 320/390/768/1440px and exercised all four portal wrappers, including real manual folder breadcrumbs, authorized policy choices, selected folder state, fixed dialog actions, navigation, grid/list, search, pagination, upload, previews, and history.
- Real existing ONLYOFFICE container: DOCX text editing, XLSX cell editing, PPTX slide addition, two authorized collaborators sharing the same editor key, and saved DOCX reopening read-only all passed. Saved versions were decrypted to verify edited contents; original bytes were compared with all three source templates and remained identical.
- Existing ONLYOFFICE health and browser API both returned HTTP 200. The existing Document Server was retained.
- Production Vite build, Blade compilation, PHP syntax checks, and diff-whitespace checks passed.
- The targeted additive index migration was applied successfully to the configured application database. No application user, department, audit, or archive record was seeded, relocated, or reset.

Atlas connectivity interrupted an earlier integration run. Final integration verification used a temporary local MongoDB 7 replica set, with the URI set only in the test processes. The application `.env` was not changed. The exact Atlas test database left by the interrupted cleanup was removed after connectivity recovered. The temporary local replica set, live editor test database, encrypted fixtures, and PHP server were removed/stopped after verification. Browser fixtures render production views and intercept API requests without writing live records.

Qodo review could not run because the installed CLI returned `not_logged_in`. No completed external review is claimed. There are no remaining failures in the completed test sets described above.

## File browser visual refinements

The browser uses the supplied Figma screen's white file navigation, horizontal folder cards, and original folder SVG. The redundant repository title and subtitle were removed, and the Create Folder close button has a visible box. The later Explorer toolbar reference takes precedence for navigation: Back, Forward, Up, and Refresh sit beside a white Home/breadcrumb address bar and a contextual search box with its search icon on the right. Search moves below navigation on narrow screens. The loading skeleton follows the same toolbar geometry. Up is enabled in manual repository folders and returns to the parent scope.

The frontend production build and Blade compilation passed. Browser verification passed at 320/390/768/1440px across all four portal wrappers, including contextual search placeholders, manual-folder Up/Back behavior, accessible parent breadcrumbs, folder icons, existing document flows, and responsive dialogs. These visual refinements do not change backend authorization or stored records.

The later Drive list reference replaces the folder cards with compact rows, sortable names, vertical action menus, and Owner / Date modified / File size columns. Missing metadata and uncalculated folder sizes display a dash. Narrow screens retain Name and File size. Folder locations hide the document heading, filters, records, loading rows, access note, and pagination; documents remain available inside leaf folders and in document-only views. This decision uses the underlying child-folder structure, so searching within a folder location does not reveal an aggregate document list. Browser checks cover folder-row geometry, both name sort directions, hidden document controls, and retained leaf-folder document access.
