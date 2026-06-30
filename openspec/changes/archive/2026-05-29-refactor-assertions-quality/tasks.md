# Implementation Tasks: Refactor Test Assertions and Static Scans Quality

## Review Workload Forecast
Decision needed before apply: No
Chained PRs recommended: No
Chain strategy: size-exception
400-line budget risk: Low

## Phase 1: PhpTokenScanner Helper
- [x] Create `tests/PhpTokenScanner.php` with constructor tokenizing content via `token_get_all` and filtering out whitespaces and comments.
- [x] Implement `tokensMatch` in `tests/PhpTokenScanner.php` to handle token type/value comparisons and string normalization.
- [x] Implement `hasSequence` in `tests/PhpTokenScanner.php` to check for contiguous token sequences.
- [x] Implement `countSequence` in `tests/PhpTokenScanner.php` to count occurrences of contiguous token sequences.
- [x] Create `tests/PhpTokenScannerTest.php` to unit test the scanner's sequence matching, ignore-list, and case-insensitivity.

## Phase 2: Refactor Tautology Tests
- [x] Refactor `tests/BootstrapTest.php` to replace `$this->assertTrue(true)` with `$this->assertTrue(class_exists(\BaconQrCode\Encoder\Encoder::class))`.
- [x] Refactor `tests/SecureConnectionsTest.php` to check for `curl_ca_bundle` in `$GLOBALS` using `array_key_exists`.
- [x] Add `testCurlCaBundleOverrideFromIni` in `tests/SecureConnectionsTest.php` to test dynamic `curl_ca_bundle` override from config file in isolated environment.

## Phase 3: Refactor Static Scanning Tests
- [x] Refactor `tests/CsrfResetTest.php` to instantiate `PhpTokenScanner` and assert GET method CSRF reset sequence.
- [x] Refactor `tests/CsrfResetTest.php` to verify `hasBlindCsrfReset` using token sequence counts.
- [x] Refactor `tests/SslEnforcementTest.php` to instantiate `PhpTokenScanner` and verify secure cURL SSL option sequences.
- [x] Refactor `tests/SslEnforcementTest.php` to assert that insecure options like `verify_peer => false` are absent.

## Phase 4: Verification
- [x] Execute test suite using PHPUnit to ensure all new and refactored tests pass successfully.
- [x] Manually verify comment-immunity by adding arbitrary comments in source files and confirming static scans still pass.
