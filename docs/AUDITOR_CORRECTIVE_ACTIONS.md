# Auditor Corrective Actions implementation report

Implemented November 13, 2026. The three supplied screenshots informed the card list and large details dialog. Their illustrative record titles and counts were not copied into application data.

## Result

- The Auditor Corrective Actions page shows an **Audit Item** description first and the existing check reference underneath. When an authorized source audit item is linked, its question, finding, standard and auditor remarks are used. Legacy records fall back to their existing remarks; missing information is identified as not recorded.
- Four cards show Total Actions, In Progress, Awaiting Verification and Closed from the assigned auditor's existing nonconforming compliance checks in currently assigned departments.
- Search covers the audit item, finding, reference, standard, department and auditee. Department and status controls, server pagination, read-only status badges and responsive cards replace the old table and inline status dropdowns.
- Summary cards follow the search and department filter across all matching pages. The status filter affects the list, not the cards. Total Actions also includes Open and any retained legacy CAR statuses, so the other three cards need not sum to Total Actions.
- The responsive details dialog shows the original finding, applicable standard, auditor remarks, responsible auditee, department, recorded dates, permitted evidence, persisted CAR history and authorized workflow actions.
- The timeline retains repeated statuses and resubmissions. It distinguishes completed, current, pending and recorded returned-for-revision events. Missing dates or actors remain explicitly unrecorded; expected future stages are not presented as actual events. Permitted linked corrective file uploads use their actual file creation timestamps.
- Existing reference/original evidence responses remain separate from a verification decision. File metadata and previews reuse `QmsArchiveAccess`, `AtlWorkspace::evidence()` and the existing evidence preview component and download endpoint.

## Workflow and authorization

The README, `ComplianceCheckService` and existing audit workflow tests explicitly authorize the assigned Auditor to perform these transitions:

| Current stored status | Authorized next status | Required notes |
| --- | --- | --- |
| Open | In Progress | Optional |
| In Progress | Closed | Verification performed |
| Closed | Open | Reason for reopening |

This change preserves that workflow. Only an active Auditor who owns the check and still has the department assignment may update its status. Super Admins, Admins/ATL, Auditees, other Auditors, inactive accounts and revoked department assignments cannot close it. Compliant records cannot acquire a CAR through the status endpoint. The existing atomic status comparison, append-only status history, closure timestamps, audit logging and notification dispatch remain in use.

**Awaiting Verification is a presentation state, not a new database status.** It means an Open or In Progress CAR has a timestamped corrective response, or a linked file uploaded by its responsible auditee in the same department, at or after its latest recorded status event. Both the current stored status and derived display status are returned. Undated evidence is displayed without inventing a submission date or claiming this stage.

Existing `/api/v1/compliance-checks` list/detail routes accept `correctiveActions=1` for this presentation. That mode is restricted to active Auditors and uses the existing check scope plus current department assignments. Verification posts to the existing `/api/v1/compliance-checks/{id}/status` endpoint. Permission checks execute on the server, including for direct requests.

The existing `ComplianceCheck` collection, `CarTimeline` helper, archive permissions, status badge, loading skeleton, pagination component and evidence viewer are reused. There is no duplicate CAR data model, collection, transition API or evidence store. No production records, evidence submissions or histories were rewritten; no MongoDB reset, seeding, migration or Firebase integration was performed. Integration test writes and cleanup were confined to randomly named temporary test databases.

## Files added or changed for this task

