# Design: LDAP Connection Abstraction

## Technical Approach

Create `LDAP\Connection` wrapping connect + PROTOCOL_VERSION + REFERRALS + bind, injected via `?LDAP\Connection $ldap = null` into `validate_user()`, `changePassword()`, `sso_check()`. Factory reads global config (`$ldap_dn`, `$ldap_admpwd`). Backward-compatible: null triggers factory default. Tests mock `LDAP\Connection` with PHPUnit. `check_user()` kept separate — phase 2 scope (see Known Limitations).

## Architecture Decisions

| Decision | A | B | Chosen | Rationale |
|----------|---|---|--------|-----------|
| **Namespace** | Plain `Connection` | `LDAP\Connection` + PSR-4 | **B** | Spec requires type hint. Add PSR-4 to composer.json; autoloader in config.php (already included by all targets). |
| **set_option scope** | TLS only | ALL `ldap_set_option` (PROTOCOL_VERSION, REFERRALS, TLS) | **B** | Spec requires single TLS entry. Moving all three eliminates every `ldap_set_option` call from targets — cleaner diff. |
| **Error handling** | Custom exceptions | `\RuntimeException` for factory misconfig; PHP warnings for connect/bind | **B** | Minimal. Factory validates required globals, throws `\RuntimeException`. Constructor failures produce PHP warnings — matching existing patterns (functions already suppress display_errors). |
| **check_user injection** | Inject Connection now | Scope separately for phase 2; document limitation | **B** | check_user has its own connect/bind/search/unbind (lib/ldap_checkuser.php:28–110). Modifying it expands scope. Tests cover post-check_user logic paths; check_user fails gracefully without real server. |
| **Existing param names** | Rename to $oldPwd/$newPwd | Keep original $oldPassword/$newPassword/$newPasswordCnf | **B** | These names appear in dozens of lines inside changePassword(). Renaming adds noise. Only ADD `$ldap` param. |
| **Existing type hints** | Add `string`, `bool` hints | No new scalar type hints | **B** | Adding hints changes error behavior (TypeError vs current null/empty checks). Only `$ldap` gets `?LDAP\Connection`. |

## Data Flow

```
validate_user($user, $pwd, ?LDAP\Connection $ldap=null)
  ├─ check_user($usuario)              // OWN LDAP (out of scope — see limitations)
  ├─ $conn = $ldap ?? Connection::factory()   // admin-bound
  ├─ $res = $conn->getResource()
  ├─ ldap_bind($res, $user, $pwd)             // user rebind
  ├─ ldap_search / entries / session writes
  └─ (no unbind — __destruct handles it)

changePassword($user, $oldPassword, $newPassword, $newPasswordCnf, $isRecovery=false, ?LDAP\Connection $ldap=null)
  ├─ check_user($usuario)              // OWN LDAP
  ├─ $conn = $ldap ?? Connection::factory()
  ├─ ldap_bind($res, $ldap_user, $ldap_pass)  // identity (skip if recovery)
  ├─ ldap_bind($res, $ldap_admuser, $ldap_admpwd) // admin rebind
  ├─ ldap_search → ldap_mod_replace → goto Exit_Err safe
  └─ (no unbind)

sso_check(?LDAP\Connection $ldap=null)
  ├─ $conn = $ldap ?? Connection::factory()
  ├─ ldap_bind($res, $ldap_user, $ldap_pass)  // service bind
  └─ ldap_search → session writes
```

## File Changes

| File | Action | Description |
|------|--------|-------------|
| `lib/LDAP/Connection.php` | Create | PSR-4 class. Constructor sets PROTOCOL_VERSION=3, REFERRALS=0. `factory()` reads `$ldap_host`, `$ldap_port`, `$ldap_dn`, `$ldap_admpwd` from `$GLOBALS`. `getResource(): mixed`. `__destruct()` → `ldap_unbind`. |
| `composer.json` | Modify | Add PSR-4: `"LDAP\\": "lib/LDAP/"` |
| `private/config.php` | Modify | Add `require_once __DIR__ . '/../vendor/autoload.php'` |
| `lib/ldap_validateuser.php` | Modify | Add `use LDAP\Connection;`. Add `?LDAP\Connection $ldap = null` param (no type hints on existing params). Replace inline connect+set_option+unbind with `$ldap ?? Connection::factory()`. Keep `check_user($usuario)` line 38 unchanged. |
| `lib/ldap_changepwd.php` | Modify | Add `use LDAP\Connection;`. Signature: `changePassword($user, $oldPassword, $newPassword, $newPasswordCnf, $isRecovery = false, ?LDAP\Connection $ldap = null)`. Original names preserved. Remove inline connect+set_option+unbind. Two-bind flow on shared resource. |
| `lib/sso_check.php` | Modify | Add `use LDAP\Connection;`. Signature: `sso_check(?LDAP\Connection $ldap = null)` — no return type. Remove inline connect+set_option+unbind. Service bind on `getResource()`. |
| `tests/LDAP/ConnectionTest.php` | Create | Unit: factory from globals, missing-config `\RuntimeException`, resource retrieval, destructor. |
| `tests/LDAP/ValidateUserTest.php` | Create | Mock Connection. Test session writes, error paths, user parsing. |
| `tests/LDAP/ChangePasswordTest.php` | Create | Mock Connection. Test E101–E109, recovery flag, `Exit_Err` session state. |
| `tests/LDAP/SsoCheckTest.php` | Create | Mock Connection. Test REMOTE_USER/AUTH_USER parsing, POST/already-authenticated skip. |

## Interfaces

```php
namespace LDAP;

class Connection {
    public function __construct(string $host, int $port, string $bindDn, string $password);
    public function getResource(): mixed;
    public static function factory(): self;  // $GLOBALS: ldap_host, ldap_port, ldap_dn, ldap_admpwd
    public function __destruct();
}
```

Target signatures (original names, no new scalar type hints, each file needs `use LDAP\Connection;`):

```php
function validate_user($user, $pwd, ?LDAP\Connection $ldap = null);
function changePassword($user, $oldPassword, $newPassword, $newPasswordCnf, $isRecovery = false, ?LDAP\Connection $ldap = null);
function sso_check(?LDAP\Connection $ldap = null);
```

## Testing Strategy

| Layer | What | Approach |
|-------|------|----------|
| Unit | Connection factory | Inject/cleanup `$GLOBALS`; assert `\RuntimeException` on missing config. |
| Unit | validate_user paths | Mock Connection. Test session state, user-format parsing. |
| Unit | changePassword rules | Mock Connection. Test E101–E109, recovery vs normal, `Exit_Err` writes. |
| Unit | sso_check flows | Mock Connection. Test header parsing, skip conditions, session population. |
| Integration | Existing 17 tests | `vendor/bin/phpunit` — must remain green. |

## Migration / Rollout

No migration required. `composer dump-autoload` needed after deploy.

## Known Limitations

- **check_user() not injected**: `lib/ldap_checkuser.php` has its own `ldap_connect→bind→search→unbind`. `validate_user()` (line 38) and `changePassword()` (line 43) call `check_user($usuario)` BEFORE using the injected Connection. When tests inject a mock, `check_user()` attempts real LDAP via globals — fails gracefully (no server), populating session errors. Tests cover all post-check_user logic. Phase 2 scope.

## Open Questions

- [ ] `composer dump-autoload` — manual or CI/CD?
- [ ] `Exit_Err` goto paths — verify single destructor invocation (resource unbound once).
