# Tasks: Remove Session LDAP Password

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | ~35 (remove ~17, add ~8, modify ~10) |
| 400-line budget risk | Low |
| Chained PRs recommended | No |
| Suggested split | Single PR |
| Delivery strategy | single-pr |
| Chain strategy | pending |

Decision needed before apply: No
Chained PRs recommended: No
Chain strategy: pending
400-line budget risk: Low

## Phase 1: Test-First (TDD RED)

- [x] 1.1 Add `testSessionHasNoLdapPasswordAfterLogin()` to `tests/SessionSecurityTest.php` — assert `$_SESSION['ldap_pass']` is not set and `ldap_user`/`is_authenticated` are present after `validate_user()` completes a successful login
- [x] 1.2 Run `php vendor/phpunit/phpunit/phpunit` — confirm the new test FAILS (RED)

## Phase 2: Core Implementation

- [x] 2.1 `lib/ldap_validateuser.php` line 79 — delete `$_SESSION['ldap_pass'] = $ldap_pass;`
- [x] 2.2 `lib/ldap_loaduser.php` — delete `$session_pass` retrieval (line 29) and the dead DN-formatting block (lines 56-60); simplify lines 31-33 to always bind service account `$ldap_user`/`$ldap_pass`
- [x] 2.3 `lib/ldap_changeuser.php` — after successful `ldap_modify` (line 82), add `error_log("[AUDIT] acting_user={$_SESSION['ldap_user']} target_dn={$user_dn}")` for admin action attribution

## Phase 3: Verification (TDD GREEN)

- [x] 3.1 Run `php vendor/phpunit/phpunit/phpunit` — all tests pass, including the new regression test
- [x] 3.2 Grep `$_SESSION['ldap_pass']` across `lib/*.php` — confirm only references remain in flow-unaffected files (`change_pwd.php` unset, which is idempotent)
