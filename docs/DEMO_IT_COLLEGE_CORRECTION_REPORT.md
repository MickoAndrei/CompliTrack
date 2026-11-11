# Demo IT college correction

Changed only `departments._id = ObjectId("6aeac5b68ce89fe741003b38")`, verified as active `DEMO-IT`, `[DEMO] Information Technology`. Its collegeName is now `CET`, previously `Collage of Engineering and Technology`. User authorization supplied the organizational approval; the existing demo seeder independently declares this department as CET.

Before updating, verified the active demo IT auditor's primary/assigned department IDs and active role-1 demo CET administrator. Inspected current references: 3 users, 2 compliance checks, 4 archive documents, 0 audit assignments. Relationships reference department IDs, which were retained. No user, assignment, checklist, archive or other record was migrated or deleted.

Backup saved before the update:

`storage/app/private/database-backups/demo-it-20261106-090041-846cdb/`

Contains `department.canonical.jsonl` (complete BSON-type-preserving original), `manifest.json` (references, all non-target record fingerprints, indexes), and `result.json` (post-update verification). Private storage is Git-ignored. The atomic update filtered the exact ID/code/old college and used only `$set: {collegeName: "CET"}`. Even the existing updated timestamp was left unchanged so this maintenance changed only the authorized field.

Verification proved the target document exactly matches its original except collegeName, every non-target document fingerprint/count is unchanged, and all four audit indexes plus `_id_` are unchanged. `.env` checksum is unchanged.

The first application verification found the department selectable but the auditor absent: legacy demo users store departmentId as string, whereas CET Admin directory scope queried BSON ObjectIds only. Fixed `RoleScopeService::users` to match both representations of the same permitted CET department IDs. Role restrictions and exact college scope remain. No user data was rewritten. The assignment integration regression now creates an auditor with a string departmentId to cover this case.

Actual commands/results:

- `php scripts/correct-demo-it-college.php --apply`: identity/reference checks, verified backup, single-field update, record/index preservation passed. Its final demo-auditor selector assertion exposed the legacy reference mismatch above. No repeat update was needed.
- `php scripts/correct-demo-it-college.php`: read-only follow-up after source fix; `adminDepartmentSelectable=true`, `demoAuditorSelectable=true`, `assignmentPageRendered=true` using the actual existing Admin controller/view and demo actor. This is server-side application rendering, not a browser login test.
- `php scripts/inspect-audit-assignments.php`: corrected department reads as CET; demo auditor remains active, assignment page renders, metrics status 200, zero live assignments.
- `php artisan mongodb:check`: MongoDB reachable, replica set atlas-hhx0ia-shard-0; change streams unavailable.
- `php vendor/phpunit/phpunit/phpunit -c phpunit.audit.xml`: isolated full regression suite. Covers authorized assignment creation/list/workspace, string/ObjectId relationships, unauthorized access denial, draft CRUD, digital/physical/restricted evidence, signature state separate from N/C/O, C/N/O metrics, findings/CAR separation, submission locking, return/resubmit/accept, notifications, legacy CAR closure, private-download denial, and stale-update conflicts. Final result recorded below.
- In `realtime/`, `npm.cmd test`: 1 broadcast-notification test passed. `npm.cmd run check`: syntax checks passed.
- `.env` SHA-256 remains `8E5D78F0DB3EF5B1D2E0F7C52FFA9DBA5C1D0000B6E7A72956DDC2A23FB47A94`.
- `git diff --check`: no whitespace errors, existing dashboard CRLF normalization warning only.

Files changed: new guarded maintenance script `scripts/correct-demo-it-college.php`; targeted compatibility fix in `app/Services/RoleScopeService.php`; string-reference regression fixture in `tests/Feature/AuditWorkflowMongoTest.php`; this report. No UI changes, new assignment system, live assignment, database reset, or environment changes.

Realtime limitation: source inspection shows the notifications change-stream watcher filters `admin_broadcast`, not `audit_review`. The check watcher handles existing compliance/CAR events. With change streams currently unavailable, no live Socket.IO or push delivery was demonstrated. Persisted in-app review notification creation/retrieval is covered by the isolated workflow suite. No unrelated realtime features or configuration were modified.

Manual browser acceptance remains: sign in as the CET Admin, confirm demo IT and auditor selectors, deliberately create an authorized live test assignment only after user approval, then complete checklist/evidence/N/C/O/submission/reviewer steps in browser. No live assignment was necessary for this operation, so none was inserted. Isolated fixtures exercised the existing application routes instead.

Final isolated regression result after the string-reference fix: **14 tests, 171 assertions passed**, exit code 0, 35.465 seconds. All test fixture writes and cleanup used generated isolated MongoDB databases.
