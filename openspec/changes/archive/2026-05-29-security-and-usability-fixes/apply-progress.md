# Apply Progress: Security and Usability Fixes

This document records the progress of applying the security and usability fixes in a strict TDD fashion.

## TDD Cycle Evidence Table

| Task | Test File | Layer | Safety Net | RED | GREEN | TRIANGULATE | REFACTOR |
|------|-----------|-------|------------|-----|-------|-------------|----------|
| Bootstrap PHPUnit | `tests/BootstrapTest.php` | Test Infrastructure | Assertion failure verification | [x] | [x] | [x] | [x] |
| Phase 1: Session Security Configuration | `tests/SessionSecurityTest.php` | Session Management | Assert strict mode and cookie flags under HTTP and HTTPS | [x] | [x] | [x] | [x] |
| Phase 2: CSRF Validation Reset | `tests/CsrfResetTest.php` | Authentication Gateways | Verify GET request resets failed CSRF state | [x] | [x] | [x] | [x] |
| Phase 3: Secure Connections | `tests/SecureConnectionsTest.php`, `tests/SslEnforcementTest.php` | Configurations & Integrations | Enforce env-aware LDAP verification and production cURL/Stream SSL checks | [x] | [x] | [x] | [x] |
| Phase 4: Import Cleanup | `tests/ImportCleanupTest.php` | Presentation (Index) | Clean up duplicate import statements | [x] | [x] | [x] | [x] |

## Details of Phase 1: Session Security Configuration
- **RED**: Created `tests/SessionSecurityTest.php` with assertions for strict mode and cookie configurations. Ran PHPUnit, verified failure due to strict mode set to '0'.
- **GREEN**: Restructured `lib/session_security.php` to set strict mode and cookie parameters before calling `session_start()`. Bootstrapped by prepending the require to `private/config.php`. Verified PHPUnit passes.
- **TRIANGULATE**: Added test cases for both HTTPS enabled (cookie secure parameter true) and disabled (secure parameter false). Verified both pass.

## Details of Phase 2: CSRF Validation Reset
- **RED**: Created `tests/CsrfResetTest.php` to scan `login.php`, `change_pwd.php`, `rescue.php`, and `totp.php` for `$_SERVER['REQUEST_METHOD'] === 'GET'` resetting `$_SESSION['csrf_token_ok'] = true`. Verified PHPUnit fails.
- **GREEN**: Added GET request validation reset logic directly after `session_start()` in all four files. Verified PHPUnit passes.
- **TRIANGULATE**: Verified that the reset does not happen blindly on other request methods (e.g. POST requests). Verified all tests pass.

## Details of Phase 3: Secure Connections (LDAP & API TLS/SSL Verification)
- **RED**: Created `tests/SecureConnectionsTest.php` and `tests/SslEnforcementTest.php` to verify environment-aware LDAPTLS_REQCERT, exposure of global $curl_ca_bundle, and that peer verification is strictly enabled with support for the custom CA bundle in the integration files. Verified PHPUnit fails.
- **GREEN**: Added `ldap_reqcert` and `curl_ca_bundle` placeholders in `config.ini`, dynamic configuration logic in `config.php`, and updated cURL and stream verification options to enforce SSL/TLS validation using the config values in `lib/get_token.php`, `lib/sync_presence.php`, and `lib/test_native.php`. Verified PHPUnit passes.
- **TRIANGULATE**: Added an integration test in `tests/SecureConnectionsTest.php` that dynamically rewrites and restores `config.ini` to verify that `ldap_reqcert` overrides the environment-based defaults perfectly. Verified all tests pass.

## Details of Phase 4: Import Cleanup and Verification
- **RED**: Created `tests/ImportCleanupTest.php` to verify that duplicate imports and comments of `checkip.php` are cleaned up. Verified PHPUnit fails because 2 occurrences are present.
- **GREEN**: Cleaned up the redundant comment and import statement in `index.php`. Verified PHPUnit passes.
- **TRIANGULATE**: Added checks in `tests/ImportCleanupTest.php` to ensure other top-level block imports (like `fillcombobox.php`, `ldap_newfilter.php`, and `config.php`) are singular and completely unaffected by the cleanup. Verified all tests pass.
