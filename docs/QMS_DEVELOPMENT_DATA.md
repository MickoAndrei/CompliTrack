# CET development data, mock imports, and audit scenarios

Applied to remote **complitrack** for the current app on port 8000. No database reset, `.env` edits, Firebase dependencies, new authentication roles, or replacement Admin accounts were introduced.

## Result

| Item | Result |
| --- | --- |
| Existing Admin/ATL | Reused `demo.admin@example.test`, ObjectId `6aeac5b88ce89fe741003b3a`, unchanged |
| Existing assigned IT Auditor | Reused ObjectId `6aec2806956cbefa81046e82`, unchanged |
| New primary Admin accounts | **0** |
| Newly created demo accounts | **22** |
| Imported Word kit documents | **27**, source checksums verified |
| Additional scenario archive records | **28**, including versions and independent upload examples |
| Verified archive records | **55**: **45 encrypted files** and **10 metadata-only records** |
| Seeded audits | **11** |
| New CARs | **7**, in the existing `compliance_checks` collection |
| Main database totals | **30 users, 11 audits, 11 compliance checks, 63 archives, 111 notifications** |

The 63 archives include the 8 older records whose organizational ownership remains unverified. They were preserved and remain access-restricted. All five CET departments and the separate Dean office retain their existing organizational IDs.

The full account list, source file manifest, readable categories, department/college IDs, storage/classification status, and audit references are in `storage/app/private/qms-development/report.json`. This report contains no passwords. Newly generated, distinct account passwords are stored separately in `storage/app/private/qms-development/new-account-credentials.json`; neither private file is committed or exposed by the web server.

## Account reuse and creation

The existing Admin/ATL was selected from active primary-role `1` users. There was exactly one matching Admin in the actual database. It remains the reviewer for every seeded audit, the actor in seeded ATL review-history entries, and the recipient of submission notifications. Its password hash, role, department assignment, permissions, and other stored fields were not changed.

The existing active IT Auditor already had a canonical IT assignment, so its existing ID was reused without adding or changing assignments. The older demo auditor/auditee accounts in unclassified departments were not silently reassigned.

Created accounts:

- A Department Head, Department Secretary, and representative Faculty/Staff member in **each** of CET-ABE, CET-ENV, CET-FPT, CET-HM, and CET-IT: 15 accounts.
- An Auditor for ABE, ENV, FPT, and HM: 4 accounts. IT reuses its existing Auditor.
- CET College Dean and College Secretary: 2 accounts, with explicit CET college scope and the Dean office as their home office.
- A distinct Cluster Head Administrator: 1 account, with explicit CET scope. It uses existing primary role `3` with an organizational responsibility, not the Admin/ATL role, and cannot accept/return audit submissions.

All new identities use `qms.*@example.test` email addresses and `[MOCK]` names. Credentials are independently generated and hashed through Laravel. User reuse checks organization, activity, role, and responsibility; ambiguous or incompatible matches fail rather than replacing an account. The seeder fills missing department-head references without replacing existing heads.

## Schema and storage

The original college/category migration remains in place. This seed reuses `users`, `departments`, `qms_categories`, `archive_documents`, `audits`, `compliance_checks`, `notifications`, `audit_logs`, `counters`, and `migrations`.

- Adds indexed `mockSeedKey` identifiers to newly seeded users, audits, archives, and CARs. Partial unique indexes leave unmarked existing records unaffected.
- Imported archives carry `sourceImportKey`, source metadata, and mock tags. Operational IDs remain MongoDB references; original portable fixture IDs are retained only as provenance.
- Audit records use existing `auditorId`, `reviewerId`, `items`, `evidenceRequests`, and `history`, with optional source metadata, authorized attachment IDs, and completed-record retention metadata.
- CARs use existing `correctiveStatus`, `statusHistory`, `linkedArchiveIds`, and `evidenceResponses`; source audit/item links identify the associated checklist finding.
- Reuses `sharedAuditIds` for explicitly shared evidence and plan/checklist/report attachments. The assigned ATL can read these attachments through its existing audit-review assignment, without receiving the general department repository or changing its user permissions.
- Notification dedupe keys prevent repeat assignment, evidence, submission, publication, and CAR notifications. Existing suitable unique indexes are reused.
- Adds three configurable, provisional Dean categories: College Management Reviews; Board Resolutions & Directives; College Development Plans. Existing categories remain intact.
- A completed-seed checkpoint prevents re-running fixture initialization over edited demo workflows.

