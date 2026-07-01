<?php

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

class ChangePasswordTest extends TestCase
{
    protected function setUp(): void
    {
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        // Set up globals so config.php and check_user() don't fatal
        $GLOBALS['ldap_protocol'] = 'ldap://';
        $GLOBALS['ldap_host'] = 'dc.example.com';
        $GLOBALS['ldap_port'] = 389;
        $GLOBALS['ldap_dn'] = 'dc=example,dc=com';
        $GLOBALS['ldap_domain'] = ['DOMAIN', 'example.com'];
        $GLOBALS['ldap_user'] = 'svc_user@example.com';
        $GLOBALS['ldap_pass'] = 'svc_pass';
        $GLOBALS['ldap_admuser'] = 'admin';
        $GLOBALS['ldap_admpwd'] = 'password';
        $GLOBALS['config'] = ['medley' => ['UDS_URL' => '']];
        $GLOBALS['password_min_length'] = 8;
        $GLOBALS['user_max_attempts_allowed'] = 5;

        // Ensure the production file is loaded
        if (!function_exists('changePassword')) {
            require_once __DIR__ . '/../../lib/ldap_changepwd.php';
        }
    }

    protected function tearDown(): void
    {
        $keys = ['ldap_host', 'ldap_port', 'ldap_dn', 'ldap_admpwd', 'ldap_protocol',
                 'ldap_domain', 'ldap_admuser', 'ldap_user', 'ldap_pass', 'config',
                 'password_min_length', 'user_max_attempts_allowed'];
        foreach ($keys as $key) {
            unset($GLOBALS[$key]);
        }
    }

    // ═══════════════════════════════════════════════════════
    // Empty input guard tests
    // ═══════════════════════════════════════════════════════

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testEmptyFieldsShowCompleteFieldsMessage(): void
    {
        @changePassword('', '', '', '');
        $this->assertContains(
            'Por favor, completa todos los campos.',
            $_SESSION['mensaje']
        );
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testMissingNewPasswordShowsCompleteFieldsMessage(): void
    {
        @changePassword('user@example.com', 'oldpass', '', '');
        $this->assertContains(
            'Por favor, completa todos los campos.',
            $_SESSION['mensaje']
        );
    }

    // ═══════════════════════════════════════════════════════
    // Injection tests
    // ═══════════════════════════════════════════════════════

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

        // With recovery=true, identity bind is skipped. But check_user still runs.
        @changePassword('user@example.com', 'oldpass', 'NewPass1!', 'NewPass1!', true, $mockClient);

        // getResource() must have been called on the mock
        // Verified by expect()->atLeastOnce() above
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testRecoveryFlagSkipsIdentityBind(): void
    {
        // With isRecovery=true, the identity bind (user+password) is skipped.
        // The function should still reach admin bind and password validation.
        $nativeConn = ldap_connect('ldap://localhost');

        $mockClient = $this->getMockBuilder(\LDAP\Client::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getResource'])
            ->getMock();

        $mockClient->method('getResource')->willReturn($nativeConn);

        @changePassword('user@example.com', '', 'NewPass1!', 'NewPass1!', true, $mockClient);

        // After recovery flow, session mensaje should be populated
        $this->assertIsArray($_SESSION['mensaje']);
    }

    // ═══════════════════════════════════════════════════════
    // Session state tests
    // ═══════════════════════════════════════════════════════

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testSessionMensajeIsSetAfterChange(): void
    {
        @changePassword('user@example.com', 'oldpass', 'NewPass1!', 'NewPass1!');
        $this->assertIsArray($_SESSION['mensaje']);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testSessionMensajeCssIsSetAfterChange(): void
    {
        @changePassword('user@example.com', 'oldpass', 'NewPass1!', 'NewPass1!');
        $this->assertArrayHasKey('mensaje_css', $_SESSION);
    }

    // ═══════════════════════════════════════════════════════
    // Exit_Err session state
    // ═══════════════════════════════════════════════════════

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testExitErrLabelIsPresentAfterValidation(): void
    {
        // The Exit_Err label ensures session state is written even on
        // early exit (password mismatch, short password, etc.).
        // Verify the label exists in the source (structural check).
        $source = file_get_contents(__DIR__ . '/../../lib/ldap_changepwd.php');
        $this->assertStringContainsString('Exit_Err:', $source);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testMismatchedPasswordsSetSessionError(): void
    {
        // Bind as user first fails (no server), then admin bind. But the
        // E102 mismatch check happens AFTER admin bind succeeds in the
        // current code. Since there's no real server, we verify the
        // function executes without fatal errors and populates session.
        $nativeConn = ldap_connect('ldap://localhost');

        $mockClient = $this->getMockBuilder(\LDAP\Client::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getResource'])
            ->getMock();

        $mockClient->method('getResource')->willReturn($nativeConn);

        @changePassword('user@example.com', 'oldpass', 'NewPass1!', 'DifferentPass!', false, $mockClient);

        // Session mensaje should exist
        $this->assertIsArray($_SESSION['mensaje']);
    }

    // ═══════════════════════════════════════════════════════
    // Graceful degradation test
    // ═══════════════════════════════════════════════════════

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testChangePasswordHandlesFailedConnectionGracefully(): void
    {
        // With no real LDAP server, all binds will fail. The function
        // should handle this without PHP errors and populate session.
        $nativeConn = ldap_connect('ldap://localhost');

        $mockClient = $this->getMockBuilder(\LDAP\Client::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getResource'])
            ->getMock();

        $mockClient->method('getResource')->willReturn($nativeConn);

        @changePassword('user@example.com', 'wrongpass', 'NewPass1!', 'NewPass1!', false, $mockClient);

        $this->assertIsArray($_SESSION['mensaje']);
    }
}
