# Proposal: Security and Usability Fixes

## Intent
Resolve security vulnerabilities (insecure session cookie flags, missing TLS validation in curl/LDAP) and fix user login lockouts caused by sticky CSRF failure states.

## Scope
### In Scope
- Configure secure session cookie params (HttpOnly, Secure, SameSite) prior to session start.
- Reset CSRF token status on GET requests to prevent permanent lockout.
- Enforce TLS certificate validation for LDAP and Saviacloud curl requests in production.
- Clean up duplicate imports in index.php.

### Out of Scope
- Complete rewrite of LDAP integration.
- General refactoring of session management.

## Capabilities
### New Capabilities
- None

### Modified Capabilities
- csrf-protection: Resolve login lockout by resetting status on GET requests.
- session-security: Apply secure session cookie flags (HttpOnly, Secure, SameSite) correctly before starting session.
- secure-integrations: Enforce TLS/SSL certificate validation on Saviacloud curl requests and AD LDAP connections in production.

## Approach
1. **CSRF Logic**: Update `login.php` or `preventvalidpost.php` to clear/reset token validation state on GET requests.
2. **Session Security**: Reorder code in `lib/session_security.php` to set cookie params before starting session.
3. **TLS Validation**: Enforce `CURLOPT_SSL_VERIFYPEER` in `lib/sync_presence.php`. Add environment-aware LDAP connection parameters demanding TLS verification in production.
4. **Imports**: Remove duplicate imports in `index.php`.

## Affected Areas
| Area | Impact | Description |
|------|--------|-------------|
| `lib/session_security.php` | Modified | Reverse session_start and set_cookie_params order. |
| `login.php` | Modified | Reset csrf_token_ok flag on GET requests. |
| `lib/preventvalidpost.php` | Modified | Reset token validation state if needed. |
| `lib/sync_presence.php` | Modified | Set CURLOPT_SSL_VERIFYPEER to true by default with CA config fallback. |
| `private/config.php` | Modified | Set environment-aware LDAPTLS_REQCERT (demand in production, never in development). |
| `index.php` | Modified | Remove duplicate checkip require. |

## Risks
| Risk | Likelihood | Mitigation |
|------|------------|------------|
| API connection failure | Medium | Support configurable CA path bundle. |
| LDAP TLS failure | Medium | Allow configuring CA in config.ini. |

## Rollback Plan
To revert these changes, run the following command:
```bash
git checkout HEAD -- lib/session_security.php login.php lib/preventvalidpost.php lib/sync_presence.php private/config.php index.php
```

## Success Criteria
- [ ] Users do not experience permanent CSRF lockout on login.
- [ ] Session cookies are successfully transmitted with HttpOnly, Secure (under HTTPS), and SameSite=Lax flags.
- [ ] curl requests to Saviacloud API and LDAPS connections verify certificates in production mode.
- [ ] Duplicate imports are cleaned up.