All file content uses the existing AES-256-GCM private storage and upload validation. MongoDB stores metadata and secure storage references; file bytes remain in the app's encrypted private storage. The original 27 Word files are unchanged. Other departments use visibly synthetic PDFs without official forms, approvals, student records, or personnel information. Physical originals and references work without uploads.

An encrypted backup of every affected collection and its indexes was written and verified before seeding. The private report and migration checkpoint record its path. Post-seed verification compared **all 8 pre-existing user records, all 4 existing compliance checks, and all 4 role records byte-for-byte** with the backup. They were unchanged, including credentials and organizational relationships.

## Workflow scenarios

| Reference | Department | Stored state | Scenario |
| --- | --- | --- | --- |
| IQA-2026-001-DEMO | IT | accepted | Imported completed kit: 7 evaluated items, 3 C / 3 N / 1 O, authorized result publication, 3 linked CARs |
| IQA-2026-002-DEMO | IT | ready | Kit spot check: 2 C and 1 pending physical-original request |
| QMS-DEMO-SCHEDULED | IT | draft, no items | Assigned audit awaiting preparation |
| QMS-DEMO-CHECKLIST-DRAFT | ABE | draft | Persisted draft questions without evaluations |
| QMS-DEMO-EVIDENCE-REQUEST | ENV | ready | Secretary-addressed evidence request awaiting response |
| QMS-DEMO-DIGITAL-SUBMISSION | FPT | ready | Submitted authorized file awaiting auditor review |
| QMS-DEMO-PHYSICAL-REVIEW | HM | ready | Mock signed-original verification and a pending item |
| QMS-DEMO-SUBMITTED | ABE | submitted | N/C/O checklist awaiting existing ATL review |
| QMS-DEMO-RETURNED | ENV | returned | ATL return and retained review history |
| QMS-DEMO-COMPLETED | FPT | accepted | Completed N/C/O results and verified closed CAR |
| QMS-DEMO-RESUBMITTED | HM | submitted | ATL return/resubmission cycle and repeated corrective responses |

Scheduled and checklist-draft states both use existing `draft`, distinguished by whether questions exist. In-progress uses `ready`; completed uses `accepted`. No conflicting status names were added. Accepted records are retained; this does not introduce an additional interactive audit-archiving workflow.

The kit's CARs are `CAR-DEMO-001` (closed), `CAR-DEMO-002` (in_progress / awaiting verification), and `CAR-DEMO-003` (open). Four additional department CARs use `QMS-DEMO-CAR-CET-{ABE,ENV,FPT,HM}`. Observations do not create CARs. Timeline dates/events are stored fixture records; the UI does not manufacture timelines.

**ATL/CAR policy:** the current application permits the assigned Auditor to officially close a CAR, and permits the Admin/ATL to accept or return audit submissions. The portable kit's ATL closure attribution is preserved in source metadata; operational CAR closure is attributed to the assigned Auditor. No extra closure permission was granted to the ATL or auditee. This difference is intentional and follows existing authorization.

## Permissions and UI

Department users see their assigned organization's permitted records. Deans, College Secretaries, and Cluster Heads use explicit college scope. Restricted faculty records require explicit grants, and auditors receive only explicitly shared assigned evidence. Cross-department searches, direct URLs, previews, and downloads enforce the same policy.

