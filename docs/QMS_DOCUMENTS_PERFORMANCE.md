# QMS Documents performance investigation

Measured on 16 November 2026, Windows/PHP 8.2.30, MongoDB 7.0 in an isolated local Docker replica set. Institutional records and the running ONLYOFFICE container were untouched. Existing uncommitted work in this checkout was preserved.

## Evidence and implementation

The initial `/api/v1/documents/tree` request was the largest bottleneck: 12,259.98 ms median, with 247 MongoDB commands taking 641.5 ms median combined. The controller repeatedly filtered hydrated document collections inside every category/unit pair, including repeated ObjectId casts. A single pass now builds department/category count maps; immediate-child counts, storage totals, response shape and visible hierarchy are preserved. This alone reduced the intermediate optimized tree measurement from 10,060.85 ms to approximately 116 ms.

Folder policy evaluation repeatedly fetched the same eligible departments and audit assignments. Request attributes now hold repository units and reusable model references, including missing references. Already loaded folders populate that lookup for breadcrumb ancestry. The cache expires with the request; it does not cache permission decisions across requests. Assignment status, auditor binding, department membership and each existing folder policy still determine access.

The document presenter previously rebuilt the authorized document query and performed a visibility lookup for every listed row. Listing rows have already passed that same authoritative database predicate; only this listing caller marks visibility checked. Owner, department and category references are fetched together. Other presenter callers and all mutation endpoints retain their independent visibility checks.

Read-only rename/move/delete hints now retrieve version chains and audit/compliance references in three page-wide queries. Projections omit file contents and unrelated fields. Protected historical versions, active editor workspaces and audit/compliance evidence still prevent mutation. Transactional mutation endpoints continue to recheck their current chain and references using the original authoritative logic; hints never authorize a write.

The frontend already requested a paginated file list for the active view, used immediate category filters, retained folders before files, and guarded against stale navigation responses. No initial editor configuration, file content, conversion or preview requests were found. No request loop or duplicate initialization was demonstrated. Improvements add cancellation, a 30-second request timeout, cleanup on component destruction, initialization failure handling, Retry, and suppression of empty-folder messages after failures. Failed pagination retains the last successful page; failed navigation does not display another location's records. Direct folder links now skip the unrelated root listing.

## Repeatable measurements

The opt-in `QmsArchivesMongoTest::test_repeatable_qms_performance_sample` creates a random isolated database and removes only that database afterward. The dataset contains the migrated CET configuration, one department chair, one audit, 30 manual folders and 120 documents in one folder. Each endpoint has one warmup and 20 measured requests through the Laravel test kernel. Median is the mean of samples 10 and 11; p95 uses nearest rank (sample 19). MongoDB driver command monitoring includes finds, aggregations/counts and cursor operations. These are local kernel latencies, not internet/browser timings. ActingAs avoids measuring login/session initialization or real network authentication. The fixture's My Drive/Department roots contain folders but no direct files; Assigned, Shared and Starred are empty. Recent, Audit, folder and search return documents.

| Endpoint/flow | Before median / p95 ms | After median / p95 ms | MongoDB commands before → after |
|---|---:|---:|---:|
| Initial navigation tree | 12259.98 / 14269.49 | 115.76 / 130.98 | 247 → 22 |
| My Drive | 109.26 / 153.38 | 25.63 / 29.36 | 36 → 7 |
| My Department | 100.99 / 116.46 | 26.29 / 29.74 | 37 → 8 |
| Assigned Audits | 351.89 / 401.12 | 52.86 / 58.59 | 129 → 11 |
| Shared with Me | 90.97 / 98.82 | 25.67 / 41.53 | 36 → 7 |
| Recent | 810.13 / 997.60 | 84.82 / 95.35 | 264 → 19 |
| Audit Records document query | 735.61 / 932.36 | 92.26 / 125.87 | 264 → 19 |
| Starred | 115.96 / 146.93 | 28.03 / 39.37 | 37 → 8 |
| Folder, 12-file page | 476.94 / 553.71 | 72.91 / 81.56 | 176 → 19 |
| Search, 12-file page | 533.25 / 631.00 | 72.61 / 87.58 | 176 → 19 |

Largest API response: **49,874 bytes before and after** (tree). Folder response: 10,346 bytes before and after. Initial QMS API calls remain one tree and one active-view list; direct authorized folder links decrease from a tree plus two lists to a tree plus one list. These counts are verified using the browser adapter fixture. No claim is made about unrelated dashboard notification requests.

Full browser initial page latency, real middleware/session timing, Atlas/network latency and production p95 were not measured: the benchmark uses isolated test-kernel requests and database-free browser fixtures, not institutional accounts or production traffic. Do not interpret the tree latency as a measured browser page-load time. Audit plan hierarchy navigation is functionally tested but its endpoint has no separate latency benchmark here.

Raw measurements and the query plan are written to `storage/framework/testing/qms-performance-before.json`, `qms-performance-after-final.json` and `qms-query-plan.json`. Keep them when comparing another environment. The intermediate `qms-performance-after.json` predates the count-map optimization and is not the final result.

Run a new optimized sample from PowerShell:

```powershell
$env:MONGODB_URI='mongodb://127.0.0.1:27027/?replicaSet=qmsTest&directConnection=true'
$env:QMS_BENCHMARK='local'
php vendor/phpunit/phpunit/phpunit -c phpunit.audit.xml --filter test_repeatable_qms_performance_sample
```

This requires a running isolated MongoDB replica set. The test creates its own random `complitrack_qms_test_*` database and asserts its name before using or dropping it. Do not point diagnostic scripts at institutional records for writes.

## Queries and indexes

