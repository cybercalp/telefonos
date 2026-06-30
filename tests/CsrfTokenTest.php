<?php
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

class CsrfTokenTest extends TestCase
{
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testGetTokenGeneratesNewTokenWhenEmpty(): void
    {
        require __DIR__ . '/../lib/csrf.php';

        $token = get_csrf_token();

        $this->assertNotEmpty($token, 'Token should not be empty');
        $this->assertEquals($token, $_SESSION['csrf_token'], 'Token should be stored in session');
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testGetTokenReturnsSameTokenOnSubsequentCalls(): void
    {
        require __DIR__ . '/../lib/csrf.php';

        $token1 = get_csrf_token();
        $token2 = get_csrf_token();

        $this->assertSame($token1, $token2, 'Subsequent calls should return the same token');
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testGetTokenGenerates64CharHexString(): void
    {
        require __DIR__ . '/../lib/csrf.php';

        $token = get_csrf_token();

        $this->assertEquals(64, strlen($token), 'Token should be exactly 64 characters');
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{64}$/',
            $token,
            'Token should be a 64-char lowercase hex string'
        );
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testVerifyTokenReturnsTrueForMatchingToken(): void
    {
        require __DIR__ . '/../lib/csrf.php';

        $token = get_csrf_token();

        $this->assertTrue(verify_csrf_token($token), 'Matching token should verify successfully');
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testVerifyTokenReturnsFalseForMismatchedToken(): void
    {
        require __DIR__ . '/../lib/csrf.php';

        get_csrf_token(); // generate a token in session

        $this->assertFalse(
            verify_csrf_token('wrong-token-value'),
            'Mismatched token should fail verification'
        );
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testVerifyTokenReturnsFalseWhenSessionTokenEmpty(): void
    {
        // Start session manually so csrf.php guard won't trigger start,
        // then we never populate csrf_token
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        require __DIR__ . '/../lib/csrf.php';

        $this->assertFalse(
            verify_csrf_token('some-token'),
            'Should return false when no CSRF token exists in session'
        );
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testVerifyTokenReturnsFalseWhenProvidedTokenEmpty(): void
    {
        require __DIR__ . '/../lib/csrf.php';

        get_csrf_token(); // populate session token

        $this->assertFalse(
            verify_csrf_token(''),
            'Should return false when provided token is empty string'
        );
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testGetTokenFromRequestPost(): void
    {
        require __DIR__ . '/../lib/csrf.php';

        $_POST['csrf_token'] = 'post-token-value';

        $this->assertEquals('post-token-value', get_token_from_request());
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testGetTokenFromRequestGet(): void
    {
        require __DIR__ . '/../lib/csrf.php';

        $_GET['csrf_token'] = 'get-token-value';

        $this->assertEquals('get-token-value', get_token_from_request());
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testGetTokenFromRequestHeader(): void
    {
        require __DIR__ . '/../lib/csrf.php';

        $_SERVER['HTTP_X_CSRF_TOKEN'] = 'header-token-value';

        $this->assertEquals('header-token-value', get_token_from_request());
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testGetTokenFromRequestReturnsNullWhenNotFound(): void
    {
        require __DIR__ . '/../lib/csrf.php';

        $this->assertNull(get_token_from_request());
    }
}
