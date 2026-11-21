# MongoDB Database Switch

The Super Admin → System Settings control switches between the configured Local and Remote MongoDB targets. It is supported only when this checkout is running on Windows under `start-complitrack.ps1`; other platforms reject the action without changing the active connection.

```env
MONGODB_ACTIVE_ENVIRONMENT=local
MONGODB_LOCAL_URI=
MONGODB_LOCAL_DATABASE=
MONGODB_REMOTE_URI=
MONGODB_REMOTE_DATABASE=
```

Set `MONGODB_ACTIVE_ENVIRONMENT` only after confirming which target the application currently uses. When it is `local` or `remote`, Laravel and the realtime service use that target's URI/database pair. The legacy `MONGODB_URI` and `MONGODB_DATABASE` remain intact as a fallback and are not rewritten during a switch. Credentials and connection strings remain server-side.

## What the button does

The POST uses Laravel web middleware and CSRF protection, the dedicated `database.switch` permission, active Super Admin authorization, rate limiting, and typed confirmation. The request store serializes submissions and audits the administrator, source, target, request time, and final outcome. The browser supplies only the target enum and confirmation; it cannot provide a shell command or script path.

The Windows worker verifies that Laravel, the scheduler, and Socket.IO were started by this checkout's startup script and that no unmanaged Laravel queue worker is running. It probes both databases, stops the managed services, changes only `MONGODB_ACTIVE_ENVIRONMENT`, rotates the symmetric `JWT_SECRET`, changes the cache namespace, and verifies the target. It clears MongoDB-backed sessions, remember-me credentials, and Sanctum tokens from both configured databases so all users—including the requesting Super Admin—must sign in again. Rotating the JWT secret invalidates existing mobile/API JWTs. ONLYOFFICE uses its separate secret and is not changed.

The worker restarts Laravel, the scheduler, Socket.IO, and the local Vite service through `start-complitrack.ps1` with migrations, seeders, logout, and frontend rebuild explicitly skipped. It verifies Laravel, MongoDB, and Socket.IO health before resolving the request. If verification fails, it restores the source target and restarts/verifies it. If rollback also fails, the request remains pending for operator recovery; inspect `logs/database-switch.log` and the pending stage before resolving it.

Login invalidation runs before the final service-health check. If startup fails after that stage and the worker rolls back, the previous database remains active but sessions and JWTs have already been invalidated; all users must sign in again. Health-check timeouts record the last failed check category in `logs/database-switch.log`.

An older maintenance-only request can be executed only from the local console with a fresh typed confirmation:

```powershell
php artisan complitrack:database-switch:execute-pending "SWITCH TO REMOTE"
```

Use `SWITCH TO LOCAL` when that is the pending target. The command accepts only an existing maintenance request or a request left in `rollback_failed`, rechecks both connections, and launches the same fixed worker. It does not accept a browser command or arbitrary script path.

No business records are copied, merged, synchronized, migrated, seeded, or deleted. The only database changes are the requested logout invalidations (session records, remember tokens, and API token records). Cache entries are isolated under a new namespace rather than deleted.

## Deployment and failure conditions

- Start the local application with `start-complitrack.ps1` so its managed PID files identify the web, scheduler, and realtime processes. The worker refuses to stop services it cannot verify.
- If an unmanaged queue worker is running, the request is cancelled before changing `.env`; stop/manage that worker separately and retry.
- The application and realtime endpoints are briefly unavailable during restart. An ngrok or Cloudflare Tunnel may remain connected, but it does not bypass Super Admin authorization or make the application available while its local server is stopped.
- Linux/PM2 production automation is not enabled. The current deploy workflow does not provide a coordinated Laravel, scheduler, session, and realtime cutover; requests there are rejected without changing the active database.
- A non-HMAC JWT algorithm or unsupported session driver prevents the automatic logout step and triggers rollback.

The worker uses a fixed, repository-owned PowerShell script and fixed service scripts; it never executes browser-supplied commands. It does not run the normal startup bootstrap path, migrations, or seeders.
