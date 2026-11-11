# Phase 2 performance and styling verification

Measured 11 November 2026 (Asia/Shanghai). This follows [Phase 1](PERFORMANCE_OPTIMIZATION.md) and supersedes its statement that OPcache is only opt-in. These are small diagnostic samples on the existing development machine and remote MongoDB connection, not percentile or production capacity measurements.

## Browser results

Readiness means the Users component has finished loading and rendered its summary and records. Cold/warm below refer to the browser HTTP cache; they do not imply a restarted PHP worker or new MongoDB connection. The before and initial after samples used the same desktop browser instrumentation, route and temporary authorized account.

| Users page | Before | Initial after |
| --- | ---: | ---: |
| Local cold browser readiness | 4,670 ms | 2,001 ms |
| Local warm browser readiness | 3,082 ms | 1,158 ms |
| Local cold document TTFB | 988 ms | 385 ms |
| Local warm document TTFB | 633 ms | 261 ms |
| Local cold first contentful paint | 1,996 ms | 972 ms |
| Local warm first contentful paint | 808 ms | 476 ms |

After removing the temporary profiling router, ordinary browser requests with no diagnostic header measured 2,409/1,260 ms locally and 2,622/1,957 ms through ngrok (cold/warm). The local cold sample followed a PHP restart and overlapped the asset smoke check; it is a verification sample rather than an isolated comparison. An earlier ordinary public sample was 2,169/1,944 ms. The initial instrumented public sample was 3,077/1,613 ms. All completed without JavaScript exceptions. Public cold readiness can still exceed two seconds; network and database variability remain visible.

## What delayed loading

The original browser trace sent notifications at 1,813 ms and Users at 1,832 ms. On the single PHP worker, the requests took 1,384 and 2,756 ms respectively. Warm requests showed the same ordering. Notifications now start after primary records settle: in the initial local after trace, Users took 648 ms, primary readiness was marked at 1,987 ms, and notifications started at 1,993 ms. Notifications continue polling and refresh on focus.

PHP parsing/bootstrap, remote session operations, and database round trips account for much more time than JavaScript execution. Initial local after script execution was 63 ms cold and 42 ms warm; layout was 106 ms cold. The final public trace measured 61/28 ms of script execution. This did not justify replacing Alpine or rewriting the frontend.

The opt-in server profiler separates bootstrap, controller/view action, remaining middleware, session read/write, MongoDB command durations and a first-command wait estimate. These timings overlap and must not be added together. The connection-to-first-command estimate includes PHP work and is an upper bound, not a direct TLS measurement. It records command names/counts, not query contents.

| Diagnostic | Observed result |
| --- | --- |
| Isolated warm login, OPcache off | 291 and 295 ms |
| Isolated warm login, OPcache on | 142 and 142 ms |
| Isolated warm Users API, 20 rows, off | 753 and 755 ms |
| Isolated warm Users API, 20 rows, on | 612 and 572 ms |
| Isolated warm Laravel bootstrap, off/on | About 132–140 / 22–27 ms |
| Actual normal server Users API, 20 rows, warm | 614 and 594 ms |
| Actual normal server cold login | 1,180 ms; first-command wait estimate 653 ms |
| Normal warm session read/write | Usually about 49–65 / 51–60 ms, with occasional outliers |

The sidecar HTTP runner's `cold` group means the first request in that run, not necessarily a fresh process. Runtime status confirmed OPcache in the HTTP worker itself. Normal warm login also had a 507 ms outlier, including a 389 ms session read, so the lowest samples are not guaranteed latency.

## Changes

- Both Windows startup scripts use `scripts/php-development-server.ps1` and enable OPcache by default when available. Settings apply only to the child server: `enable_cli=1`, `validate_timestamps=1`, `revalidate_freq=0`. Global `php.ini` is unchanged. `start-complitrack.ps1 -DisableOpcache` or `start-complitrack-ngrok.ps1 -EnableOpcache:$false` disables it for a new server. Existing listeners are not silently replaced.
- The running application was restarted only after checking its recorded PID, listener and repository-specific command line. Its final router is Laravel's standard `server.php`, with the same OPcache flags. A separate isolated worker verified that a PHP edit appears without a restart under these settings.
- Primary Axios requests gate secondary notification polling, with a bounded five-second fallback. Identical notification reads coalesce only while in flight, scoped to the current user; completed responses are not cached.
- New Users searches abort obsolete requests and reject stale results. The existing debounce and Phase 1 page-local options freshness rules remain in place.
- Page exit aborts notification/list requests and clears sensitive resource state. Returning through browser back/forward cache reloads the server route to reauthorize access. Activity ID preferences are now scoped by user. No QMS contents are added to browser storage.
- The existing Inter, Plus Jakarta Sans and Poppins faces, weights and Unicode coverage are served locally with `font-display: swap`. Inter and Plus Jakarta Sans Latin faces are preloaded. The earlier browser trace showed failed external font requests; after traces load local fonts successfully. Hashed WOFF2 assets receive immutable cache headers and ETags through the proxy. Their bytes and licenses are checked in tests.

