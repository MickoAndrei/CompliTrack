# QMS Documents manager improvements

The existing Laravel/MongoDB QMS module and ONLYOFFICE integration are retained. This implements the improvement brief with the File Explorer layout. Later instructions take precedence: New Folder retains its icon, rounded 36px style and one-click modal; Upload File remains separate. The outer page and loading-sidebar scrollbars are removed.

## Implemented behavior

- My Drive combines authorized manual roots with existing organizational folders. It does not manufacture a fixed set of departments.
- My Department uses organizational memberships independently of audit assignments. A single department opens directly; multiple saved memberships offer selection. An ATL without department affiliation receives a labelled CET selection view. Departmental shortcuts include manual departmental roots and reference the same underlying records as My Drive.
- Normal browsing sends `scope=current`, shows immediate child folders and documents in that exact folder, and excludes nested files. Shared, Recent, Starred and Audit Records narrow the authorized query. Search communicates its scope through the location placeholder and accessible input label.
- Folder history preserves view and location for Back/Forward/Up/Refresh. Authorized direct links use `?folder=<id>&unit=<optional-unit-id>`. Unavailable locations return one neutral message.
- Folder rows and grid use the same records. Names are sortable; metadata uses actual responsible units, owners and dates. Folder sizes remain a dash. Document rows show actual human-readable file sizes; full names are available through tooltips.
- Folder menus include Open, permitted Rename/Move/Delete, Star/Unstar and Details. Organizational/configured roots remain protected. Existing scope inheritance, duplicate-name checks, circular-move prevention, depth limits and empty-only soft deletion are reused.
- Document menus include Preview, supported Open/Edit, Download, Version History, permitted Rename/Move/Delete and Star/Unstar. Details include location, owner, dates, file size and classification. Rename/move use a small dialog.
- Assigned Audits intersects valid assignments with authorized bound folders, including ATL oversight. Empty states distinguish missing assignments from assignments without created folders. Audit Records uses explicit folder policies, ancestry, audit bindings and evidence metadata, never name matching. Folder details show a recorded audit status when available.
- Shared, Starred, Assigned Audits, Audit Records, Recent, empty folders and filtered results have contextual empty states. Creation/upload controls depend on effective capabilities.
- Recent and normal listings select the latest authorized revision by `versionGroupId`. Separate same-name documents remain separate. Starred revisions resolve to the latest authorized version of the same logical document. History and explicit historical audit evidence retain their original version-specific authorization.
- Header/navigation stay stable while QMS content scrolls internally. The skeleton sidebar does not scroll. Dialogs and the existing fullscreen editor remain usable on narrow screens.

## Authorization

`QmsArchiveAccess`, `QmsFolderAccess`, `QmsRepositoryPolicy`, `QmsAuditArchive` and `DocumentEditorAccess` remain authoritative. `QmsBrowser` narrows and presents their results; saved stars and folder names never grant access.

Department filtering intersects the authorized query with organizational membership instead of merging auditor assignments into that shortcut. Assigned Audits intersects visible evidence/notes folders with eligible assignment IDs. Folder details and stars revalidate visibility server-side. Deleted records are excluded from normal access and aggregate reporting.

`QmsDocumentActions` validates document mutations. Only the latest manageable revision is mutable. Audit-bound records, shared/submitted evidence, authorized plans and live ONLYOFFICE workspaces are protected. Moves stay within the same department, permitted classification, folder policy and audit binding, and require destination upload permission. All revisions move together. Mutation transactions write the real version-chain anchor to serialize with the existing editor, including when its group ID differs from the anchor ID.

Deletion marks an entire unprotected version chain without removing encrypted bytes or database records. Existing direct URLs, editor sessions, callbacks, version history and evidence grants retain centralized checks. Assignment revocation removes audit-based access without removing independent department-chair rights.

## Files

New:

- `app/Services/QmsBrowser.php`
- `app/Services/QmsDocumentActions.php`
- `resources/views/components/qms-folder-shortcuts.blade.php`
- This report.

Updated:

- `app/Http/Controllers/Api/V1/ArchiveController.php`
- `app/Http/Controllers/Api/V1/QmsArchiveController.php`
- `app/Http/Controllers/Api/V1/QmsFolderController.php`
- `app/Services/QmsArchiveAccess.php`
- `app/Services/QmsArchivePresenter.php`
- `app/Services/QmsRepositoryPolicy.php`
- `routes/api.php`
- `resources/js/qms-archives.js`
- `resources/views/components/qms-archives.blade.php`
- `resources/views/components/qms-folder-row-heading.blade.php`
- `public/css/qms-archives.css`
- `tests/Feature/QmsArchivesMongoTest.php`
- `tests/Browser/qms-archives.cjs`
- `README.md` and `docs/QMS_AUTOMATIC_ACCESS_REDESIGN.md`.

Existing folder metadata and skeleton components are reused. The workspace contains earlier mixed changes; this is a task file list, not ownership of every local diff.

## Storage and schema

No production migration, collection reset, seeding or record relocation is required or performed. Optional fields are written lazily: user `qmsStarredFolderIds` and `qmsStarredFolderUnits`; archive `deletedAt`, `deletedByUserId` and `qmsMutationRevision`. Existing stars, version groups, folder policies, timestamps and organization/audit references are reused. No second editor, storage service or Firebase integration is introduced.

## Verification and limitations

Integration tests use a temporary local replica set with unique per-test databases. Browser tests render production Blade with isolated API fixtures and do not write application records.

Completed verification:

- QMS archive, ONLYOFFICE, audit-archive, audit-workflow and corrective-action regressions: **52 tests, 1,676 assertions passed**.
- Unit suite: **20 tests, 86 assertions passed**.
- Browser suite: **passed at 320/390/768/1440px across all four portal wrappers**, including normal folder/document flows, department selection, contextual empty states, authorized direct links, navigation, editor dialogs, no outer scrollbar and no loading-sidebar scrollbar.
- Production Vite build, Blade compilation, targeted PHP syntax checks and diff-whitespace checks passed.
- The isolated test container was stopped after verification; existing application records and the running ONLYOFFICE service were preserved.

The existing ONLYOFFICE container remains in use. Local health and browser API returned HTTP 200, with JWT configured. Integration regressions cover editor permissions, signed callbacks, encrypted saved versions and historical evidence. A new real interactive editing session was not performed during this task.

Folder sizes remain uncalculated and genuinely missing metadata remains a dash. There is no new blank-document creation workflow, trash/restore UI, or evidence deletion. Account management continues to govern membership assignments; QMS supports multiple saved memberships without changing those governance rules. Latest-version selection scans authorized identity metadata before pagination; larger repositories may benefit from an indexed latest-version projection.

Qodo CLI 2.3.1 returned `not_logged_in`; external review and notes were unavailable. No completed external review is claimed. Changes were not committed because this working tree contains mixed earlier edits.
