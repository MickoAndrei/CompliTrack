# Performance optimization and verification

For the current startup defaults, additional measurements, and the styling regression fix, see [Phase 2 performance and styling verification](PERFORMANCE_PHASE_2.md). The results below describe the earlier Phase 1 state.

Measured on 11 November 2026 (Asia/Shanghai), using the existing 30-user development database and public ngrok tunnel. These are diagnostic samples, not a production capacity or percentile benchmark.

## Before and after

| Measurement | Before | After |
| --- | --- | --- |
| Users controller, 10 rows, including options | 2,251 ms / 37 Mongo commands | 428 ms / 6 commands |
| Users controller, 20 rows, including options | 4,293 ms / 67 Mongo commands | 377 ms / 7 commands |
| Local 20-row API, three sequential samples | 5.70 / 10.03 / 7.06 s | 1.66 / 1.47 / 1.54 s |
| Ngrok 20-row API, three sequential samples | 6.06 / 11.55 / 6.20 s | 1.86 / 2.04 / 1.85 s |
| Local browser, cold / cache allowed, 10 rows | 8.80 / 6.49 s | 5.73 / 4.06 s |
| Ngrok browser, cold / cache allowed, 10 rows | 8.36 / 10.05 s | 6.31 / 4.03 s |
| Shared compiled JavaScript entry | 637,756 bytes | 169,369 bytes (73.4% smaller) |
| OPcache HTTP login median, five warm samples | 937 ms, current server | 212 ms, separate OPcache server (77.4% lower) |

Controller profiles exclude authentication, session, middleware and initial database connection costs. API measurements include those costs. Browser readiness means Alpine has loaded the Users summary and rows; it includes document loading, scripts and concurrent notifications. Cold disables the browser HTTP cache; warm permits caching. Network and background activity varied, so do not interpret cold/warm differences as a controlled cache experiment.

The main before/after API and browser measurements used the current server with OPcache **disabled**. The OPcache result is a separate experiment, not an additional gain already applied to those timings. No unmeasured performance target is claimed.

## Users API

`UserController` collects page-wide department, college and Admin reviewer IDs, then fetches each related collection at most once. Serialization reads these lookup maps. Empty ID collections do not issue a query. The single-user response paths use the same serializer. Assignment arrays, department references, legacy inactive departments and string/ObjectId reviewer references retain their meaning. Server-side write guards still determine whether an account may be changed or deactivated.

The 10-row production-data sample has no organizational college IDs and skips that lookup; the 20-row sample includes college IDs. This explains 6 versus 7 commands. For the same relation types, query count stays constant when page size grows, as the 10/20-row regression test verifies. With options included, an unfiltered management list needs at most seven controller commands for these relation types. Filtered pagination may need one additional count.

Four summary count queries are now one live MongoDB aggregation. Unfiltered pagination reuses its total rather than counting the same scope again. Filtered pagination still counts the filtered query. Summary equality comparisons retain the prior stored boolean/role behavior; no authorization-sensitive directory or summary response is cached.

`includeOptions=0` omits reusable filter metadata. It defaults to true, preserving the existing API contract. `GET /api/v1/users/options` provides fresh options to Super Admin only and is registered before the user-ID route.

Options are kept only in the current page's Alpine state, never in shared server or persistent browser storage:

- Initial load fetches them; search and pagination reuse them for up to one minute.
- Focus/visibility refresh, user creation/editing/deactivation/reactivation and opening an editor fetch fresh options.
- Department or college changes from another session are picked up on focus, editor open, expiration or page navigation. Editors always use fresh server validation on submission.
- Responsibility choices come from `config/qms.php`, not role records. Navigation/reload creates new page state for config/role/session changes. Option queries and authorization are evaluated live on the server.

## MongoDB index

Only one non-unique index was added: `users_full_name`, `{ fullName: 1 }`.

Before application, existing indexes were `_id_` and the unique partial `mockSeedKey_1`. Explain plans for unfiltered, auditor-filtered and active-filtered name ordering showed `SORT` over `COLLSCAN`. After application the plans use `IXSCAN` / `FETCH` / `LIMIT` without a blocking sort. The unfiltered 20-row query examines 20 documents rather than 30. Both server execution-time samples rounded to 0 ms: the index improves the access path, but no measurable server-time reduction is claimed for this small collection. The repeated database round trips were the major delay.

`scripts/apply-user-list-index.php` follows the repository's explicit backup-first maintenance convention. Without flags it reports indexes and execution plans. With `--apply`, it checks for a justified sort and compatible existing definitions, saves and hashes a private canonical backup, creates only this index, and verifies all old indexes and user records remain unchanged. Repeated application does not create a duplicate. The fresh-database definition is also in `database/mongodb/init.js`.

The live application reported `recordsUnchanged: true`. Its backup is under `storage/app/private/database-backups/user-list-index-20261111-070832-54342e`. No main records were deleted, reset or rewritten. Existing collection indexes remain in place. No additional filtering indexes were justified by these small explain samples; unanchored case-insensitive search still needs separate analysis at larger scale.

## Frontend and static assets

