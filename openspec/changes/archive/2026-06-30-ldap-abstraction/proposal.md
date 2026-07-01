# Proposal: LDAP Connection Abstraction

## Intent

22 files in `lib/` independently open LDAP connections with identical boilerplate (`connect → set_option → bind → work → unbind`). This makes LDAP-dependent functions untestable (0 tests exist) and scatters TLS configuration across the codebase. A centralized connection manager replaces ad-hoc connections with an injectable interface, enabling testing and consistent security.

## Scope

### In Scope
- `LDAP\Connection` class: constructor accepts host, port, DN, password; provides pre-configured bound `LDAP\Connection` resource
- Inject into `validate_user()`, `changePassword()`, `sso_check()` via optional parameter (backward-compatible default)
- PHPUnit test doubles for all three functions
- Centralized TLS enforcement in single entry point

### Out of Scope
- Full 22-file migration (phase 2)
- Global variable refactoring (`$ldap_host` etc. remain as config source)
- `get_admin_ldap_connection()` singleton changes

## Capabilities

| Type | Capability | Change |
|------|-----------|--------|
| **New** | `ldap-connection-abstraction` | Centralized LDAP connection manager with injectable interface |
| **Modified** | `test-integrity` | ADDED requirement: LDAP-dependent functions SHALL be testable via dependency injection of the connection interface |

> `session-security` and `secure-integrations` are NOT modified at spec level. Implementation MUST comply with existing `remove-session-ldap-password` deltas and centralized TLS enforcement.

## Approach

1. Create `lib/LDAP/Connection.php` wrapping `ldap_connect`, `ldap_set_option` (TLS), `ldap_bind`, `ldap_unbind`
2. Factory method creates TLS-configured connection from global config
3. Target functions accept `?LDAP\Connection $ldap = null` parameter — null triggers backward-compatible default from factory
4. Tests inject mock connection; production code uses factory default
5. Connection resource exposed via `getResource()` for native `ldap_*` operations (search, modify, etc.)

## Affected Areas

| Area | Impact | Description |
|------|--------|-------------|
| `lib/LDAP/Connection.php` | New | Connection manager class |
| `lib/ldap_validateuser.php` | Modified | Injected connection |
| `lib/ldap_changepwd.php` | Modified | Injected connection |
| `lib/sso_check.php` | Modified | Injected connection |
| `tests/` | New | Unit tests with LDAP mocks |

## Risks

| Risk | Likelihood | Mitigation |
|------|------------|------------|
| Auth regression blocks login | Medium | Backward-compatible default parameter; nil = factory default |
| Connection resource lifecycle leak | Low | Destructor calls `ldap_unbind`; PHP GC on request end |
| TLS misconfiguration during migration | Low | Single entry point reduces config surface from 22 files to 1 |

## Rollback Plan

1. Revert target functions to inline `ldap_connect/bind/unbind`
2. Remove `lib/LDAP/Connection.php`
3. Re-run full test suite — zero LDAP-related tests means zero rollback regression in tests

## Dependencies

- `private/config.ini` remains credential source (no config migration)
- `session-security` ADDED deltas (remove-session-ldap-password) already satisfied

## Success Criteria

- [ ] `validate_user()`, `changePassword()`, `sso_check()` have PHPUnit tests with mocked LDAP connections
- [ ] All 17 existing passing tests remain green
- [ ] All `ldap_set_option` TLS configuration occurs in exactly one file (`lib/LDAP/Connection.php`)
- [ ] No new `ldap_connect()` calls appear outside `lib/LDAP/`
