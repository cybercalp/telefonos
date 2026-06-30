<?php
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

/**
 * Tests for lib/preventvalidpost.php
 *
 * NOTE: Full CSRF rejection path testing (invalid token → header()+exit) is limited
 * because the file calls exit() on invalid POST CSRF tokens, which kills the child
 * test process before assertions can run.
 *
 * The exit() call on line 28 of preventvalidpost.php terminates the PHP process
 * immediately. Even with @runInSeparateProcess, the child process dies and PHPUnit
 * cannot collect test results from it.
 *
 * To fully test the CSRF rejection path, the production code would need to be
 * refactored: extract the CSRF validation into a function that returns a result
 * instead of calling header()+exit directly.
 */
class PreventValidPostTest extends TestCase
{
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