The full Lucide catalog import was replaced with 91 explicit icons found in application source. `node scripts/generate-icon-registry.mjs` regenerates the registry when adding an icon; a frontend test checks coverage. The shared entry fell to 169,369 bytes (about 56.6 KB in Vite's gzip estimate).

Dashboard, archive, settings, audit-team, CAR history and Auditee features are separate chunks loaded only where applicable. Document-ready helpers initialize delayed modules even if `DOMContentLoaded` has already fired. Page-specific Alpine registrations finish before the single `Alpine.start()`. DOCX preview remains lazy-loaded.

The active ngrok Node proxy serves only content-hashed build JS/CSS directly, with gzip negotiation, `Vary: Accept-Encoding`, immutable one-year caching, ETags/304 and HEAD support. It validates the resolved asset path. APIs, cookies, HTTPS forwarding and Socket.IO still use the existing proxy paths and retain their cache policies. Both local proxy and public ngrok asset requests confirmed `Content-Encoding: gzip` and `Cache-Control: public, max-age=31536000, immutable`. Direct port 8000 bypasses this static handler. Apache equivalents are included in `public/.htaccess`; no Apache/Nginx deployment was performed.

The ngrok startup script builds assets before starting services. Public ngrok requests ignore Vite's client-local hot-file reference through `UseTunnelAssets`; the original Vite setting is restored after each request. The script no longer moves the local hot file, so ordinary local development retains HMR even while the tunnel serves compiled files.

Google Inter and Plus Jakarta Sans font downloads still failed in the diagnostic browser. Typography and font providers were preserved. No unverified font files or substitutes were added. External font availability remains a source of delay/fallback to investigate separately.

## OPcache, Laravel and runtime costs

The installed PHP has `php_opcache.dll` available, but the current app does not load it. A temporary server on port 8012 loaded OPcache using process flags only:

```text
-d zend_extension=opcache
-d opcache.enable_cli=1
-d opcache.validate_timestamps=1
-d opcache.revalidate_freq=0
```

Five interleaved `/login` requests per server were measured after separate warmup requests:

| Runtime | Warm request times | Median |
| --- | --- | --- |
| Existing port 8000, no OPcache | 937, 846, 1,041, 1,196, 821 ms | 937 ms |
| Temporary port 8012, OPcache | 272, 265, 212, 188, 173 ms | 212 ms |

The OPcache cold warmup took 2,289 ms versus 1,070 ms for the existing server. Its benefit is from reusing compiled PHP on subsequent requests. Timestamp validation remains enabled to pick up PHP edits. Global `php.ini` was not changed; the temporary server is stopped after the benchmark.

`start-complitrack-ngrok.ps1 -EnableOpcache` can start a server with these process-local settings at the next fresh startup. It probes the extension first, and leaves an already-running port 8000 unchanged with an explanatory warning. The default startup behavior is preserved. A production deployment should configure OPcache in its actual PHP worker service, not rely on this development server.

The saved read-only CLI runtime sample measured 272 ms Laravel bootstrap, 817 ms first database ping, warm pings of 55/53/51 ms, a 72 ms missing-session read and a 61 ms missing-cache read. An earlier sample measured 279/853 ms bootstrap/first ping and 88/113 ms session/cache reads. These show material initial connection and database-backed framework overhead; they do not establish the performance or compatibility of another storage driver. Session and cache storage were left unchanged.

Config and route caching were both generated and loaded successfully using isolated temporary cache paths. The Users options route was present in the cached routing table. These files were removed; the running development caches were not changed. The deployment workflow already uses configuration and route caching. Development keeps them off so `.env` and route changes behave normally.

## Regression validation

| Check | Result |
| --- | --- |
| Full PHP suite with `phpunit.audit.xml` | 114 tests, 1,678 assertions, all passed |
| Frontend/Node unit and version tests | 20 passed |
| Realtime suite including actual MongoDB integration | 15 passed, none skipped |
| Flutter tests | 12 passed |
| Live compiled portal checks | Seven pages across all four roles passed; no JavaScript exceptions or unrendered icons |
| Existing result-modal browser regression | Passed at 1440, 900, 390 and 320 px, plus dashboard/legacy-link cases |
| PHP/Node syntax, PowerShell parser and diff whitespace | Passed |
| Isolated config/route cache generation and loading | Passed |

PHP tests cover user creation/editing/deactivation, searches and pagination, CET responsibilities, preserved credentials, Admin/ATL protections, archive permissions, audit assignments and review, Auditee evidence, CARs, notifications, authentication/sessions and cross-department restrictions. New tests enforce constant related-query counts, empty-list query avoidance, mixed BSON assignment handling, option freshness/authorization, and tunnel asset mode restoration. Browser modal checks exercise tabs, retries, upload navigation, focus trapping and return, and responsive sizing without altering live application records.

Automated database tests use random isolated database names with guarded cleanup. Temporary live-verification sessions were removed. The existing ngrok proxy alone was reloaded to activate static handling; the app and agent were left running.

Qodo review could not run because this session has neither its CLI nor connected managed review tools. A local diff review and the tests above were completed; no independent Qodo review is claimed.

## Reproduction and remaining limits

The diagnostic runners and raw results are in the local ignored `storage/framework/testing/` directory. The original report is `performance-report.md`; original database and browser samples were preserved as `performance-database-before.json` and `performance-browser-before.json`. Final results are `performance-database-results.json`, `performance-results-after.json`, `performance-opcache-results.json` and `performance-portal-results.json`.

Useful commands from the repository root:

```powershell
php vendor/phpunit/phpunit/phpunit -c phpunit.audit.xml
node --test tests/Unit/*.test.cjs scripts/*.test.js
$env:RUN_MONGO_NOTIFICATION_TESTS = '1'
npm.cmd test --prefix realtime
flutter.bat test # run inside mobile/
node scripts/generate-icon-registry.mjs
npm.cmd run build
php scripts/apply-user-list-index.php # inspect; no --apply needed for repeat validation
```

The profiler uses the current development database read-only; the application is not isolated from other activity. The samples do not measure p95 latency, mobile-device performance or concurrent-user capacity. Windows' built-in PHP server can serialize requests: notification fetches can delay the Users request, which explains part of the browser/API gap. OPcache is still opt-in for the next startup. Remaining work includes font delivery, initial MongoDB connection cost, isolated comparisons of session/cache alternatives and a concurrent-load benchmark on the intended production web/PHP server.