The real authorized folder predicate was compiled using the MongoDB Laravel builder's `toMql()`, including both legacy string and ObjectId category references, and executed through `explain` with execution statistics. It uses `departmentId_1_categoryId_1_createdAt_-1`: IXSCAN, 120 keys examined, 120 documents examined, 120 returned. Existing `_id` indexes serve batched reference lookups. No new database indexes or production migrations were justified by this sample or applied. Case-insensitive substring search is still a regex query; a normal title index would not solve arbitrary substring search.

## Validation and remaining work

Final checks: `npm.cmd run build` passed; `node tests/Browser/qms-archives.cjs` passed; the combined QMS/ONLYOFFICE/route integration selection completed **29 tests, 824 assertions, 1 skipped** (the opt-in benchmark). The benchmark was run separately and passed **212 assertions**. `git diff --check` passed, with line-ending notices on existing files.

The QMS archives, audit archive, route and ONLYOFFICE integration suites cover department isolation, assignment revocation, combined Chair/Auditor responsibilities, grants, mutations, preserved versions, secure editor callbacks and encrypted saves. Added regression tests compare batched action hints with authoritative checks, assert three page-wide capability reads, bound a 30-level folder tree's query count, verify immediate-child counts and navigation, and deactivate a unit between requests to verify scope refresh.

The headless Edge browser suite covers 1440/768/390/320 px, all four roles, both layouts, search/filter/pagination, metadata and preview behavior, audit hierarchy/history, exact initial/direct-link request counts, lazy ONLYOFFICE, failed initialization/retry and rapid navigation cancellation. The first browser run used old compiled assets and failed; rebuilding assets resolved that mismatch. A subsequent run exposed pagination-record preservation, which was corrected and retested.

Remaining scale limits: the legacy navigation response still contains the authorized folder hierarchy and fetches authorized document summaries for counts; latest-version selection still materializes projected IDs before pagination; audit evidence/assignment resolution and global folder exclusion may grow with repository size. This targeted change preserves those API contracts. Further changes should benchmark larger multi-department datasets and consider a separately paginated navigation/count contract and database-side latest-version grouping with the same historical-evidence visibility semantics. Do not add global permission caches.

Deployment requires the changed PHP/Blade/JS files and `npm.cmd run build` assets. No new configuration, migrations, infrastructure or ONLYOFFICE restart is required. The existing development-only `scripts/performance/router.php` / `RequestProfile.php` can provide Server-Timing and aggregate command counts for authenticated real-network follow-up; it is token-gated and does not log document contents or credentials.

## Files changed for this investigation

- `app/Http/Controllers/Api/V1/ArchiveController.php`, `QmsArchiveController.php`
- `app/Services/QmsRequestReferences.php` (new), `QmsRepositoryPolicy.php`, `QmsFolderAccess.php`, `QmsBrowser.php`, `QmsArchiveAccess.php`, `QmsArchivePresenter.php`, `QmsDocumentActions.php`, `DocumentEditorAccess.php`
- `resources/js/qms-archives.js`, `resources/views/components/qms-archives.blade.php`
- `tests/Feature/QmsArchivesMongoTest.php`, `tests/Support/QmsQueryProbe.php` (new), `tests/Browser/qms-archives.cjs`
- This report; generated build and isolated testing artifacts.

Several listed files already had unrelated uncommitted changes at intake. No commit was made because those overlapping changes could not safely be isolated into a task-only commit.

## Independent review assessment

Qodo fast checkpoint `69f5b86b-bea8-43a6-816c-f2c2cc8b0d01` and final auto review `dbf88586-1009-49ca-98d8-fea1c1920c8e` completed. Reviews used locally fetched Development history and included overlapping pre-existing edits. Issue and compliance reviewers ran; spec/UI/persona/cross-repository/skills reviewers were skipped. These results do not certify those skipped dimensions. Findings were assessed locally; none were persistently dismissed in Qodo.

- `ca3b89d2-b18d-40db-a36e-54d7b12cce17` [Reliability/action_required]: claimed missing Blade components. They exist as pre-existing untracked files; Blade rendering and the browser suite pass. Their inclusion in the final input resolves the review's missing context. No code fix required.
- `57739075-e14c-4d44-847e-edab527f6658` [Correctness/action_required]: claimed the folder API cannot create repository folders. The current `QmsFolderController::store` dispatches `folderPolicy` requests to `storeRepositoryFolder`; integration tests create and navigate those folders successfully. That controller's overlapping pre-existing changes were outside the submitted change paths. No code fix required.
- `97d0d36c-4684-42bb-be96-bededbeb4c11` [Correctness/action_required]: noted that editor saves do not inherit audit shares. This is the explicit existing policy in `QMS_AUTOMATIC_ACCESS_REDESIGN.md`, Editing and evidence versions: grants apply to the exact historical evidence revision; a new revision requires its own evidence grant. Changing this would widen visibility. This investigation preserves that behavior. No code fix required.
- `472396e3-67d8-4027-aed3-9b8067251a25` [Correctness/remediation_recommended]: proposed showing ordinary-category evidence in Assigned Audits. The current documented design defines this as evidence/notes repository-folder navigation; legacy linked evidence remains reachable through Audit Records and its authorized contextual endpoints. This performance task preserves that separation and does not change the visible hierarchy. No code fix required.
- `927f3b90-3e83-48d5-b992-47e6e28d4228` [Performance/remediation_recommended]: confirms the remaining projected-ID materialization in `QmsBrowser::latest`. Valid limitation, retained above. Database-side grouping and pagination needs a separate measured change that preserves filtering order and exact historical-revision authorization. The current targeted work substantially reduces measured latency but does not eliminate every scale limit.
