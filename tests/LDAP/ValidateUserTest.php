<?php

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

class ValidateUserTest extends TestCase
{
    protected function setUp(): void
    {
        // Set up server vars needed by dependent functions (db_attemptslogin etc.)
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        // Set up globals so config.php and check_user() don't fatal
        $GLOBALS['ldap_protocol'] = 'ldap://';
        $GLOBALS['ldap_host'] = 'dc.example.com';
        $GLOBALS['ldap_port'] = 389;
        $GLOBALS['ldap_dn'] = 'dc=example,dc=com';
        $GLOBALS['ldap_domain'] = ['DOMAIN', 'example.com'];
        $GLOBALS['ldap_admuser'] = 'admin';
        $GLOBALS['ldap_admpwd'] = 'password';
        $GLOBALS['config'] = ['medley' => ['UDS_URL' => '']];

        // Ensure the production file is loaded
        if (!function_exists('validate_user')) {
            require_once __DIR__ . '/../../lib/ldap_validateuser.php';
        }
    }

    protected function tearDown(): void
    {
        $keys = ['ldap_host', 'ldap_port', 'ldap_dn', 'ldap_admpwd', 'ldap_protocol',
                 'ldap_domain', 'ldap_admuser', 'config'];
        foreach ($keys as $key) {
            unset($GLOBALS[$key]);
        }
    }

    // ═══════════════════════════════════════════════════════
    // Empty input guard tests — no LDAP needed
    // ═══════════════════════════════════════════════════════

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testEmptyUsernameShowsFieldRequiredMessage(): void
    {
        @validate_user('', 'password');
        $this->assertContains(
            'Por favor, completa todos los campos.',
            $_SESSION['mensaje']
        );
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testEmptyPasswordShowsFieldRequiredMessage(): void
    {
        @validate_user('user@example.com', '');
        $this->assertContains(
            'Por favor, completa todos los campos.',
            $_SESSION['mensaje']
        );
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testBothEmptyShowsFieldRequiredMessage(): void
    {
        @validate_user('', '');
        $this->assertContains(
            'Por favor, completa todos los campos.',
            $_SESSION['mensaje']
        );
    }

    // ═══════════════════════════════════════════════════════
    // Injection tests — mock Client
    // ═══════════════════════════════════════════════════════

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testInjectedClientIsUsedInsteadOfFactory(): void
    {
        // Create a native LDAP\Connection to satisfy type constraints
        $nativeConn = ldap_connect('ldap://localhost');

        // Create mock Client
        $mockClient = $this->getMockBuilder(\LDAP\Client::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getResource'])
            ->getMock();

        $mockClient->expects($this->atLeastOnce())
            ->method('getResource')
            ->willReturn($nativeConn);

        // Call with injected client
        @validate_user('user@example.com', 'password', $mockClient);

        // getResource() should have been called on the mock
        // (Verified by expect()->atLeastOnce() above)
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testInjectedClientGetResourceIsCalled(): void
    {
        $nativeConn = ldap_connect('ldap://localhost');

        $mockClient = $this->getMockBuilder(\LDAP\Client::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getResource'])
            ->getMock();

        $mockClient->expects($this->atLeastOnce())
            ->method('getResource')
            ->willReturn($nativeConn);

        @validate_user('user@example.com', 'password', $mockClient);

        // Test passes if getResource() was invoked (mock expectation above)
        $this->assertTrue(true);
    }

    // ═══════════════════════════════════════════════════════
    // Session state tests
    // ═══════════════════════════════════════════════════════

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testSessionMensajeIsArrayAfterValidation(): void
    {
        @validate_user('user@example.com', 'password');
        $this->assertIsArray($_SESSION['mensaje']);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testSessionMensajeCssIsSetAfterValidation(): void
    {
        @validate_user('user@example.com', 'password');
        $this->assertArrayHasKey('mensaje_css', $_SESSION);
    }

    // ═══════════════════════════════════════════════════════
    // Error code path tests (integration-level — verify
    // that the error switch structure exists via code coverage)
    // ═══════════════════════════════════════════════════════

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testValidateUserHandlesFailedBindGracefully(): void
    {
        // With LDAP extension installed but no real server, ldap_bind() will
        // fail with a connection error. The function should handle this
        // without PHP fatal errors and populate session messages.
        $nativeConn = ldap_connect('ldap://localhost');

        $mockClient = $this->getMockBuilder(\LDAP\Client::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getResource'])
            ->getMock();

        $mockClient->method('getResource')->willReturn($nativeConn);

        @validate_user('user@example.com', 'wrongpassword', $mockClient);

        // After validation, session mensaje should exist (even if error)
        $this->assertIsArray($_SESSION['mensaje']);
    }
}
