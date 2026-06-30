# Design: Remove Session LDAP Password

## Technical Approach

Surgical removal of one session assignment. The service account (`$ldap_user`/`$ldap_pass` from `config.ini`) already serves as fallback for read binds in `ldap_loaduser.php:32-33`. By removing `$_SESSION['ldap_pass'] = $ldap_pass` at `ldap_validateuser.php:79`, the fallback becomes the sole path. Writes already use admin bind exclusively. No new credentials or architecture changes — dead code elimination only.

## Architecture Decisions

### Decision 1: Service Account as Exclusive Read Bind

| Option | Tradeoff | Verdict |
|--------|----------|---------|
| Service account (`$ldap_user`/`$ldap_pass`) | Centralized credential, already configured | **Chosen** |
| Keep user password in session | Plaintext on disk via PHP session files | Rejected |

**Rationale**: The fallback path already binds service account on empty session password. No new permissions needed. `can_edit_user()` reads session identity (`$_SESSION['ldap_user']`, `auth_user_dn`), not bind credentials, so permission checks remain unaffected.

### Decision 2: Audit Trail via Session Identity

**Choice**: Log `$_SESSION['ldap_user']` when admin binds for modifications.
**Rationale**: Writes use admin credentials — AD's `modifiersName` reflects the admin account, not the acting user. `ldap_changeuser.php` must log `ldap_user` from session to distinguish who acted from who wrote.

### Decision 3: Dead Code Removal

**Choice**: Remove `if (!empty($session_pass))` block (lines 56-60, `ldap_loaduser.php`).
**Rationale**: Unreachable after session password removal. Eliminates cognitive load and future confusion.

## Data Flow

### Authentication (Before → After)

```
BEFORE:  ldap_bind(user, pass) OK
         → $_SESSION['ldap_pass'] = pass    ← STORED ON DISK
         → $_SESSION['ldap_user'], is_authenticated, 2FA

AFTER:   ldap_bind(user, pass) OK
         → $_SESSION['ldap_user'], is_authenticated, 2FA
         // Password discarded — never leaves function scope
```

### Profile Load

```
BEFORE:  load_userdata()
         → session_pass? → bind(user, pass)   [user rebind]
                        → bind($ldap, $ldap)   [fallback]
         → search attributes → can_edit_user()

AFTER:   load_userdata()
         → bind($ldap_user, $ldap_pass)        [service account only]
         → search attributes → can_edit_user()
```

### Password Change (Unchanged)

```
change_pwd.php → changePassword(user, oldPwd_POST, new, confirm)
  → ldap_bind(user, oldPwd)     ← credential verification
  → ldap_bind(admuser, admpwd)  ← admin write bind
  → ldap_mod_replace(user_dn, unicodePwd)
```

### Admin Write Flow

```
datos_active.php POST → update_ldap_data(user_dn)
  → ldap_bind(admuser, admpwd)  ← admin bind
  → can_edit_user(ldap_conn, user_dn)  ← permission check via session DN
  → ldap_modify(target_dn, attrs)
  → log: "acting_user={$_SESSION['ldap_user']} target_dn={$user_dn}"
```

## File Changes

| File | Action | Description |
|------|--------|-------------|
| `lib/ldap_validateuser.php` | Modify | Remove `$_SESSION['ldap_pass'] = $ldap_pass;` (line 79) |
| `lib/ldap_loaduser.php` | Modify | Remove dead DN-formatting block lines 56-60; optionally simplify `$session_pass` retrieval line 29 |
| `lib/ldap_changeuser.php` | Modify | Add `error_log()` recording acting session user alongside target DN |

## Testing Strategy

| Layer | What to Test | Approach |
|-------|-------------|----------|
| Unit | Session keys post-login | Assert `array_key_exists('ldap_pass', $_SESSION)` is false |
| Unit | Session keys present | Assert `ldap_user` and `is_authenticated` are set |
| Integration | Profile load via service bind | `datos_active.php` GET loads user data with authenticated session |
| Integration | Password change reads from POST | `changePassword()` uses `$_POST['txtUserPwd']`, never session |
| Integration | Cross-user edit permission | Admin loads another user via `?user=X`; `can_edit_user()` passes |

## Migration / Rollout

No migration required. Rollback: restore line 79 in `ldap_validateuser.php`, restore lines 56-60 in `ldap_loaduser.php`. No config or DB changes.

## Open Questions

- [ ] Should `$session_pass = isset($_SESSION['ldap_pass']) ? ...` (line 29) be cleaned or kept as defense-in-depth?
