# Proposal: Refactor Test Assertions and Static Scans Quality

## Intent
Replace fragile regex-based static code scans and tautological assertions in PHPUnit tests with robust, comment-immune PHP token-based assertions and proper type/existence checks to guarantee test suite integrity.

## Scope
### In Scope
- Build `Tests\PhpTokenScanner` helper utilizing `token_get_all()` to parse and search PHP files ignoring whitespaces and comments.
- Refactor `CsrfResetTest.php` to use token scanner sequences instead of fragile regular expressions.
- Refactor `SslEnforcementTest.php` to use the token scanner to verify secure cURL and stream SSL options.
- Refactor `BootstrapTest.php` to verify actual loading of `BaconQrCode\Encoder\Encoder` rather than using `assertTrue(true)`.
- Refactor `SecureConnectionsTest.php` to use `array_key_exists` instead of `isset || is_null` and add dynamic config overrides verification.

### Out of Scope
- Modifying production code logic of authentication, CSRF, or cURL calls.
- Upgrading PHPUnit or other external dependencies.

## Capabilities
### New Capabilities
- None
### Modified Capabilities
- test-integrity: Replace tautologies in BootstrapTest.php and SecureConnectionsTest.php.
- robust-static-scanning: Create a PhpTokenScanner helper to robustify static scans in CsrfResetTest.php and SslEnforcementTest.php.

## Approach
1. **PhpTokenScanner Helper**:
   - Filter out `T_WHITESPACE`, `T_COMMENT`, and `T_DOC_COMMENT` using `token_get_all()`.
   - Implement `hasSequence(array $sequence): bool` method to match token sequences regardless of formatting or comments.
2. **Refactor BootstrapTest.php**:
   - Verify autoloading by asserting `class_exists(\BaconQrCode\Encoder\Encoder::class)`.
3. **Refactor SecureConnectionsTest.php**:
   - Change `testCurlCaBundleConfigExposed` to assert `array_key_exists('curl_ca_bundle', $GLOBALS)`.
   - Add `testCurlCaBundleOverrideFromIni` to verify that defining `curl_ca_bundle` in `private/config.ini` successfully overrides `$curl_ca_bundle` global.
4. **Refactor CsrfResetTest.php & SslEnforcementTest.php**:
   - Instantiate `PhpTokenScanner` and call `hasSequence()` to verify code structure accurately.

## Affected Areas
| Area | Impact | Description |
|------|--------|-------------|
| `tests/PhpTokenScanner.php` | New | Build helper class leveraging `token_get_all()`. |
| `tests/BootstrapTest.php` | Modified | Verify BaconQrCode class loading instead of `assertTrue(true)`. |
| `tests/SecureConnectionsTest.php` | Modified | Assert global key existence and verify dynamic config overrides. |
| `tests/CsrfResetTest.php` | Modified | Replace regex pattern matching with token scanner sequences. |
| `tests/SslEnforcementTest.php` | Modified | Replace string-contains calls with structured token scans. |

## Risks
| Risk | Likelihood | Mitigation |
|------|------------|------------|
| Dynamic token names | Low | Fall back to text/char matching. |

## Rollback Plan
```bash
git checkout HEAD -- tests/
```

## Success Criteria
- [ ] Test suite contains zero tautological assertions.
- [ ] CsrfResetTest and SslEnforcementTest are immune to formatting or comment additions.
- [ ] PhpTokenScanner successfully identifies secure connection sequences.
- [ ] Dynamic CA bundle overrides from private/config.ini are fully tested.
