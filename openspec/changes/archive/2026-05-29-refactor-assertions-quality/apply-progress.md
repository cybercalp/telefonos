# apply-progress.md: Refactor Test Assertions and Static Scans Quality

## TDD Cycle Evidence Table

| Task | Test File | Layer | Safety Net | RED | GREEN | TRIANGULATE | REFACTOR |
|------|-----------|-------|------------|-----|-------|-------------|----------|
| Phase 1: PhpTokenScanner Helper | `tests/PhpTokenScannerTest.php` | Test Helper / Static Analysis | PHPUnit | ✅ Yes, failed as expected with skeletal helper methods | ✅ Yes, implementation done and basic tests pass | ✅ Yes, added quote/case/type/count checks | ✅ No additional refactoring needed; implementation clean and concise |
| Phase 2: Refactor Tautology Tests | `tests/BootstrapTest.php`, `tests/SecureConnectionsTest.php` | Config / Environment / Bootstrap | PHPUnit | ✅ Yes, tested non-existent class/variable and failed as expected | ✅ Yes, updated assertions to check the correct class and array key | ✅ Yes, verified actual path override vs non-matching path override | ✅ Verified global variables scoped correctly when required inside separate processes |
| Phase 3: Refactor Static Scanning Tests | `tests/CsrfResetTest.php`, `tests/SslEnforcementTest.php` | Security / cURL / Stream Context | PHPUnit | ✅ Yes, asserted POST instead of GET / false instead of true options and failed | ✅ Yes, implemented sequences mapping correctly for tokenized matching | ✅ Yes, verified all target PHP files (login, pwd, rescue, totp, get_token, sync, test_native) | ✅ Ensured full immunity to comment and style variations, including nesting levels |

## Detailed Progress Log

### Phase 1: PhpTokenScanner Helper
1. Created skeleton in `tests/PhpTokenScanner.php` to define the target class.
2. Created unit tests in `tests/PhpTokenScannerTest.php` with a failing assertion checking sequence matching (RED).
3. Implemented the lexical analyzer in `tests/PhpTokenScanner.php` filtering out comments and whitespaces, performing string/quote normalization, and matching sequences case-insensitively (GREEN).
4. Discovered syntax nuance (PHP variables are tokenized as a single `T_VARIABLE` rather than `$` and identifier separately), corrected test sequence, and tests passed.
5. Expanded `tests/PhpTokenScannerTest.php` to triangulate functionality (handling quote normalization, case-insensitivity, matching by token type T_VARIABLE, non-matching sequences, and exact sequence counting) (TRIANGULATE).

### Phase 2: Refactor Tautology Tests
1. **BootstrapTest**: Replaced `$this->assertTrue(true)` with `$this->assertTrue(class_exists(\BaconQrCode\Encoder\NonExistentClass::class))` which failed as expected (RED). Then updated to checking the correct class `\BaconQrCode\Encoder\Encoder::class` which passed (GREEN).
2. **SecureConnectionsTest - Exposure**: Replaced old isset/null assertion with checking for a non-existent global variable using `array_key_exists` which failed as expected (RED). Then changed to `curl_ca_bundle` (GREEN).
3. **SecureConnectionsTest - Scope Fix**: Discovered that declaring `global $curl_ca_bundle;` before `require` is necessary because PHPUnit runs the test inside a local function scope; now variables assigned inside the required config.ini/php file correctly populate `$GLOBALS['curl_ca_bundle']`.
4. **SecureConnectionsTest - Override**: Implemented `testCurlCaBundleOverrideFromIni()` to write a custom config.ini with dynamic override, required config.php, and asserted override. Reached RED state by expecting a path different than the written one. Then updated the ini input to match, achieving GREEN state. Proper `try...finally` wraps ensure cleanup of ini file.

### Phase 3: Refactor Static Scanning Tests
1. **CsrfResetTest**: Refactored static scanner to utilize `PhpTokenScanner`. Set failing checks by asserting POST instead of GET (RED). Then updated sequences to check for GET and safely permitted non-isset/empty initializations (GREEN). All 4 target files passed without fragile regex constraints.
2. **SslEnforcementTest**: Replaced fragile string contains checks with robust token sequence matches. Set failing assertions by asserting `false` for secure parameters (RED). Then corrected to verify correct secure options (`true` / `2`) and custom CA bundles, and ensured that insecure options are absent (GREEN).

### Phase 4: Verification
1. Executed full PHPUnit test suite to confirm 100% test completion (19 tests, 55 assertions).
2. Manually verified comment-immunity by adding multiline, block, and inline comments directly inside `curl_setopt` calls in `lib/get_token.php`. The test suite verified their immunity and completed successfully without any adjustments, proving complete immunity.

