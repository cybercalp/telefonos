# Exploration: Security and Usability Fixes

This exploration analyzes and proposes solutions for several security vulnerabilities and usability issues identified in the codebase.

---

## 1. The CSRF Lockout Bug in `login.php` (and `preventvalidpost.php`)

### Current State
In `login.php`, the flag `$_SESSION['csrf_token_ok']` is initialized only if it is not already set:
```php
if (!isset($_SESSION['csrf_token_ok'])) {
   $_SESSION['csrf_token_ok'] = true;
}
```
In `preventvalidpost.php`, when a POST request is processed, if the CSRF validation fails (e.g. expired, double-submit, missing token), it unsets the token, sets `$_SESSION['csrf_token_ok'] = false`, and redirects back to the login page:
```php
if (!hash_equals($tokenSesion, $tokenEnviado)) {
   unset($_SESSION['csrf_token']);
   $_SESSION['csrf_token_ok'] = false;
   $_SESSION['mensaje'] = array('Token CSRF inválido o formulario reenviado.');
   $_SESSION['mensaje_css'] = '';
   header('Location: ' . $_SERVER['REQUEST_URI']);
   exit;
}
```
Upon redirect, the page is requested via GET. Since `$_SESSION['csrf_token_ok']` already exists and is `false`, the check `!isset($_SESSION['csrf_token_ok'])` is bypassed, keeping the flag permanently `false` for the duration of the session.
On subsequent POST requests, even if the new CSRF token matches successfully, the login form processors in `login.php` verify:
```php
if ($_SESSION['csrf_token_ok'] === true)
```
which evaluates to `false` because of the sticky `false` from the prior failure. This permanently locks out the user from logging in until they clear cookies or restart their browser session.

### Affected Areas
- `login.php` — Initialization and verification logic.
- `lib/preventvalidpost.php` — CSRF validation logic.

### Approaches

#### Approach 1: Reset the CSRF flag on GET requests (Recommended)
Change the initialization in `login.php` from `!isset(...)` to `empty(...)`, or explicitly reset `$_SESSION['csrf_token_ok'] = true;` whenever `$_SERVER['REQUEST_METHOD'] !== 'POST'`.
- **Pros:**
  - Simple, direct, and backward compatible.
  - Fully resolves the lockout bug immediately on the next page view.
- **Cons:**
  - Retains a persistent session flag across requests (though it is properly reset on GET).
- **Effort:** Low

#### Approach 2: Eliminate the session-scoped flag completely
Modify the CSRF check so it is request-scoped. Instead of storing the validation status in `$_SESSION['csrf_token_ok']`, check the token directly during the POST request and handle failure or success on the spot.
- **Pros:**
  - Best security practice; avoids polluting the session state.
- **Cons:**
  - High friction; requires modifying other pages (`change_pwd.php`, `rescue.php`, `totp.php`) which also rely on the `csrf_token_ok` session flag.
- **Effort:** Medium

### Recommendation
**Approach 1** is recommended. By setting `$_SESSION['csrf_token_ok'] = true;` when loading the page via GET, the user is given a clean slate and is never locked out on subsequent attempts, preserving existing code structures while fixing the bug safely.

---

## 2. The `session_set_cookie_params` Timing Issue in `session_security.php`

### Current State
`lib/session_security.php` is designed to enforce secure session cookie parameters. However, it calls `session_start()` *before* setting the session parameters:
```php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Set cookie params
$cookieParams = session_get_cookie_params();
session_set_cookie_params([
    'lifetime' => $cookieParams['lifetime'],
    'path' => $cookieParams['path'],
    'domain' => $cookieParams['domain'],
    'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on', // only over HTTPS
    'httponly' => true,
    'samesite' => 'Lax'
]);
ini_set('session.use_strict_mode', 1);
```
In PHP, calling `session_set_cookie_params` and configuring session-related `ini_set` settings *after* `session_start()` is executed has no effect on the current session cookie being sent. Thus, session cookies are sent without the `HttpOnly`, `Secure`, and `SameSite` flags during the initial request, leaving users exposed to session theft.

### Affected Areas
- `lib/session_security.php`

### Approaches

#### Approach 1: Fix execution order and enforce global inclusion (Recommended)
1. Re-order the statements in `lib/session_security.php` to set `ini_set('session.use_strict_mode', 1)` and `session_set_cookie_params` **before** calling `session_start()`.
2. Replace all instances of raw `session_start()` in the codebase with `require_once __DIR__ . '/lib/session_security.php';` to guarantee that all entry points configure session cookies securely.
- **Pros:**
  - Resolves the timing bug completely.
  - Ensures standard, bulletproof session security configurations across the entire site.
- **Cons:**
  - Requires updating multiple files (approximately 15 files) that currently use raw `session_start()`.
- **Effort:** Medium

#### Approach 2: Fix execution order in `session_security.php` only
Re-order the statements inside `lib/session_security.php` without altering other files.
- **Pros:**
  - Touches only one file.
  - Fixes the bug for files that already include `session_security.php`.
- **Cons:**
  - Files containing raw `session_start()` will still start insecure sessions without applying the secure cookie parameters.
- **Effort:** Low

### Recommendation
**Approach 1** is recommended. Reversing the order inside `lib/session_security.php` and replacing raw `session_start()` calls globally will eliminate vulnerabilities like session fixation and session hijacking across the entire application.

---

## 3. Disabling `CURLOPT_SSL_VERIFYPEER` in `sync_presence.php`

