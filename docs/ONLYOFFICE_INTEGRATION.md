# Existing ONLYOFFICE integration

CompliTrack reuses its existing Docker Document Server and `DocsAPI.DocEditor` integration. No replacement editor installation, new document server, database reset, or new application collection was introduced.

## Deployment inspected on 2026-11-14

| Setting | Verified value |
| --- | --- |
| Container | `complitrack-documentserver-1` (`14b4fdc08f8a`) |
| Image / running version | `onlyoffice/documentserver:10.0.0`, build 169 |
| Compose file | `docker-compose.documents.yml` |
| Published port | `127.0.0.1:8082 → 80` |
| Docker network | `complitrack_default`, service alias `documentserver` |
| Persistent volumes | `complitrack_onlyoffice_data`, `complitrack_onlyoffice_logs`, `complitrack_onlyoffice_library`, existing custom-font volume |
| Restart policy | `unless-stopped` |
| JWT | Browser, inbound and outbound enabled; `Authorization` header; signing secret retained in the existing private env configuration |
| Laravel → Document Server | `ONLYOFFICE_INTERNAL_URL=http://127.0.0.1:8082` |
| Document Server → Laravel | `ONLYOFFICE_APP_URL=http://host.docker.internal:8000` |
| Public browser editor | `ONLYOFFICE_URL=https://undercook-ecosystem-detract.ngrok-free.dev/document-server` |
| Loopback browser editor | `ONLYOFFICE_LOCAL_URL=http://127.0.0.1:8082` |

The container successfully fetched signed document URLs from Laravel. Its persistent volumes and signing secret were preserved. The existing `start-complitrack-documents.ps1` remains the startup entry point. `start-complitrack-ngrok.ps1` starts the existing reverse proxy and ngrok tunnel; `realtime/src/devProxy.js` already forwards the Document Server prefix and WebSockets. TLS terminates at ngrok. Certificate verification and JWT remain enabled.

The configured ngrok hostname did not resolve through this machine's default DNS resolver. Public DNS at `1.1.1.1` resolved it, and the public editor API returned HTTP 200 using that verified address with normal HTTPS certificate verification. This is an outstanding host DNS issue, not a reason to disable TLS. Local CompliTrack requests now select the loopback editor URL instead of depending on ngrok DNS. Public HTTPS requests retain the configured HTTPS editor URL; they never fall back to HTTP loopback.

Read-only diagnostics (no secret output):

```powershell
php artisan documents:check --local
php artisan documents:check
```

Configure these URLs for the actual deployment rather than copying a browser's localhost address into a Docker callback URL. The local browser override applies only to HTTP requests using `localhost`, `127.0.0.1` or IPv6 loopback. A custom external Document Server remains configurable through `ONLYOFFICE_URL`.

## Configuration and authorization

