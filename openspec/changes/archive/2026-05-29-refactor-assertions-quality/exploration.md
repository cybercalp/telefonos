## Exploration: Refactor Assertions Quality

### Current State
1. **Bootstrap Test (`tests/BootstrapTest.php`)**:
   Currently contains `testBootstrap()` which executes a single tautological assertion: `$this->assertTrue(true)`. This test does not verify any real system behavior and serves only as a placeholder.
2. **Secure Connections Test (`tests/SecureConnectionsTest.php`)**:
   The test `testCurlCaBundleConfigExposed()` checks the global variable `$curl_ca_bundle` using:
   `$this->assertTrue(isset($curl_ca_bundle) || is_null($curl_ca_bundle))`.
   In PHP, because every variable is either set or null (or if unset, `global` initializes it to `null`), this condition is a logical tautology that is always true. It fails to verify if the global variable was actually declared/parsed in `config.php`.
3. **Static Code Scanning Tests (`SslEnforcementTest.php` and `CsrfResetTest.php`)**:
   These tests parse source files (`lib/get_token.php`, `lib/sync_presence.php`, `lib/test_native.php`, `login.php`, `change_pwd.php`, `rescue.php`, `totp.php`) using simple string matches (`assertStringContainsString`) or exact regular expressions to ensure SSL is enforced and CSRF tokens are conditionally initialized.
   This approach is highly fragile because:
   - Commented-out secure configurations will pass tests that only search for string containment.
   - Commented-out insecure configurations (e.g. disabling verification) will fail tests even though they are safe because they are commented out.
   - Any minor code style/formatting adjustments (such as adding spaces, changing single/double quotes, using alternative array syntax, or using `1` instead of `true`) will break the assertions.

---

### Affected Areas
- `tests/BootstrapTest.php` — Tautological `$this->assertTrue(true)` needs replacement.
- `tests/SecureConnectionsTest.php` — Tautological `$this->assertTrue(isset(...) || is_null(...))` needs replacement.
- `tests/SslEnforcementTest.php` — Fragile string-based static scans need robustifying.
- `tests/CsrfResetTest.php` — Fragile regex-based static scans need robustifying.
- `private/config.php` — Underpins the secure connections configuration.

---

### Approaches

#### Topic 1: Replacing Tautological Assertion in `BootstrapTest.php`
1. **Assert class exists via Autoloader (Recommended)**
   - Pros: Verifies that the Composer autoloading system is fully operational and classes are resolvable. Checking a package-specific class (e.g., `BaconQrCode\Encoder\Encoder::class`) ensures the environment is healthy.
   - Cons: Relies on vendor folder dependencies.
   - Effort: Low
2. **Check basic configurations are loaded**
   - Pros: Verifies that application constants or global variables are loaded.
   - Cons: Slightly redundant with other config tests.
   - Effort: Low

#### Topic 2: Replacing Tautological Assertion in `SecureConnectionsTest.php`
1. **Check existence in `$GLOBALS` array (Recommended)**
   - Pros: Non-tautological. Using `array_key_exists('curl_ca_bundle', $GLOBALS)` checks if the global variable has actually been declared.
   - Cons: Does not verify that custom values are successfully parsed.
   - Effort: Low
2. **Dynamic ini override test (Recommended)**
   - Pros: Backup `config.ini`, write a mock path for `curl_ca_bundle`, require `config.php`, and assert that the global variable matches the mocked path. This verifies parsing logic.
   - Cons: Slightly more code to backup and restore `config.ini`.
   - Effort: Low
3. **Verify file readability/existence**
   - Pros: Can verify if a defined CA bundle path is actually valid and readable.
   - Cons: Not relevant if no path is configured.
   - Effort: Low

#### Topic 3: Upgrading Static Code Scanning in `SslEnforcementTest.php` and `CsrfResetTest.php`
1. **Flexible Regular Expressions**
   - Pros: Uses standard PHPUnit assertions; simple regex updates.
   - Cons: Still highly fragile, hard to maintain, and completely vulnerable to comments causing false positives/negatives.
   - Effort: Medium
2. **PHP Tokenizer Lexical Analysis (`token_get_all`) (Recommended)**
   - Pros: Compile-level robusticity. By parsing the code into PHP tokens using `token_get_all()` and filtering out all `T_WHITESPACE`, `T_COMMENT`, and `T_DOC_COMMENT`, we get a 100% robust token stream. It ignores all comments, extra spaces, line breaks, indentations, case variations (`TRUE` vs `true`), and single/double quote changes.
   - Cons: Requires a lightweight token scanner helper class in the tests directory.
   - Effort: Medium
3. **Dynamic Mocking/Stubbing at Runtime**
   - Pros: Tests execution behavior rather than code layout.
   - Cons: Global-namespace cURL calls execute immediately and hit live network API endpoints; mocking built-in functions in global namespace is extremely difficult and requires non-standard PHP extensions (e.g., `uopz`).
   - Effort: High

---

### Recommendation
1. **For `BootstrapTest.php`**: Replace `$this->assertTrue(true)` with `class_exists(\BaconQrCode\Encoder\Encoder::class)` check to verify that Composer autoloader is functional.
2. **For `SecureConnectionsTest.php`**: Update `testCurlCaBundleConfigExposed()` to assert `array_key_exists('curl_ca_bundle', $GLOBALS)`. Additionally, implement a test `testCurlCaBundleOverrideFromIni()` that writes a mock CA bundle path to a temp `config.ini`, requires `config.php`, and asserts the exact matched value.
3. **For `SslEnforcementTest.php` & `CsrfResetTest.php`**: Implement a shared `PhpTokenScanner` helper that utilizes PHP's built-in `token_get_all()`. This helper will filter out all whitespaces and comments, then search for sequence patterns. This provides highly robust, comment-immune, and style-insensitive lexical scanning of target scripts.

---

### Risks
- **Overwriting config.ini during tests**: Overriding configuration options via backup/restore can corrupt configuration if the test suite is aborted abruptly. *Mitigation*: Wrap configuration override logic in robust `try { ... } finally { ... }` blocks to ensure restore is always called.
- **Token scanner changes**: If the structure of the underlying files is completely refactored (e.g. from array mapping to OOP wrapper classes), the token sequences will change. *Mitigation*: Write high-level pattern token checks that focus on core tokens, and verify that the tests are well-documented.

### Ready for Proposal
Yes
