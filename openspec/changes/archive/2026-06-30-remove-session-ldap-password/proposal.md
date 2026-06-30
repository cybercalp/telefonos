# Proposal: Remove Session LDAP Password

## Intent

Eliminate storage of plaintext Active Directory passwords in PHP session files. Currently `ldap_validateuser.php` stores the user's AD password in `$_SESSION['ldap_pass']` after login, where it persists on disk for the entire session. This creates a credential leakage risk via file inclusion vulnerabilities, backup exposure, or server misconfiguration.

## Scope

### In Scope
- Remove `$_SESSION['ldap_pass']` assignment from `ldap_validateuser.php`
- Simplify `ldap_loaduser.php` to use service account (`$ldap_user`/`$ldap_pass` from config) exclusively for LDAP reads — the fallback already exists (lines 31-33)
- Remove obsolete `$_SESSION['ldap_pass']` unset in `change_pwd.php` (line 48)
- Verify profile editing and password change flows still function

### Out of Scope
- Changes to `$_SESSION['userpass']` (set by `ldap_newpwd.php` for post-reset password change — unrelated variable, different flow)
- Changes to LDAP admin account (`$ldap_admuser`/`$ldap_admpwd`) configuration
- Session cookie security (already addressed by `session-security` spec)
- Service account permission changes

## Capabilities

### New Capabilities
None.

### Modified Capabilities
- `session-security`: Add requirement that plaintext credentials MUST NOT be stored in session data. Current spec covers only cookie flags (HttpOnly, SameSite, Secure); this extends it to session content.

## Approach

**Surgical removal** — the password change flow (`ldap_changepwd.php`) already receives old password via function parameter, not from session. The profile load flow (`ldap_loaduser.php`) already has a service account fallback when session password is empty. Only one line of credential storage needs removal.

1. `ldap_validateuser.php` line 79: delete `$_SESSION['ldap_pass'] = $ldap_pass;`
2. `ldap_loaduser.php`: the ternary at lines 31-33 naturally defaults to service account when session pass is absent — may clean up dead code (lines 56-60) but behavior is unchanged
3. `change_pwd.php` line 48: remove `$_SESSION['ldap_pass']` from unset list (harmless if left, but explicit cleanup)

## Affected Areas

| Area | Impact | Description |
|------|--------|-------------|
| `lib/ldap_validateuser.php` | Modified | Remove line 79 (session password assignment) |
| `lib/ldap_loaduser.php` | Modified | Remove dead session-pass branch (lines 56-60), keep service-account fallback |
| `change_pwd.php` | Modified | Remove `$_SESSION['ldap_pass']` from unset on line 48 |
| `lib/ldap_changepwd.php` | Unchanged | Already uses function parameters, not session |
| `lib/ldap_changeuser.php` | Unchanged | Already admin-bind only |
| `lib/ldap_contacts.php` | Unchanged | Already admin-bind only |

## Risks

| Risk | Likelihood | Mitigation |
|------|------------|------------|
| Service account lacks read permission for some user attributes | Low | Service account already reads all users in current flow (fallback path); no new permissions needed |
| `ldap_loaduser.php` self-vs-other user logic breaks | Low | The `can_edit_user()` guard is independent of bind credentials; admin bind checks permissions correctly |

## Rollback Plan

Revert the 3 file edits — restore `$_SESSION['ldap_pass'] = $ldap_pass;` in `ldap_validateuser.php`, restore session-pass branch in `ldap_loaduser.php`, restore the unset in `change_pwd.php`. No database migrations, no config changes.

## Dependencies

- Service account (`$ldap_user`/`$ldap_pass` in `config.ini`) must have LDAP read access — already the case for existing fallback path

## Success Criteria

- [ ] `$_SESSION['ldap_pass']` is never set after login
- [ ] User profile loads correctly via `datos_active.php`
- [ ] Password change via `change_pwd.php` works (both direct and post-reset flows)
- [ ] Cross-user profile editing still enforces `can_edit_user()` permission check
- [ ] Existing PHPUnit tests continue to pass