### Current State
In `lib/sync_presence.php`, cURL is used to connect to the Saviacloud API. The option `CURLOPT_SSL_VERIFYPEER` is explicitly set to `false` for both the OAuth2 token request and the presence data request:
```php
CURLOPT_SSL_VERIFYPEER => false,
```
Disabling TLS certificate verification allows any self-signed or spoofed certificate to be accepted, completely neutralizing the confidentiality and integrity of SSL/TLS. This exposes the system to Man-in-the-Middle (MitM) attacks where attackers could intercept OAuth2 client credentials (`client_id`, `client_secret`) and manipulate presence data.
Importantly, `sync_presence.php` aborts early if the environment is not production:
```php
if ($app_env !== 'production') {
    log_sync("Entorno actual: $app_env. Sincronización abortada (solo en producción).");
    exit;
}
```
This means the cURL bypass is active *exclusively* in the production environment, which is highly dangerous.

### Affected Areas
- `lib/sync_presence.php`

### Approaches

#### Approach 1: Enable verification with configurable fallback/options (Recommended)
Set `CURLOPT_SSL_VERIFYPEER => true` and `CURLOPT_SSL_VERIFYHOST => 2` by default. Allow the system to specify a custom CA certificate bundle (via `CURLOPT_CAINFO`) in the `private/config.ini` file for systems using custom corporate/enterprise CA certificates.
- **Pros:**
  - Establishes industry-standard security.
  - Preserves compatibility if the target host uses an internal CA by allowing a custom CA bundle path in configuration.
- **Cons:**
  - Requires the host environment to have a properly configured root CA certificates bundle (typically standard in production).
- **Effort:** Low

#### Approach 2: Enable verification unconditionally
Simply change `CURLOPT_SSL_VERIFYPEER` to `true` unconditionally.
- **Pros:**
  - Simplest change.
- **Cons:**
  - Could fail if the host machine has an outdated or missing root certificates database (e.g. Windows/XAMPP environment without custom cert configuration).
- **Effort:** Low

### Recommendation
**Approach 1** is recommended. Change the options to `true` and add a configuration setting (`ssl_verify` or `ca_cert_path` in `private/config.ini`) so that the connection remains fully secure in production, while retaining support for enterprise proxy or custom CA requirements.

---

## 4. The Double Require of `checkip.php` in `index.php`

### Current State
In `index.php`, lines 15 and 17 both include `lib/checkip.php`:
```php
14: // Chequeo de la IP
15: require_once('./lib/checkip.php');
16: // Chequeo de la IP
17: require_once('./lib/checkip.php');
```
While `require_once` prevents multiple declarations or errors, this redundancy is a code quality defect, increases overhead, and litters the codebase.

### Affected Areas
- `index.php`

### Approaches

#### Approach 1: Remove the duplicate line (Recommended)
Remove the second `require_once('./lib/checkip.php');`.
- **Pros:**
  - Cleans up the code and reduces PHP file-checking overhead.
- **Cons:**
  - None.
- **Effort:** Low

### Recommendation
**Approach 1** is the only sensible choice.

---

## 5. The `LDAPTLS_REQCERT` Bypass in `private/config.php`

### Current State
In `private/config.php`, `LDAPTLS_REQCERT` is globally bypassed:
```php
// Variables para acceder al servidor LDAP
// Ignorar el certificado SSL inválido de Active Directory para conexiones ldaps:// (Común en XAMPP/Windows)
putenv('LDAPTLS_REQCERT=never');
```
Setting `LDAPTLS_REQCERT` to `never` tells OpenLDAP to ignore certificate validation when connecting over LDAPS (`ldaps://`) or STARTTLS. This exposes domain controller communications to MitM attacks, risking the disclosure of credentials—including domain administrator credentials (`ldap_admuser` and `ldap_admpwd` configured in `config.ini`).

### Affected Areas
- `private/config.php`
- Active Directory / LDAP authentication security.

### Approaches

#### Approach 1: Environment-aware verification and custom CA configuration (Recommended)
Disable certificate validation (`never`) only in `development` mode if verification fails or is not configured. In `production`, set `LDAPTLS_REQCERT` to `demand` (default, highly secure) and configure the CA certificate file dynamically (using `putenv("LDAPTLS_CACERT=" . $ldap_ca_cert)`) via a configuration parameter in `config.ini`.
- **Pros:**
  - Protects production AD credentials from interception.
  - Maintains seamless developer local setups where AD certificates are self-signed.
- **Cons:**
  - Requires production administrators to configure/export the AD CA certificate to the web server (standard secure enterprise configuration).
- **Effort:** Medium

#### Approach 2: Remove the environment variable entirely
Delete the `putenv('LDAPTLS_REQCERT=never');` line entirely, forcing system-default OpenLDAP behavior.
- **Pros:**
  - Simplest change.
- **Cons:**
  - Will break logins immediately in environments where the LDAP server uses an untrusted or self-signed certificate.
- **Effort:** Low

### Recommendation
**Approach 1** is recommended. By setting `LDAPTLS_REQCERT` based on the environment (demanded in `production`, tolerated as `never` in `development` or if configured) and introducing an optional configuration file setting for the AD CA, we achieve a highly secure production system without disrupting development workflow.

---

### Risks
- **LDAP/cURL Connections Blocking:** Enforcing TLS validation in production might break active integrations if the current servers use self-signed or expired certificates, or if the host lacks a properly configured root CA bundle. These systems must be pre-configured with the appropriate root/intermediate certificates.
- **Session Expiration Changes:** Enabling secure cookie flags like SameSite=Lax and Secure will prevent session sharing across non-HTTPS connections or across subdomains, which is the intended security behavior but may affect any unencrypted testing environments.

### Ready for Proposal
Yes