Authenticated `POST /api/v1/documents/{id}/editor` accepts `mode: edit|view`, plus the existing authorized audit-plan context query parameters. The backend issues the file type, document type (`word`, `cell`, `slide`, `pdf`), immutable source URL, collaborative key, title, user, callback URL, mode and signed token. Permissions are under `document.permissions`, as specified by the [official Docs API configuration](https://api.onlyoffice.com/docs/docs-api/usage-api/config/document/); they are not an `editorConfig.permissions` field. No signing secret is sent to the browser.

`DocumentEditorAccess` intersects existing document/download permissions, audit context, verified organization scope and protected classifications. Department Chairs and Secretaries can edit permitted files in their own authorized departments. College Secretaries and Cluster Administrators need their existing organizational/folder permissions. Admin/ATL scope comes from explicit reviewer assignments; accepted or archived assignments are read-only. A Department Chair who also has an Admin role retains department scope. Protected owner/restricted records require the owner's or an explicit document access grant; administrative responsibility alone does not override classification. Auditors remain read-only and must have existing assigned-audit evidence access. Ordinary faculty/staff do not acquire editing rights.

Capabilities are rechecked when issuing configuration, fetching source bytes, reading save status and accepting a save. Source retrieval requires an outgoing ONLYOFFICE JWT whose signed URL matches the specific session capability. Configuration JWTs cannot be reused as source-download JWTs. File paths, encryption details and workspace internals are not exposed through document presentation.

## Collaboration and saving

An active editing workspace is recorded on the original version-chain anchor in the existing `archive_documents` collection. Authorized editing participants receive the same key while co-editing that workspace, including after force saves. Their individual random capability URLs and identities are kept in the existing private file cache. Final saving closes the workspace; reopening the saved revision generates a new editing key. Read-only viewers use separate immutable revision keys and cannot reserve, replace or block editing workspaces. Sessions expire after eight hours. Earlier versions cannot start competing edits, but authorized read-only history viewing remains available.

Callbacks implement [ONLYOFFICE's official callback protocol](https://api.onlyoffice.com/docs/docs-api/usage-api/callback-handler/):

| Status | CompliTrack behavior |
| --- | --- |
| 1 | Tracks editing activity; reconnect after a no-change close can resume the workspace |
| 2 | Retrieves and persists changed bytes, then closes the editing workspace |
| 3 / 7 | Records the server save error and exposes it through authenticated save-status polling |
| 4 | Closes without creating a revision |
| 6 | Persists a force-save revision while retaining the collaborative key |

Signed claims are authoritative; unsigned request-body alterations do not change the save. The backend validates the workspace key, participant mode and current contributing writers, organization, current revision/checksum, extension and file package signature. Fetches are limited to configured Document Server origins and `/cache/files/`, with redirects disabled, a 20 MB limit and normal TLS verification. Credential-bearing URLs, traversal paths and unrelated hosts are rejected.

Existing encryption, storage and version creation services are reused. A MongoDB transaction writes the new version, durable callback journal, workspace state and activity record together. A workspace lock and anchor revision update serialize saves; a changed latest revision rejects a conflicting save. Duplicate callback events and identical current bytes do not create duplicate versions. Failed transactions clean up newly orphaned encrypted files. Callback acknowledgement is `error: 0` for handled events and `error: 1` when persistence fails; invalid authentication or payloads receive an HTTP rejection.

Original bytes, department/college ownership, visibility, explicit access grants, audit sharing, reference metadata and previous versions are retained. New content does not inherit Audit Plan approval. Existing plan IDs and audit/evidence references continue to point to their original records.

The UI retains Drive-style navigation and provides Open, Preview, Edit with ONLYOFFICE, Versions and Details. Its large editor view shows the filename, location, preview/history controls and Back to QMS Documents. A Saved indicator appears only after the backend reports a stored version. Editor-side Save or “All changes saved” is not proof of CompliTrack persistence. Final save can arrive after the editor closes; status polling continues briefly to confirm it.

## Verification and limitations

Tests use isolated MongoDB databases and temporary encrypted files. `OnlyOfficeMongoTest` covers role and department isolation, Admin scope, protected classifications, read-only auditors, local/public URLs, shared keys, live revocation, signed retrieval, malformed/forged callbacks, supported office formats, duplicate events, original preservation and conflicting versions. Existing archive, folder, audit-plan hierarchy and authentication-route tests provide regression coverage.

`tests/Browser/onlyoffice-live.cjs` exercises the actual existing container with copied blank templates. DOCX text edits, XLSX cell edits and PPTX slide additions were stored through real callbacks; decrypting the saved versions verified edited contents and exact preservation of all original template bytes. Two authenticated Chair/Secretary editor instances initialized against the same collaborative key. Browser automation distinguishes force-save confirmation from a final-save callback after close. The isolated fixture scripts are CLI-only; no fixture login route is registered in the application.

The ordinary QMS browser suite also passes at 1440, 768, 390 and 320 pixels, preserving the audit-plan folders and existing preview flows. Production builds and PHP syntax checks pass. Qodo review remains unavailable because its prior repository credential failure prohibited automatic retries; no external clean review is claimed.

The existing container was restarted without recreation. Its ID remained unchanged, Docker reported it healthy, and a saved DOCX subsequently reached the actual editor's read-only Document Ready event. Internal health and the local browser Docs API both returned HTTP 200 after restart.

Regression runs passed at substantive checkpoints: 13 tests / 272 assertions for editor, folder, audit-plan and route coverage, followed by 7 tests / 139 assertions covering the final URL selection and scope checks. Later read-only workspace refinements have their own focused format/status regression. Browser verification includes actual two-user editor initialization, callback-confirmed storage, and saved DOCX reopening in read-only mode.

Final read-only workspace/security checks passed: 6 tests / 118 assertions. The subsequent transaction/status refinement passed its focused DOCX/XLSX/PPTX save-and-history test: 1 test / 70 assertions. The isolated live database, encrypted fixture files and temporary Laravel server on port 8017 were removed after verification; the main application and existing Document Server remain running.
