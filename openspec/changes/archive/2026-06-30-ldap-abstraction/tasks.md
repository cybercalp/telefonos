# Tasks: LDAP Connection Abstraction

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | ~600 |
| 800-line budget risk | Low |
| Chained PRs recommended | No |
| Suggested split | Single PR |
| Delivery strategy | auto-forecast |
| Chain strategy | pending |

Decision needed before apply: No
Chained PRs recommended: No
Chain strategy: pending
400-line budget risk: Medium

## Phase 1: Foundation

- [x] 1.1 Add PSR-4 autoloading `"LDAP\\": "lib/LDAP/"` to `composer.json`
- [x] 1.2 Add `require_once __DIR__ . '/../vendor/autoload.php'` to `private/config.php`
- [x] 1.3 Run `composer dump-autoload` (manual: updated `autoload_psr4.php` + `autoload_static.php`)

## Phase 2: Connection Class (TDD)

- [x] 2.1 [RED] Write `tests/LDAP/ConnectionTest.php` — factory from globals, missing-config RuntimeException, getResource, destructor→unbind
- [x] 2.2 [GREEN] Create `lib/LDAP/Client.php` — namespace LDAP, constructor(PROTOCOL_VERSION=3, REFERRALS=0, TLS, bind), factory(), getResource(): mixed, __destruct()
- [x] 2.3 Run ConnectionTest — must pass (7 tests, 11 assertions)

## Phase 3: Inject Connection (TDD per function)

- [x] 3.1 [RED] Write `tests/LDAP/ValidateUserTest.php` — mock Client, session writes, error paths (525, 52e, 773), empty-input guard
- [x] 3.2 [GREEN] Modify `lib/ldap_validateuser.php` — add `use LDAP\Client;`, signature `validate_user($user, $pwd, ?LDAP\Client $ldap=null)`, replace inline connect/set_option/unbind with `$ldap ?? Client::factory()`, keep `check_user()` line 38
- [x] 3.3 [RED] Write `tests/LDAP/ChangePasswordTest.php` — mock Client, E101–E109 rules, recovery flag, Exit_Err session state, E102 mismatch
- [x] 3.4 [GREEN] Modify `lib/ldap_changepwd.php` — add `use LDAP\Client;`, signature `changePassword($user, $oldPassword, $newPassword, $newPasswordCnf, $isRecovery=false, ?LDAP\Client $ldap=null)`, remove inline connect/set_option, two-bind on shared getResource(), keep original param names
- [x] 3.5 [RED] Write `tests/LDAP/SsoCheckTest.php` — mock Client, REMOTE_USER/AUTH_USER parsing, POST/already-auth skip, session writes
- [x] 3.6 [GREEN] Modify `lib/sso_check.php` — add `use LDAP\Client;`, signature `sso_check(?LDAP\Client $ldap=null)`, remove inline connect/set_option/unbind, service bind via getResource()

## Phase 4: Verification

- [x] 4.1 Run full suite `php vendor/phpunit/phpunit/phpunit` — 89 tests pass (57 existing + 32 new), 145 assertions, 1 skipped
- [x] 4.2 Verify zero `ldap_set_option` in target functions (ldap_validateuser, ldap_changepwd, sso_check) ✓
- [x] 4.3 Document check_user() limitation — own LDAP calls via globals (phase 2 scope); tests cover post-check_user paths

## Implementation Notes

- **Class renamed to `LDAP\Client`** (from `LDAP\Connection`): PHP 8.1+ defines a native final class `LDAP\Connection` in the LDAP extension. Using that name causes a fatal error. Renamed to `Client` — preserves namespace, avoids conflict.
- **Autoloader updated manually**: `composer` CLI not available on this dev machine. Updated `vendor/composer/autoload_psr4.php` and `vendor/composer/autoload_static.php` directly.
- **`@ldap_bind` added in sso_check.php**: Suppresses PHP warnings during bind failure so error_log + return false handles it — prevents PHPUnit from converting warnings to exceptions in separate-process tests.
- **Error log redirection in SsoCheckTest**: `ini_set('error_log', ...)` redirects error_log output to a temp file so PHPUnit doesn't treat it as unexpected test output in `@RunInSeparateProcess`.
- **check_user() limitation**: Both `validate_user()` and `changePassword()` call `check_user()` BEFORE using the injected Client. `check_user()` does its own LDAP connect/bind/search/unbind via globals (not injected). In tests without a real server, `check_user()` fails gracefully — connects via globals, bind fails, populates `$_SESSION['mensaje']` but sets `$_SESSION['mensaje_css']` to `''` (not `'no'`), allowing post-check_user logic to execute. Phase 2 scope.

## Notes

- Centralized TLS/PROTOCOL_VERSION/REFERRALS in Client constructor only.
- Backward-compatible: null default triggers factory — production callers unchanged.
- Client exposes `getResource()` for native ldap_* ops. Destructor handles unbind.
- check_user() not injected — phase 2. In tests, fails gracefully → session errors → post-check logic covered.
