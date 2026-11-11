# Realtime notifications and Firebase removal

## Discovery and removal

Read the entire README first, then inspected manifests/locks, MongoDB configuration, Sanctum/JWT authentication, notification controllers/models, Node socket authentication/watchers, web refresh logic, Flutter startup/socket client, platform folders, examples, startup script, and Git history filenames/private-key markers.

Firebase was used only for optional Node device push and unused Flutter messaging dependencies. Authentication is Laravel Sanctum/JWT; no Firebase Authentication, Firestore, Realtime Database or Storage implementation was found. Flutter had no Firebase initialization and its messaging-service file was empty.

Removed with package managers:

- Node `firebase-admin` and its unused transitive packages via `npm.cmd uninstall firebase-admin`; regenerated `realtime/package-lock.json`.
- Flutter `firebase_core`, `firebase_messaging` and their transitive packages from pubspec; `flutter.bat pub get` regenerated the lockfile and plugin metadata.
- Added direct `jsonwebtoken` dependency because existing socket authentication previously obtained it transitively. Added `socket.io-client` as a development-only transport test dependency.
- Updated compatible transitive body-parser after npm identified a high-severity advisory; final install audit reported zero vulnerabilities.

Deleted Node Firebase initialization/push services, empty Dart messaging service, obsolete Node compliance notification producer, token upload/storage code, Firebase example/startup configuration, and obsolete Flutter FCM settings card. Removed fcmSent from new-record models/producers/seeder. Existing database fields/tokens were not deleted or migrated. Removed outdated requirements from README and file structure documentation. Authentication, accounts, roles and existing notification screens remain.

Final active-source/manifest/lock/platform/config/example scan for firebase/fcm/firestore returned no matches. README explains the removal; prior implementation reports are historical. Git-ignore credential exclusions and unused legacy CSS selector names are harmless references, not runtime integrations. No matching committed service-account/config files or private-key markers were found by the performed filename/history searches. This is not proof that no secret ever existed under an arbitrary filename. No credentials were printed and no Git history was rewritten; if independently discovered credentials were committed, revoke them and arrange approved history remediation.

## Root causes and implementation

Node previously required replica-set name `rs0`, rejecting the application's differently named Atlas set. It also filtered notifications to `admin_broadcast`, excluding all audit-review records. Laravel's connectivity diagnostic incorrectly called an unsupported PHP change-stream `close()` method, making a reachable Atlas replica set appear unable to stream. Fixed diagnostic by releasing the stream normally; final check reports **Change streams: Available**.

The portable transport now always polls persisted notifications, so standalone MongoDB also works without watch/change-stream support. No replica-set-name restriction remains. Actual standalone topology was not provisioned; tests prove polling requires no watch() and real MongoDB delivery works on the available Atlas deployment.

Laravel writes stable, deduplicated records for audit assignment; combined checklist/results submission for review; audit acceptance; return for revision; compliant/noncompliant standalone check creation; and CAR status updates. Checklist and audit submission are one existing action and produce one reviewer notification, not duplicate alerts. Invalid/unauthorized/stale operations do not emit successful workflow notifications. CAR events use a stable status-history position, so later reopen cycles are distinct events.

Node polls up to 100 undelivered records per pass every two seconds, atomically claims an expiring 30-second lease, checks the recipient is active, sends only to that recipient's authenticated room, then marks dispatch. All notification types are handled. Worker errors retry; expired leases can be recovered. A sparse unique dedupeKey index prevents duplicate persisted events. Normal repeat polling does not repeat dispatch. Socket event IDs are stable; mobile suppresses replayed IDs. JWT signature/algorithm/expiry and active user are checked; long-lived sockets disconnect at expiry. No client-controlled room subscription is introduced.

Web notification pages retain their UI and refresh the authorized HTTP endpoint every ten seconds while visible. Existing header refresh behavior remains. Flutter retains Socket.IO foreground notifications and HTTP retrieval. Closed-app OS push has been removed, not silently replaced with an unsupported promise.

Node now uses root application MongoDB/JWT configuration first, with realtime-file fallback and explicit process overrides. This avoids the observed stale localhost connection in realtime/.env without editing either environment file. Fixed an existing incorrect package.json path in socket initialization that prevented actual startup. Added a testable start/stop export and health notificationTransport=polling.

## Database changes and preservation

No live audit assignments or workflow fixtures were inserted. No users/departments/checks/notifications were deleted or reset. CET membership and all four audit indexes verified intact; audits remains empty. Root `.env` checksum unchanged: `8E5D78F0DB3EF5B1D2E0F7C52FFA9DBA5C1D0000B6E7A72956DDC2A23FB47A94`.

The main notifications collection had only `_id_`, so its required indexes were added after duplicate preflight and a verified private backup:

- `notifications_dedupe_unique`: `{dedupeKey:1}`, unique/sparse.
- `notifications_realtime_pending`: `{realtimeDeliveredAt:1,realtimeLeaseUntil:1,createdAt:1}`.

