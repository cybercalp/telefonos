# ldap-connection-abstraction Specification

## Purpose

Centralized LDAP connection manager with injectable interface, replacing ad-hoc per-function boilerplate and enforcing TLS configuration from a single entry point.

## Requirements

### Requirement: Connection Lifecycle

The system SHALL provide an `LDAP\Connection` class managing the full LDAP lifecycle: connect → TLS → bind → unbind.

**Implementation note**: The class was named `LDAP\Client` (not `LDAP\Connection`) because PHP 8.1+ defines a native final `LDAP\Connection` class in the LDAP extension. See design.md and verify-report.md for the full rationale.

#### Scenario: TLS-encrypted connection and bind

- GIVEN valid host, port, DN, and password
- WHEN a Connection is instantiated
- THEN TLS MUST be enabled AND the resource MUST be authenticated via bind.

#### Scenario: Resource cleanup

- GIVEN an active Connection
- WHEN the Connection object is destroyed
- THEN `ldap_unbind` MUST be called on the underlying resource.

### Requirement: Resource Access

The system SHALL expose the native LDAP resource for `ldap_*` operations (search, modify).

#### Scenario: Resource retrieval

- GIVEN a bound Connection
- WHEN `getResource()` is called
- THEN the PHP LDAP resource handle MUST be returned.

### Requirement: Factory Method

The system SHALL provide `Connection::factory()` that creates a Connection from `$GLOBALS` config.

#### Scenario: Factory from globals

- GIVEN `$ldap_host`, `$ldap_port`, `$ldap_dn`, `$ldap_admpwd` are set
- WHEN `Connection::factory()` is called
- THEN a bound, TLS-configured Connection MUST be returned.

#### Scenario: Factory with missing config

- GIVEN any required global is absent
- WHEN `Connection::factory()` is called
- THEN a descriptive exception MUST be thrown.

### Requirement: Injectable Parameter

| Function | New Signature |
|----------|--------------|
| `validate_user()` | `?LDAP\Connection $ldap = null` |
| `changePassword()` | `?LDAP\Connection $ldap = null` |
| `sso_check()` | `?LDAP\Connection $ldap = null` |

#### Scenario: Default factory fallback

- GIVEN any target function is called with `$ldap = null`
- WHEN an LDAP resource is required
- THEN `Connection::factory()` MUST be used.

#### Scenario: Injected connection overrides factory

- GIVEN a Connection instance is passed to any target function
- WHEN the function executes
- THEN the injected Connection MUST be used AND `Connection::factory()` SHALL NOT be called.

### Requirement: Single TLS Entry Point

All `ldap_set_option` calls (PROTOCOL_VERSION, REFERRALS, TLS) MUST reside exclusively within `lib/LDAP/`.

#### Scenario: TLS config containment

- GIVEN `lib/ldap_validateuser.php`, `lib/ldap_changepwd.php`, `lib/sso_check.php`
- WHEN these functions establish LDAP connections
- THEN they SHALL NOT call any `ldap_set_option`.