| File | Purpose |
| --- | --- |
| `app/Services/CorrectiveActionPresenter.php` | Authorized CAR read presentation, summary, filtering, details, evidence and timeline |
| `app/Services/ComplianceCheckService.php` | Shared allowed transitions; active account, current department and nonconforming-record guards |
| `app/Http/Controllers/Api/V1/ComplianceCheckController.php` | Opt-in CAR presentation on existing list/detail routes |
| `resources/views/auditor/corrective-actions.blade.php` | Card list, filters, modal, evidence, timeline and verification form |
| `resources/js/corrective-actions.js` | Page state, requests, stale-response protection and guarded verification submission |
| `resources/js/app.js` | Load the CAR presentation script when its page is present |
| `resources/js/icons.js` | Regenerated explicit Lucide registry |
| `public/css/corrective-actions.css` | Responsive navy, yellow and white presentation |
| `tests/Feature/CorrectiveActionsMongoTest.php` | Real database authorization, details, scope, filters, pagination and workflow tests |
| `tests/Unit/corrective-actions.test.cjs` | Stale requests, modal lifecycle, verification notes, duplicate submissions and backend errors |
| `tests/Fixtures/corrective-actions.php` | Production Blade browser fixture without database writes |
| `tests/Browser/corrective-actions.cjs` | Responsive list/dialog, timeline, filters, pagination, verification, previews and focus checks |
| `docs/AUDITOR_CORRECTIVE_ACTIONS.md` | This report |

Compiled frontend artifacts were rebuilt with Vite. Other existing worktree changes, including earlier header and directory work, were retained.

## Validation

| Check | Result |
| --- | --- |
| `php artisan test --compact` | 72 passed, 553 assertions; 69 opt-in integration tests skipped |
| PHP MongoDB regression selection: `CorrectiveActionsMongoTest`, `AuditWorkflowMongoTest`, `AtlWorkspaceMongoTest`, `QmsArchivesMongoTest` using `phpunit.audit.xml` | 27 passed, 954 assertions |
| Final targeted `CorrectiveActionsMongoTest` rerun after adding inactive-account and compliant-record checks | 3 passed, 63 assertions; inactive accounts correctly receive the existing authentication middleware's 401 response |
| `node --test tests/Unit/*.test.cjs` | 35 passed |
| `npm.cmd run build` | Passed |
| Realtime `RUN_MONGO_NOTIFICATION_TESTS=1 npm.cmd test` | 17 passed, including actual persisted notification delivery through authenticated Socket.IO |
| `node tests/Browser/corrective-actions.cjs` | Passed at 1440, 768, 390 and 320 pixels; includes focus restoration and stacked evidence preview |
| `node tests/Browser/attention.cjs` | Passed existing four-portal inbox, directory, Activity Logs and header checks |
| `node tests/Browser/evidence-preview.cjs` | Passed PDF/image/Word previews, authorization errors, retry, focus, cleanup and parent-dialog preservation at 1440, 390 and 320 pixels |
| `node tests/Browser/evidence-requests.cjs` | Failed its search-icon spacing assertion when fixtures loaded CSS from the configured ngrok URL. Retested with `APP_URL=http://localhost`: progressed to a separate Shift+Tab focus-wrap assertion at line 84 and failed there. No evidence-request module or test was changed for this CAR task. |

Screenshots of the new dialog are generated at `storage/framework/testing/car-browser/details-1440.png` and `details-390.png`.

Qodo CLI authentication succeeded outside the sandbox, but review operation `f944024f-474d-4375-a2a2-9039a65e6c80` failed because Qodo could not obtain repository credentials for the current workspace. Its instruction was not to automatically retry. No Qodo approval or completed external review is claimed. The selected worktree changes were submitted without a commit because shared files contain earlier user work; this is not a reviewed PR commit. Local implementation inspection and the checks above were completed.

## Remaining workflow questions and limitations

1. Should Awaiting Verification eventually become an explicit persisted workflow state? This implementation derives it from recorded submissions and timestamps, without changing the approved status machine.
2. Should an Auditor be able to return an In Progress CAR for revision? No such transition exists today. Recorded legacy returned stages are displayed, and Closed CARs can be reopened with notes, but no new return transition or permissions were invented.
3. Does the organization want ATL approval before closure? The current documented and enforced workflow authorizes assigned Auditors to close after recording verification. Changing that policy requires a separate explicit workflow decision.
4. The existing evidence-request Shift+Tab focus assertion needs investigation in that module. It remains a reported regression-check failure.
5. The read presenter batch-loads the authorized CAR set to derive evidence-based stages and search linked audit descriptions before filtering/pagination. Large histories may require a later database aggregation or indexing strategy; this change introduces no schema migration or production index changes.