The Auditee dashboard adds a read-only organizational oversight summary for Head/Secretary/Dean/College Secretary/Cluster responsibilities. It shows scoped audit references, states, and CAR counts, without private checklist drafts or auditor notes. Evidence response and audit-review actions remain assigned to their original responsible accounts. Document details now display available document codes, source revisions, and effective dates. Shared request files use existing file preview/download controls without creating duplicate evidence systems.

## Commands and actual validation

```powershell
php scripts/import-mock-archives.php
php scripts/import-mock-archives.php --apply
php scripts/seed-qms-development.php
php scripts/seed-qms-development.php --apply --summary
php scripts/verify-qms-development.php
php scripts/verify-qms-schema.php
php vendor/phpunit/phpunit/phpunit -c phpunit.audit.xml
php vendor/phpunit/phpunit/phpunit -c phpunit.audit.xml --filter test_development_scenarios
npm.cmd run build
node tests/Browser/qms-development-live.cjs
```

`php artisan db:seed --class=QmsDevelopmentSeeder` is also available for explicitly requested development setup. The seeder refuses production environments. Tests use isolated random MongoDB databases and private temporary storage.

- Full regression run: **49 tests, 928 assertions passed**.
- Final targeted scenario/ATL test after attachment-scope refinement: **1 test, 65 assertions passed**. This also checked ATL assignment/review, unchanged account BSON, auditee/ATL closure denial, assigned Auditor closure, new-account authentication, and shared request-file links.
- Importer integration test: **1 test, 18 assertions passed**, including deduplication, encrypted-file integrity, protected downloads, and rejecting changed imports.
- An intermediate targeted test expected an Admin with a valid IT membership to be denied an ordinary IT document. That expectation was corrected by using the actual existing ATL's unclassified legacy department in the fixture; final checks passed with the real authorization distinction.
- The second main-database seed run reported `alreadySeeded: true` with unchanged record counts and no duplicate users, archives, audits, CARs, or notifications.
- Post-seed verification passed: **45 encrypted files verified, 10 metadata-only records, all original users/roles/checks unchanged**.
- Live browser acceptance on port 8000 passed with new mock accounts: IT Secretary authentication, actual stored Word syllabus preview above preserved details, ENV Secretary's denied IT download/empty IT search, and CET Dean's six authorized folders at mobile width with no horizontal overflow.
- Production build, PHP syntax checks, Blade compilation, and whitespace checks passed. Vite retains the existing main-bundle-size warning.

Live screenshots are in `storage/framework/testing/qms-live/it-document-preview.png` and `dean-mobile-archives.png`. Passwords and tokens were not printed by the browser test.

## Remaining decisions

The two older unclassified demo units and their 8 archives still require approved ownership mappings; they were not merged or reassigned. Final institutional category names, approval rules, and document-control/signature procedures remain unverified and must not be inferred from mock material. The 400-person Excel directory was not bulk-converted into accounts; this task creates the requested representative users, and the existing file workflow does not support XLSX uploads.

## Files for this implementation

- Services: `MockArchiveImporter.php`, `QmsDevelopmentData.php`, `QmsOversightService.php`; refinements to `QmsArchiveAccess.php`, `QmsArchivePresenter.php`, and `AuditeeEvidenceService.php`.
- Seeder/scripts: `database/seeders/QmsDevelopmentSeeder.php`; `scripts/import-mock-archives.php`, `seed-qms-development.php`, `inspect-scenario-data.php`, `verify-qms-development.php`.
- Controllers/views: `QmsArchiveController.php`, `AuditeeController.php`; Auditee dashboard/oversight partial, shared archive component, and authorized audit attachment links in the audit workspace.
- Tests: `tests/Feature/QmsArchivesMongoTest.php`, `tests/Browser/qms-development-live.cjs`.
- Documentation: this report, README links, and the original archive migration report. Other pre-existing workspace changes were retained.
