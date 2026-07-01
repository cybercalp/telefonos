# Delta for test-integrity

## ADDED Requirements

### Requirement: LDAP Dependency Injection Testability

LDAP-dependent functions (`validate_user`, `changePassword`, `sso_check`) SHALL accept an injectable `LDAP\Connection` parameter to enable unit testing with mocked connections, ensuring no real network I/O occurs during test execution.

#### Scenario: Unit test with mock connection

- GIVEN a PHPUnit test for `validate_user()`
- WHEN a mock `LDAP\Connection` is injected
- THEN the function MUST execute without establishing a real LDAP connection.

#### Scenario: Production path uses factory default

- GIVEN a target function is called without a Connection argument
- WHEN the function requires LDAP authentication
- THEN it MUST obtain a real Connection via `Connection::factory()`.

#### Scenario: Mock connection prevents network operations

- GIVEN a mock `LDAP\Connection` passed to `changePassword()`
- WHEN the mock's `getResource()` returns a controlled resource
- THEN the test MUST NOT trigger real `ldap_modify` network calls.
