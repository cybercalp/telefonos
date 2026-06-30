<?php
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

/**
 * Tests for lib/preventvalidpost.php
 *
 * The CSRF validation logic was extracted into validate_csrf_post() which returns
 * true/false instead of calling header()+exit, making it fully testable.
 * The top-level POST block still calls header()+exit for backward compat.
 */
class PreventValidPostTest extends TestCase
{
    // --- Tests for the extracted validate_csrf_post() function ---

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testValidateCsrfPostReturnsTrueForMatchingTokens(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['REQUEST_URI'] = '/test';
        $_SESSION = ['csrf_token' => 'abc123', 'csrf_token_ok' => true];
        if (!defined('SQLITE_DB_PATH')) define('SQLITE_DB_PATH', ':memory:');
        require __DIR__ . '/../lib/preventvalidpost.php';

        $result = validate_csrf_post('abc123', 'abc123');
        $this->assertTrue($result);
        $this->assertTrue($_SESSION['csrf_token_ok']);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testValidateCsrfPostReturnsFalseForMismatchedTokens(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['REQUEST_URI'] = '/test';
        $_SESSION = ['csrf_token' => 'abc123', 'csrf_token_ok' => true];
        if (!defined('SQLITE_DB_PATH')) define('SQLITE_DB_PATH', ':memory:');
        require __DIR__ . '/../lib/preventvalidpost.php';

        $result = validate_csrf_post('wrong', 'abc123');
        $this->assertFalse($result);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testValidateCsrfPostUnsetsTokenOnFailure(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['REQUEST_URI'] = '/test';
        $_SESSION = ['csrf_token' => 'abc123', 'csrf_token_ok' => true];
        if (!defined('SQLITE_DB_PATH')) define('SQLITE_DB_PATH', ':memory:');
        require __DIR__ . '/../lib/preventvalidpost.php';

        validate_csrf_post('wrong', 'abc123');
        $this->assertArrayNotHasKey('csrf_token', $_SESSION);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testValidateCsrfPostSetsCsrfTokenOkFalseOnFailure(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['REQUEST_URI'] = '/test';
        $_SESSION = ['csrf_token' => 'abc123', 'csrf_token_ok' => true];
        if (!defined('SQLITE_DB_PATH')) define('SQLITE_DB_PATH', ':memory:');
        require __DIR__ . '/../lib/preventvalidpost.php';

        validate_csrf_post('wrong', 'abc123');
        $this->assertFalse($_SESSION['csrf_token_ok']);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testValidateCsrfPostSetsErrorMessageOnFailure(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['REQUEST_URI'] = '/test';
        $_SESSION = ['csrf_token' => 'abc123', 'csrf_token_ok' => true];
        if (!defined('SQLITE_DB_PATH')) define('SQLITE_DB_PATH', ':memory:');
        require __DIR__ . '/../lib/preventvalidpost.php';

        validate_csrf_post('wrong', 'abc123');
        $this->assertArrayHasKey('mensaje', $_SESSION);
        $this->assertIsArray($_SESSION['mensaje']);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testValidateCsrfPostWithEmptyProvidedToken(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['REQUEST_URI'] = '/test';
        $_SESSION = ['csrf_token' => 'abc123', 'csrf_token_ok' => true];
        if (!defined('SQLITE_DB_PATH')) define('SQLITE_DB_PATH', ':memory:');
        require __DIR__ . '/../lib/preventvalidpost.php';

        $result = validate_csrf_post('', 'abc123');
        $this->assertFalse($result);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testValidateCsrfPostWithEmptySessionToken(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['REQUEST_URI'] = '/test';
        $_SESSION = ['csrf_token' => '', 'csrf_token_ok' => true];
        if (!defined('SQLITE_DB_PATH')) define('SQLITE_DB_PATH', ':memory:');
        require __DIR__ . '/../lib/preventvalidpost.php';

        $result = validate_csrf_post('abc123', '');
        $this->assertFalse($result);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testValidateCsrfPostDoesNotCallExit(): void
    {
        // Confirms we reach this line after a failure — no process killed
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['REQUEST_URI'] = '/test';
        $_SESSION = ['csrf_token' => 'abc123', 'csrf_token_ok' => true];
        if (!defined('SQLITE_DB_PATH')) define('SQLITE_DB_PATH', ':memory:');
        require __DIR__ . '/../lib/preventvalidpost.php';

        validate_csrf_post('wrong', 'abc123');
        $this->assertTrue(true);
    }

    // --- Integration tests for the full include path ---
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testFileIncludesWithoutErrorOnGetRequest(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['REQUEST_URI'] = '/test';
        $_SESSION = ['csrf_token' => 'test-token', 'csrf_token_ok' => true];

        // Use in-memory SQLite to avoid disk dependency
        if (!defined('SQLITE_DB_PATH')) {
            define('SQLITE_DB_PATH', ':memory:');
        }

        require __DIR__ . '/../lib/preventvalidpost.php';

        // Variables are local to this method scope when require'd inside a method
        $this->assertIsInt($attempts, '$attempts should be an integer');
        $this->assertIsInt($blocked_until, '$blocked_until should be an integer');
        $this->assertIsInt($now, '$now should be an integer');
        $this->assertIsInt($tiempo_restante, '$tiempo_restante should be an integer');
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testFileWithValidCsrfTokenOnPostDoesNotCrash(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['REQUEST_URI'] = '/test';
        $_POST['csrf_token'] = 'valid-token';
        $_SESSION = ['csrf_token' => 'valid-token', 'csrf_token_ok' => true];

        // Use in-memory SQLite to avoid disk dependency
        if (!defined('SQLITE_DB_PATH')) {
            define('SQLITE_DB_PATH', ':memory:');
        }

        require __DIR__ . '/../lib/preventvalidpost.php';

        // If we reach here, CSRF validation passed and exit() was not called
        $this->assertTrue(true, 'File should include without exiting on valid CSRF token');
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testVariablesInitializedAfterInclude(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REMOTE_ADDR'] = '192.168.1.1';
        $_SERVER['REQUEST_URI'] = '/test';
        $_SESSION = [];

        // Use in-memory SQLite to avoid disk dependency
        if (!defined('SQLITE_DB_PATH')) {
            define('SQLITE_DB_PATH', ':memory:');
        }

        require __DIR__ . '/../lib/preventvalidpost.php';

        // Variables are local to this method scope when require'd inside a method
        $this->assertTrue(isset($attempts), '$attempts must be defined');
        $this->assertTrue(isset($blocked_until), '$blocked_until must be defined');
        $this->assertTrue(isset($now), '$now must be defined');
        $this->assertTrue(isset($tiempo_restante), '$tiempo_restante must be defined');

        // With empty/fresh DB, these should have default values
        $this->assertEquals(0, $attempts, 'attempts should default to 0 with empty DB');
        $this->assertEquals(0, $blocked_until, 'blocked_until should default to 0 with empty DB');
        $this->assertGreaterThan(0, $now, 'now should be a current timestamp > 0');
    }
}
