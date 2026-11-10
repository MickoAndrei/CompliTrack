# JWT mechanism review

Reviewed November 10, 2026. Authentication behavior was inspected without changing application code, signing keys, environment files, or live accounts. Tests used a randomly named local MongoDB database with guarded cleanup, an isolated signing key, and suppressed application logging. Credentials and token values were not included in the results.

Subsequent password-reset email work removed the reset-link generation, response field, and logging from password changes and added dedicated PHPMailer delivery; see [PASSWORD_RESET_EMAIL.md](PASSWORD_RESET_EMAIL.md). The other JWT findings below remain outstanding.

## Result

JWT login and API validation work, but logout and credential-change revocation need correction before relying on the complete session lifecycle.

| Finding | Evidence | Impact |
| --- | --- | --- |
| High: JWT logout fails | `app/Http/Controllers/Api/V1/AuthController.php:65` calls the undefined variable `$auth('api')` instead of the authentication helper. An actual bearer logout returned 500; reuse of that JWT returned 200. | Mobile clears its local copy in `finally`, but the server token remains usable. |
| High: password changes create and expose reset credentials | `AuthController.php:89` onward creates a fresh password-reset record, logs the raw reset URL, and returns `test_reset_link`. The diagnostic confirmed that the response contains this credential without printing it. | Anyone with access to that log entry can use the reset URL during its validity window. Remove this testing behavior. |
| Credential changes do not revoke existing JWTs | After a successful password change, the original JWT still accessed `/auth/me` with status 200. The reset-password implementation likewise has no token-version or user-wide invalidation mechanism. | Password changes alone do not terminate a previously obtained bearer token. Define and implement the desired revocation policy, including mobile and realtime. |
| Realtime validation is weaker than API validation | `realtime/src/sockets/auth.js` verifies HS256 signatures and checks an active MongoDB user, but does not require `exp` or consult Laravel's blacklist. A correctly signed token without `exp` was accepted in a direct middleware probe. | Missing-expiry tokens have no scheduled socket expiry. A Laravel-revoked JWT is not independently checked against that revocation at socket authentication. No signing-key compromise was demonstrated. |
| Long access lifetime and no refresh workflow | The effective local TTL is 10,080 minutes (7 days). The configured refresh window is 20,160 minutes, but there is no application refresh route or mobile refresh flow. | A surviving bearer token can remain useful for up to 7 days. Mobile clears credentials on HTTP 401 and requires another login. Refresh configuration alone does not create a refresh workflow. |

## Working controls

- HS256 is configured, and the signing secret is present and at least 32 bytes long. Length alone does not establish randomness or safe secret handling.
- Laravel requires `iss`, `iat`, `exp`, `nbf`, `sub`, and `jti`; API tests rejected expired and tampered JWTs.
- Inactive accounts are rejected by API middleware; authorization reads the current user role rather than trusting the embedded role claim alone.
- The blacklist is enabled and configured to use persistent MongoDB cache storage. The broken logout call prevents that path from using it.
- Web browser login uses the existing session/Sanctum flow; non-stateful API/mobile login receives JWTs.
- Mobile stores tokens using `flutter_secure_storage` and sends a Bearer header.
- Realtime pins HS256, rejects expired tokens and the wrong signing algorithm, checks that the user is active at connection, and schedules disconnect when a valid `exp` exists.

## Verification

- Isolated API diagnostic: **7 cases, 4 passed, 3 failed**. Passed: JWT login/use, tamper rejection, expiry rejection, inactive-account rejection. Failed: logout/revocation, absence of reset credentials in password-change responses, old-token rejection after password change.
- Existing realtime tests: **13 passed, 1 optional MongoDB integration case skipped**.
- Additional realtime middleware probe: valid signed token accepted; expired and HS384 tokens rejected; signed token without `exp` accepted (finding).
- The diagnostic explicitly resets cached authentication guards and JWT parser state between requests to avoid false acceptance caused by the test container retaining the previous request's token.
- No live Atlas authentication, production TLS test, Flutter device execution, or deployment-wide secret/revocation synchronization was performed.

## Suggested fix order

1. Correct JWT logout and test that the same token receives 401 afterward.
2. Remove the testing reset-link generation, response field, and logging from password changes.
3. Add a shared credential/session-version or equivalent revocation policy for API and realtime, covering password changes and resets.
4. Require expiry and appropriate claims in realtime and enforce the shared revocation policy there.
5. Decide whether mobile should require login after expiration or receive a deliberately designed refresh workflow, then choose a shorter access-token TTL accordingly.

Reference: the [jwt-auth guard documentation](https://jwt-auth.readthedocs.io/en/latest/auth-guard/) describes logout invalidation and refresh; the [jsonwebtoken documentation](https://github.com/auth0/node-jsonwebtoken) describes signature/algorithm verification and the optional expiration claim.
