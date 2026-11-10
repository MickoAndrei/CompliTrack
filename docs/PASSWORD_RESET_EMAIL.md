# Password-reset email

Password-reset and account-setup links use PHPMailer 7 through Laravel's dedicated `password_reset` mailer. This mailer does not use `MAIL_MAILER=log` or a log failover. SMTP debug output is disabled, and delivery exceptions omit raw SMTP responses, message bodies, and credentials. Password changes no longer generate, return, or log a testing reset link.

Reset tokens continue to be hashed in MongoDB, expire after ten minutes, and are deleted after successful use. The PHPMailer change does not modify existing account passwords. Old application log entries are not deleted by this change.

## Personal Gmail setup

Enable 2-Step Verification on the sending Google account and create an App Password through [Google's App Password settings](https://myaccount.google.com/apppasswords). Set the following locally in `.env`:

```dotenv
MAIL_MAILER=phpmailer
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_ENCRYPTION=tls
MAIL_AUTH_TYPE=LOGIN
MAIL_USERNAME=yourname@gmail.com
MAIL_FROM_ADDRESS=yourname@gmail.com
MAIL_PASSWORD="your-16-character-google-app-password"
```

Use the generated App Password, not the normal Gmail sign-in password. PHPMailer removes display spaces between App Password groups. Keep the real value in `.env` only. Microsoft OAuth variables are unused in this mode. Then run `php artisan config:clear` and `php artisan mail:check` before retrying the reset. See [Google's instructions](https://support.google.com/mail/answer/185833?hl=en).

## Microsoft 365 setup

Provider defaults are `smtp.office365.com`, port `587`, STARTTLS, and `XOAUTH2`. Populate these values in the local `.env`, using the sending mailbox and your organization's Entra application credentials:

```dotenv
MAIL_MAILER=phpmailer
MAIL_HOST=smtp.office365.com
MAIL_PORT=587
MAIL_SCHEME=smtp
MAIL_ENCRYPTION=tls
MAIL_AUTH_TYPE=XOAUTH2
MAIL_USERNAME=your-sending-mailbox@your-domain
MAIL_FROM_ADDRESS=your-sending-mailbox@your-domain
MAIL_FROM_NAME="CompliTrack"
MAIL_OAUTH_TENANT_ID=your-tenant-id
MAIL_OAUTH_CLIENT_ID=your-app-client-id
MAIL_OAUTH_CLIENT_SECRET=your-app-client-secret-value
MAIL_OAUTH_REFRESH_TOKEN=
```

Keep real credentials out of source control and chat messages. Set `APP_URL` to the actual CompliTrack URL recipients can reach; `localhost` links only work on the server machine itself.

For unattended organizational Microsoft 365 delivery, an administrator must register an Entra application, grant the Exchange Online `SMTP.SendAsApp` application permission and admin consent, register its service principal in Exchange, and grant access to the sending mailbox plus the applicable SendAs permission. SMTP AUTH must be permitted for the organization/mailbox. The token request uses `https://outlook.office365.com/.default`. Follow Microsoft's [SMTP OAuth instructions](https://learn.microsoft.com/en-us/exchange/client-developer/legacy-protocols/how-to-authenticate-an-imap-pop-smtp-application-by-using-oauth) and [Microsoft 365 SMTP configuration guide](https://learn.microsoft.com/en-us/Exchange/mail-flow-best-practices/how-to-set-up-a-multifunction-device-or-application-to-send-email-using-microsoft-365-or-office-365).

For delegated OAuth (including personal Outlook accounts), the provider can exchange an existing `MAIL_OAUTH_REFRESH_TOKEN` instead of using application credentials. Obtain it through your organization's approved OAuth flow with SMTP.Send/offline_access consent, use the appropriate tenant and Outlook SMTP host, and manage refresh-token rotation through that authorization process. This implementation does not provide an interactive OAuth consent screen or persist replacement refresh tokens.

If your tenant explicitly permits password-based SMTP, `MAIL_AUTH_TYPE=LOGIN` with `MAIL_PASSWORD` is supported. OAuth is the configured default; do not disable tenant security policies just to enable password authentication.

After filling in the configuration:

```powershell
php artisan config:clear
php artisan mail:check
```

The check prints missing field names without printing credentials or sending mail. Then request a reset through CompliTrack for an authorized account whose mailbox you control, confirm receipt, open the complete email link, and verify the reset. Microsoft credentials/consent and actual inbox delivery cannot be verified until this configuration is supplied.

## Validation

Reset email links use `APP_URL` even when requested from localhost. For the configured public tunnel, run `powershell -ExecutionPolicy Bypass -File .\start-complitrack-ngrok.ps1`; its default domain is `undercook-ecosystem-detract.ngrok-free.dev`. The script disables public debug output and ngrok request inspection and forwards through a local proxy. It uses the existing app/realtime ports (8000/4000) and starts the app if necessary. Build frontend assets before exposing the site; the script backs up any Vite hot-file marker so external clients use built assets. An existing ngrok agent must be stopped before relaunching this helper.

The original Check your inbox tab polls every five seconds with a separate random completion token and returns to `/login?reset=success` after a successful web/API reset. The status token cannot reset a password, does not reveal the reset link or account existence, and expires after ten minutes. State is stored temporarily in the configured cache, with no application schema migration. Old reset emails sent before the `APP_URL` change retain their original URL; request a fresh link.

- Notification rendering and PHPMailer transport are tested together with a mocked SMTP connection, including HTML/text content, the recipient, TLS, and bypassing the default log mailer.
- A local SMTP sink exercises real PHPMailer SMTP commands without sending external mail or creating live reset records: `node tests/Browser/password-reset-smtp.cjs`.
- Tests cover sanitized SMTP/OAuth failures, app-only and delegated OAuth exchange, one-time/expired reset links, and the absence of new reset credentials/log output during password changes.
- Installation added only PHPMailer to `composer.json`/`composer.lock`. The existing PHP MongoDB extension version does not satisfy the already-locked MongoDB package requirement, so dependency installation required `--ignore-platform-req=ext-mongodb`. The PHP MongoDB extension should be brought into line with that existing requirement; no MongoDB packages were unlocked by this change.
