# Specification: Session Security

## Intent
Enforce secure session practices globally, ensuring session configuration parameters are correctly applied to the session cookie prior to session start, mitigating hijacking and fixation risks.

## Requirements
1. The session identifier MUST be initialized with strict mode enabled.
2. The session cookie configuration MUST be set prior to starting or resuming any PHP session.
3. The session cookie MUST carry the HttpOnly flag.
4. The session cookie MUST carry the SameSite=Lax attribute.
5. The session cookie MUST carry the Secure flag when accessed over an HTTPS connection.

## Scenarios
### Scenario 1: Secure cookie parameters on session initialization
Given the user accesses any authenticated or session-dependent endpoint
When the PHP session is started
Then the session cookie MUST be transmitted with HttpOnly, SameSite=Lax, and Secure flags (if HTTPS)
And the configuration settings MUST be applied prior to the session start.

---

## ADDED (2026-06-30 — remove-session-ldap-password)

### Requirement: Plaintext Password Storage Prohibited

The system MUST NOT store plaintext passwords in PHP session data. After successful LDAP authentication, `$_SESSION` SHALL contain the authenticated username (`ldap_user`) and authentication status (`is_authenticated`) but SHALL NOT contain the user's password (`ldap_pass`).

(Previously: `$_SESSION['ldap_pass']` was stored on successful login for rebind operations.)

#### Scenario: Session is password-free after login

- GIVEN a user completes LDAP authentication successfully
- WHEN the session is populated post-bind
- THEN `$_SESSION` SHALL NOT contain the key `ldap_pass`
- AND `$_SESSION['ldap_user']` and `$_SESSION['is_authenticated']` SHALL be present

#### Scenario: Regression test asserts password absence

- GIVEN the application processes a successful login
- WHEN session variables are assigned
- THEN no plaintext password SHALL appear in any `$_SESSION` key
- AND a test SHALL assert `array_key_exists('ldap_pass', $_SESSION)` returns false

### Requirement: Service Account as Exclusive Read Bind Path

The user profile loader (`load_userdata()`) MUST bind to LDAP using the configured service account (`$ldap_user`/`$ldap_pass` from `config.ini`) for all read operations. Without session passwords, the service account SHALL be the sole credential path.

(Previously: `load_userdata()` attempted user-credential bind first, falling back to service account when session password was empty.)

#### Scenario: Profile load binds with service account

- GIVEN a user is authenticated (session has `ldap_user`, no `ldap_pass`)
- WHEN `load_userdata()` connects to LDAP
- THEN bind MUST use `$ldap_user`/`$ldap_pass` from configuration
- AND user attributes MUST load for self and permitted cross-user targets

#### Scenario: Cross-user edit enforces permissions via service bind

- GIVEN an admin loads another user's profile via `datos_active.php?user=X`
- WHEN `can_edit_user()` runs against the target DN
- THEN the permission check SHALL operate regardless of absent session password
- AND profile load SHALL succeed only if `can_edit_user()` passes

#### Scenario: Dead DN-formatting branch removed

- GIVEN session password is never present after this change
- WHEN `ldap_loaduser.php` executes
- THEN the conditional block formatting DN for user-level bind SHALL be eliminated
- AND no execution path SHALL depend on `$_SESSION['ldap_pass']`

### Requirement: Password Change Receives Credentials via Parameter

The password change function SHALL accept the old password exclusively as a function parameter. It MUST NOT read any password from `$_SESSION`.

(Previously: no dependency existed — `changePassword()` already used parameters. This requirement formalizes the invariant.)

#### Scenario: Direct password change uses POST-supplied password

- GIVEN a user submits the password change form
- WHEN `changePassword($user, $oldPassword, $newPassword, $newPasswordCnf)` is called
- THEN `$oldPassword` MUST be `$_POST['txtUserPwd']`
- AND the initial LDAP bind for credential verification SHALL use this POST value

#### Scenario: Post-reset password change is unaffected

- GIVEN a user resets their password via `ldap_newpwd.php` (sets `$_SESSION['userpass']`)
- WHEN `change_pwd.php` routes through the session-username branch
- THEN `$oldPassword` SHALL come from `$_SESSION['userpass']` (unchanged behavior)
- AND this flow SHALL remain independent of the removed `$_SESSION['ldap_pass']`

### Requirement: Administrative Action Attribution

When a service account performs LDAP modifications on behalf of an authenticated user, the acting user's identity MUST be recorded for audit purposes.

#### Scenario: Admin action logs the acting principal

- GIVEN an authenticated admin edits a different user's profile
- WHEN the service account performs the LDAP write operation
- THEN `$_SESSION['ldap_user']` SHALL be logged as the acting principal
- AND the log SHALL distinguish the acting user from the target user