Backup: `storage/app/private/database-backups/notifications-20261106-092511-de5aa7/`, containing Canonical Extended JSON documents, prior index metadata and result verification. Document contents were unchanged by the index operation. No destructive migration/backfill required. New transport lease/dispatch fields are added only when the service handles notifications; they are hidden from API output. No Firebase data outside this application was accessed or deleted.

## Files changed for this task

- Laravel: Notification model; NotificationDispatchService; ComplianceCheckService; Web AuditController; API AuthController, ArchiveController and NotificationController; DemoDataSeeder; connectivity command.
- Node: package/lock, environment example, config/env and db, server, socket auth/server, notification model and polling worker; removed Firebase config/push and old check-change producer; updated watcher tests; new socket and MongoDB integration tests.
- Web: notification resource refresh timer in resources/js/pages.js, preserving lifecycle/focus cleanup.
- Flutter: pubspec/lock/plugin metadata; removed empty messaging service and obsolete settings card; removed token storage/login fields; bounded socket replay suppression; smoke test uses isolated dotenv values instead of local URLs.
- Database: init.js index declaration; backup-first scripts/apply-notification-indexes.php; read-only scripts/inspect-notification-setup.php.
- Docs/startup: README, FILE_STRUCTURE.md, start-complitrack.ps1, this report.

## Commands and final results

- `npm.cmd uninstall firebase-admin`; `npm.cmd install jsonwebtoken --save`; `npm.cmd install --save-dev socket.io-client`; `npm.cmd update body-parser`: completed in realtime/. No unrelated direct packages removed. Initial sandbox registry access failed; approved outside-sandbox install succeeded. Final npm package audit reported 0 vulnerabilities.
- `flutter.bat pub get`: completed, removed Firebase packages from lock/plugin metadata.
- `php vendor/phpunit/phpunit/phpunit -c phpunit.audit.xml`: **14 tests, 176 assertions passed**, 33.445s. Exercises persistence/dedupe and assignment/submission/accept/return types plus existing authorization, evidence, CAR and Auditor workflow. One preceding run overlapped Vite's manifest replacement and failed an error-page render; final run after build passed.
- `php vendor/phpunit/phpunit/phpunit -c phpunit.audit.xml --filter=legacy_records_notifications`: after the stable CAR-key adjustment, **1 test, 27 assertions passed**.
- In realtime/: `$env:RUN_MONGO_NOTIFICATION_TESTS='1'; npm.cmd test`: **11 tests passed**, including actual service startup, real isolated MongoDB persistence and real authenticated Socket.IO delivery; invalid JWT rejection, recipient room isolation, all relevant event types, inactive-user denial, lease recovery, duplicate polling, no watch() requirement. Only generated test databases receive fixtures and cleanup.
- `npm.cmd run check` in realtime/: passed.
- `flutter.bat test`: **12 tests passed**. An initial smoke test depended on local .env URLs; isolated test configuration fixed it. The installed dotenv API is loadFromString, which the final fixture uses.
- `flutter.bat analyze`: **No issues found** (16.9s final run).
- `npm.cmd run build`: passed, 1605 modules. `php artisan view:cache`: passed. PHP controller syntax check passed. `git diff --check`: no whitespace errors, CRLF normalization warnings only.
- `php artisan mongodb:check`: reachable Atlas replica set; corrected diagnostic reports streams Available.
- `php scripts/apply-notification-indexes.php`: backup and additive indexes successful, recordsUnchanged=true.
- `php scripts/inspect-notification-setup.php`: final main DB CET value and all four audit/two notification indexes verified read-only, auditCount=0.
- Active source/manifest/lock/example/platform searches: no remaining Firebase integration matches. Filename/private-key-marker Git-history searches produced no credential file matches.

## Operational limits and next steps

Restart the existing realtime service to load the new source (`npm start` in realtime/ or the existing process manager restart). If installed elsewhere, run npm ci there. No .env edit is required when the root app MongoDB/JWT configuration is present; explicit service overrides must intentionally match Laravel. CORS must permit the intended clients. Foreground delivery and offline retrieval are supported; background OS push is not.

Manual browser/device acceptance remains: log in through existing auth, trigger an authorized workflow action, confirm its notification appears for the correct recipient, refresh/reconnect and verify persistence. No live assignment was created for this task. Real transport was verified in isolation, not with a production user's browser.

Transport dispatch is best effort/at least once around process interruption, not a network receipt guarantee. Offline clients retrieve persisted notifications through HTTP. A crash between socket emit and marking dispatch can replay the same ID; the mobile client suppresses duplicates. Business writes and notification persistence are separate MongoDB writes rather than a transactional outbox; a process/database failure between them can require operational retry/reconciliation. The implementation does not claim exactly-once delivery or Firebase-equivalent closed-app push.
