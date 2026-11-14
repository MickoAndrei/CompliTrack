# Browser tab sessions

Open CompliTrack in a new browser tab and sign in to another account. Signing in
or out affects that tab's session. Refreshing or navigating within the tab keeps
its account. There is no additional sidebar entry.

`public/js/tab-session.js` runs before application content. It stores a random,
public context identifier in `sessionStorage` and carries it as `_tab` through
navigation, forms, Fetch, and Axios. Laravel's `UseTabSession` middleware selects
a separate encrypted, HttpOnly session cookie for that context before either
the web middleware or Sanctum starts the session. The web guard also namespaces
its remember-me cookie. The identifier is not an authentication token.

Axios sends the page's CSRF meta token instead of the browser-wide XSRF cookie.
Redirects and avatar URLs preserve the context. Mobile bearer-token requests
continue to use the existing API guard.

Modern browsers use Web Locks to give duplicated tabs a fresh context before
application startup. BroadcastChannel provides a fallback where Web Locks is
unavailable; duplicate detection in that fallback can depend on background-tab
message scheduling. JavaScript and session storage must be enabled. Closing a
tab discards its context; opening a fresh tab requires a fresh sign-in. Existing
tabs should be refreshed after deployment.

Verification:

```powershell
php artisan test --compact --filter='TabSessionTest|ExampleTest|TunnelAssetsTest'
npm.cmd run build
node tests/Browser/tab-session.cjs
```

The browser check uses headless Microsoft Edge on Windows, an isolated test
profile, a local HTTP fixture, and the production JavaScript bundle. The Laravel
tests exercise two identities in one cookie jar without real user accounts.
