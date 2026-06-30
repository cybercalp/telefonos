# Tasks: Security and Usability Fixes

## Review Workload Forecast
Decision needed before apply: No
Chained PRs recommended: No
Chain strategy: size-exception
400-line budget risk: Low

## Phase 1: Session Security Configuration
- [x] **Configure strict session cookies**: Re-order `lib/session_security.php` to set `session.use_strict_mode` and cookie parameters before starting the session.
- [x] **Bootstrap session security globally**: Prepend `require_once __DIR__ . '/../lib/session_security.php';` at the top of `private/config.php`.

## Phase 2: CSRF Validation Reset
- [x] **Reset CSRF on GET in login.php**: Set `$_SESSION['csrf_token_ok'] = true` when `REQUEST_METHOD` is `GET`.
- [x] **Reset CSRF on GET in change_pwd.php**: Set `$_SESSION['csrf_token_ok'] = true` when `REQUEST_METHOD` is `GET`.
- [x] **Reset CSRF on GET in rescue.php**: Set `$_SESSION['csrf_token_ok'] = true` when `REQUEST_METHOD` is `GET`.
- [x] **Reset CSRF on GET in totp.php**: Set `$_SESSION['csrf_token_ok'] = true` when `REQUEST_METHOD` is `GET`.

## Phase 3: Secure Connections (LDAP & API TLS/SSL Verification)
- [x] **Update configuration templates**: Add `ldap_reqcert` and `curl_ca_bundle` placeholders and instructions in `private/config.ini`.
- [x] **Implement dynamic config options**: In `private/config.php`, dynamically parse, set `LDAPTLS_REQCERT`, and expose `$curl_ca_bundle`.
- [x] **Enforce TLS on token acquisition**: Update cURL verification and custom CA bundle validation logic in `lib/get_token.php`.
- [x] **Enforce TLS on presence synchronization**: Update cURL verification and CA bundle options for token/data endpoints in `lib/sync_presence.php`.
- [x] **Enforce TLS on stream/cURL tests**: Update stream context parameters and cURL options in `lib/test_native.php`.

## Phase 4: Import Cleanup and Verification
- [x] **De-duplicate index imports**: Remove duplicate `checkip.php` import and redundant comments on lines 16-17 of `index.php`.
- [x] **Verify session settings**: Confirm `HttpOnly`, `SameSite=Lax`, and `Secure` (over HTTPS) flags are present in the response headers.
- [x] **Verify CSRF recovery**: Submit form with invalid CSRF token, redirect, and verify subsequent GET re-enables login.
- [x] **Verify SSL/TLS enforcement**: Run LDAP and Saviacloud test scripts in production mode to assert validation failure/success scenarios.
