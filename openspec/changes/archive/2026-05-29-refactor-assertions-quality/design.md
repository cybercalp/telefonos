# Technical Design: Refactor Test Assertions and Static Scans Quality

## 1. Technical Approach
To eliminate tautological assertions and fragile regex-based static scans, we will introduce a lexical scanner helper for tests, `PhpTokenScanner`, which filters out insignificant tokens (whitespaces and comments). This ensures static assertions check exact syntax trees regardless of styling, indentation, or commenting.

Tautologies are replaced by strict type and array existence checks (`class_exists`, `array_key_exists`), and environment variable overrides are verified by dynamically patching config files within isolated child processes.

## 2. Architecture Decisions
### 2.1 `Tests\PhpTokenScanner`
A robust lexical analyzer will be implemented at `tests/PhpTokenScanner.php`:
- **Lexing**: Utilizing standard `token_get_all()` to tokenize target PHP files.
- **Filtering**: Removing `T_WHITESPACE`, `T_COMMENT`, and `T_DOC_COMMENT` tokens to produce a clean sequential stream.
- **Normalization**: Standardizing single and double-quoted strings (by stripping outer quotes) and keywords/constants (via case-insensitive matching).
- **Matching Core API**:
  - `hasSequence(array $sequence): bool`
  - `countSequence(array $sequence): int`

### Data Flow for Static Scanner:
```
[PHP Source File] 
       │
       ▼ (token_get_all)
[Raw Tokens Stream]
       │
       ▼ (Filter: T_WHITESPACE, T_COMMENT, T_DOC_COMMENT)
[Filtered Tokens] ───(Normalized Match)◄─── [Expected Sequence]
       │
       ▼
[Assertion Result] (True / False)
```

## 3. Detailed File Changes

### `tests/PhpTokenScanner.php` (New)
A helper class under the `Tests` namespace.
```php
<?php
namespace Tests;

class PhpTokenScanner
{
    private array $tokens = [];

    public function __construct(string $content)
    {
        $all = token_get_all($content);
        $this->tokens = array_values(array_filter($all, function ($t) {
            if (is_array($t)) {
                return !in_array($t[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT]);
            }
            return true;
        }));
    }

    private function tokensMatch($expected, $actualToken): bool
    {
        $actualType = is_array($actualToken) ? $actualToken[0] : null;
        $actualText = is_array($actualToken) ? $actualToken[1] : $actualToken;

        if (is_int($expected)) {
            return $actualType === $expected;
        }

        if ($actualType === T_CONSTANT_ENCAPSED_STRING) {
            $actualText = trim($actualText, "'\"");
        }

        $expectedText = trim($expected, "'\"");
        return strcasecmp($expectedText, $actualText) === 0;
    }

    public function hasSequence(array $sequence): bool
    {
        $seqCount = count($sequence);
        if ($seqCount === 0) {
            return true;
        }

        $tokenCount = count($this->tokens);
        for ($i = 0; $i <= $tokenCount - $seqCount; $i++) {
            $match = true;
            for ($j = 0; $j < $seqCount; $j++) {
                if (!$this->tokensMatch($sequence[$j], $this->tokens[$i + $j])) {
                    $match = false;
                    break;
                }
            }
            if ($match) {
                return true;
            }
        }
        return false;
    }

    public function countSequence(array $sequence): int
    {
        $seqCount = count($sequence);
        if ($seqCount === 0) {
            return 0;
        }

        $count = 0;
        $tokenCount = count($this->tokens);
        for ($i = 0; $i <= $tokenCount - $seqCount; ) {
            $match = true;
            for ($j = 0; $j < $seqCount; $j++) {
                if (!$this->tokensMatch($sequence[$j], $this->tokens[$i + $j])) {
                    $match = false;
                    break;
                }
            }
            if ($match) {
                $count++;
                $i += $seqCount;
            } else {
                $i++;
            }
        }
        return $count;
    }
}
```

### `tests/BootstrapTest.php`
- Replace tautological `$this->assertTrue(true)` in `testBootstrap()` with a functional verification of autoloading:
  ```php
  $this->assertTrue(class_exists(\BaconQrCode\Encoder\Encoder::class));
  ```

### `tests/SecureConnectionsTest.php`
- Update `testCurlCaBundleConfigExposed()` to:
  ```php
  $this->assertTrue(array_key_exists('curl_ca_bundle', $GLOBALS));
  ```
- Implement `testCurlCaBundleOverrideFromIni()` to write a mock path into a temporary `config.ini`, require `config.php`, and verify that `$curl_ca_bundle` receives the overridden value, wrapped in `try...finally` to guarantee rollback.

### `tests/CsrfResetTest.php`
- Integrate `PhpTokenScanner`.
- Assert CSRF resets on `GET` utilizing sequence matching:
  - Sequence: `['if', '(', '$_SERVER', '[', 'REQUEST_METHOD', ']', '===', 'GET', ')', '{', '$_SESSION', '[', 'csrf_token_ok', ']', '=', 'true', ';', '}']`
- Assert `hasBlindCsrfReset()` is false: verify that global assignments of `$_SESSION['csrf_token_ok'] = true;` do not exceed occurrences enclosed within valid `GET` blocks.

### `tests/SslEnforcementTest.php`
- Integrate `PhpTokenScanner`.
- Replace string checks with robust token sequence validations:
  - Check cURL option sequences: `['curl_setopt', '(', '$ch', ',', 'CURLOPT_SSL_VERIFYPEER', ',', 'true', ')']`, `['CURLOPT_SSL_VERIFYHOST', '=>', '2']`, etc.
  - Assert that disabled secure flags (e.g. `'verify_peer' => false` or `CURLOPT_SSL_VERIFYPEER, false`) are NOT present.

## 4. Testing Strategy
- **Unit Testing the Scanner**: Create `tests/PhpTokenScannerTest.php` or verify scanner accuracy within the existing tests to ensure it ignores comments, whitespaces, and quote variations.
- **CI Execution**: Run `./vendor/bin/phpunit` using the `Unit` test suite to verify all tests pass without regression.
- **Strict TDD Compliance**: Run the test suite before and after making any change to ensure tests fail on invalid configurations and pass on correct ones.
