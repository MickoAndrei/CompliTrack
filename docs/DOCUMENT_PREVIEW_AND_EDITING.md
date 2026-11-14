# Document preview, editing and version comparison

Preview opens PDFs from the original bytes and renders DOCX pages in an isolated
browser frame. Word previews offer Fit width and 100%, 150% and 200% zoom.
Document formatting depends on the available fonts and renderer; the download
retains the original file. Preview loads wherever the preview dialog is present,
including the Admin archive explorer.

In Details & history, **Upload revised version** keeps the earlier archive files.
**Compare versions** displays any two authorized digital versions side by side
(stacked on narrow screens). Access to each file is checked separately. Closing
comparison cancels requests and releases its temporary browser URLs.

## Local editing server

The integration uses ONLYOFFICE Docs 10.0.0. Install and start Docker Desktop with
Linux containers / WSL 2. Windows may require a restart when enabling WSL.
Run from PowerShell:

```powershell
.\start-complitrack-documents.ps1
```

The script starts only `docker-compose.documents.yml`, leaving the database
compose stack alone. It generates a private JWT secret in
`storage/app/private/onlyoffice.env`, waits for the server health check and then
updates the app's ONLYOFFICE settings. It never prints the secret.

The document server binds to `127.0.0.1:8082`. Laravel must be reachable from its
container as `http://host.docker.internal:8000`; change `ONLYOFFICE_APP_URL` if
the app runs elsewhere. For HTTPS/ngrok, the startup script sets the browser URL
to `APP_URL/document-server`; restart the existing development proxy after
updating its code so it forwards that path and WebSocket traffic. The direct
document server port stays bound to loopback. The proxy strips app cookies from
document server requests.

**Edit document** is available only for PDF/DOCX archives that the existing QMS
permissions allow the account to manage. Super Admin remains restricted to
folder/statistics access under the existing policy. Use an authorized Audit Team
Leader account to edit files; document editing does not grant a new role.

Use Save inside the editor. A signed server callback downloads the revised file
only from the configured document server, validates its format and size, and
passes it through the existing encrypted archive version endpoint. Original
files and their audit links remain intact. Duplicate callbacks with identical
file contents do not create duplicate versions. New versions inherit document
permissions and require new audit shares, just like uploaded revisions.

Editing sessions expire after eight hours. File delivery and saving recheck the
account's current management access. The browser checks saved revisions while
the editor is open and for up to 90 seconds after closing, because final saves
can arrive after the editing window closes. Verify the revised version in
history before leaving the page. Concurrent changes based on an old revision
are rejected by the existing unique version index; refresh and reopen the latest
revision in that case.

The local document server must be healthy to verify live editing. A mocked save
callback test verifies encrypted revisions and authorization without installing
the server.

References: [ONLYOFFICE server installation](https://helpcenter.onlyoffice.com/docs/installation/community/docker/installing/install),
[editor configuration](https://api.onlyoffice.com/docs/docs-api/usage-api/config),
[saving callbacks](https://api.onlyoffice.com/docs/docs-api/usage-api/callback-handler).