The download script records original URLs, SHA-256 hashes and sizes in `public/fonts/sources.json`; it is a maintenance command, not a runtime network dependency. Each family includes its original OFL license from the official Google Fonts repository: [Inter](https://raw.githubusercontent.com/google/fonts/main/ofl/inter/OFL.txt), [Plus Jakarta Sans](https://raw.githubusercontent.com/google/fonts/main/ofl/plusjakartasans/OFL.txt), and [Poppins](https://raw.githubusercontent.com/google/fonts/main/ofl/poppins/OFL.txt).

## Storage and concurrency experiments

An isolated random database with diagnostic payloads compared storage handlers. Initial connection took 856 ms. MongoDB session reads were 61–70 ms and the write 105 ms, versus file reads 0.34–8.19 ms and write 1 ms. MongoDB cache reads were 78–84 ms versus file reads 0.19–11.57 ms. These microbenchmarks demonstrate remote storage overhead, but do not prove deployment compatibility, shared-worker behavior, session migration, or CSRF/flash/expiry equivalence. The application session and cache drivers remain unchanged; live sessions were preserved. The isolated database was dropped by guarded cleanup.

The ordinary application uses the framework's named MongoDB connection, without manually purging or constructing clients per controller request. The PHP driver reuses persistent connections for matching client configuration within a worker; reuse does not eliminate the first connection cost of a fresh worker. See the [PHP driver's connection handling documentation](https://www.php.net/manual/en/mongodb.connection-handling.php). No unsupported connection-pool tuning was introduced.

A temporary round-robin proxy compared four parallel authorized read requests on one versus two isolated OPcache PHP workers:

| Trial | One worker | Two workers |
| --- | ---: | ---: |
| 1 | 1,972 ms | 1,074 ms |
| 2 | 1,520 ms | 928 ms |
| 3 | 1,598 ms | 1,639 ms |

Two workers reduced queueing in two trials; a session-read outlier erased the gain in the third. This is a controlled queueing experiment, not a concurrent-user load test. Windows does not support the built-in server's multiworker environment option; see [PHP's development server documentation](https://www.php.net/manual/en/features.commandline.webserver.php). Production should use a properly configured concurrent PHP service such as PHP-FPM, or IIS/FastCGI on Windows, with OPcache enabled in those actual workers. No production architecture migration was applied.

## Styling incident and regression checks

The temporary profiling router initially discarded Laravel's `false` return when serving an ordinary static request. The result was HTTP 200 with an empty CSS/JavaScript body, which broke the layout. Requests with the profiling header followed a different branch and had concealed this defect during benchmarking. The fallback now returns Laravel's result directly. A regression test compares exact stylesheet bytes with and without the header. The running application was then restored to the standard Laravel router, removing the temporary profiler from ordinary use.

Ordinary login assets were verified as nonempty with correct MIME types on ports 8000 and 8080 and through public ngrok. Browser checks verified seven pages across all four roles, with no script failures, unrendered icons or JavaScript exceptions. The Users page screenshot confirmed the intended sidebar, header, colored summary icons and table layout. A previously open browser may need Ctrl+F5 to discard an empty asset cached during the incident.

| Validation | Result |
| --- | --- |
| Full PHP suite, isolated test databases | 114 tests, 1,678 assertions passed |
| Frontend/Node unit and release tests | 26 passed |
| Realtime suite including MongoDB integration | 15 passed, none skipped |
| Added local-font proxy regression | Passed; all five proxy tests passed |
| Flutter suite | 12 passed |
| Frontend production build | Passed; entry JS 172,315 bytes, 57.59 kB gzip |
| Live role portals and ordinary public asset checks | Passed |
| Responsive result-modal browser fixtures | Passed at 1440, 900, 390 and 320 px, plus dashboard and legacy-link cases |
| OPcache edit detection in one worker | Passed |
| PHP syntax, PowerShell parsing, diff whitespace | Passed |

Qodo was unavailable (no CLI or managed review tools); local diff review and the checks above were used. No independent Qodo review is claimed. Existing authorization, authentication, CSRF, notification, archive and audit workflow coverage passed. No application records or credentials were changed by this phase.

## Reproduction and artifacts

Diagnostic code is in `scripts/performance/`. `router.php` is opt-in, requires a per-process token and loopback caller, and is not an application route. Normal startup does not enable it. Its token and temporary browser cookies must remain private and are never report contents. The temporary benchmark workers and authenticated sessions were removed after verification; the normal app, proxy, realtime service and ngrok remain running.

Ignored local artifacts under `storage/framework/testing/` include `phase2-http-before.json`, `phase2-http-normal-after.json`, `phase2-browser-before.json`, `phase2-browser-after.json`, `phase2-browser-ngrok-after.json`, `phase2-browser-ordinary.json`, `phase2-browser-final-local.json`, `phase2-browser-final-public.json`, `phase2-storage.json`, `phase2-concurrency.json`, `phase2-opcache-edits.json`, browser traces, the Users screenshot, and `phase2-php-tests.xml`. Traces may contain application URLs; keep them local.

For a new benchmark, create temporary authorized sessions locally, start dedicated diagnostic workers with the helper's `-EnableDiagnostics` switch, then run `node scripts/performance/http.cjs <label> <ports>` and `node scripts/performance/browser.cjs <label> <base>`. Use `--no-profile` for ordinary-browser verification. Run storage/concurrency experiments only with their isolated fixtures and perform their guarded cleanup. The HTTP runner sets a local stateful origin for its authenticated sidecar requests; it does not alter application authentication configuration.

The next material improvements require reducing remote database/session latency and measuring the intended production server under representative concurrent load. The current changes reach roughly one to two seconds for warm Users loads in these samples, while cold loads and network outliers still exceed that target.
