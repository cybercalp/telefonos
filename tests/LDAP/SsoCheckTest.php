<?php

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

class SsoCheckTest extends TestCase
{
    protected function setUp(): void
    {
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['REQUEST_METHOD'] = 'GET';

        // Redirect error_log to temp file to prevent PHPUnit from
        // treating stderr error_log output as unexpected test output.
        ini_set('error_log', sys_get_temp_dir() . '/phpunit_sso_error.log');

        // Set up globals for config.php
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

        if (!function_exists('sso_check')) {
            require_once __DIR__ . '/../../lib/sso_check.php';
        }
    }

    protected function tearDown(): void
    {
        $keys = ['ldap_host', 'ldap_port', 'ldap_dn', 'ldap_protocol',
                 'ldap_domain', 'ldap_user', 'ldap_pass', 'config',
                 'ldap_admuser', 'ldap_admpwd'];
        foreach ($keys as $key) {
            unset($GLOBALS[$key]);
        }
    }

    // ═══════════════════════════════════════════════════════
    // Already authenticated skip test
    // ═══════════════════════════════════════════════════════

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testAlreadyAuthenticatedSkipsSso(): void
    {
        $_SESSION['is_authenticated'] = true;

        $result = sso_check();

        $this->assertFalse($result, 'Should return false when already authenticated');
    }

    // ═══════════════════════════════════════════════════════
    // POST request skip test
    // ═══════════════════════════════════════════════════════

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testPostRequestSkipsSso(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $result = sso_check();

        $this->assertFalse($result, 'Should return false on POST to avoid conflict with manual login');
    }

    // ═══════════════════════════════════════════════════════
    // No SSO headers test
    // ═══════════════════════════════════════════════════════

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testNoRemoteUserReturnsFalse(): void
    {
        // Neither REMOTE_USER nor AUTH_USER set
        unset($_SERVER['REMOTE_USER'], $_SERVER['AUTH_USER']);

        $result = @sso_check();

        $this->assertFalse($result, 'Should return false when no SSO headers present');
    }

    // ═══════════════════════════════════════════════════════
    // REMOTE_USER parsing test
    // ═══════════════════════════════════════════════════════

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testRemoteUserWithDomainBackslash(): void
    {
        $_SERVER['REMOTE_USER'] = 'DOMAIN\\jdoe';

        // LDAP search will fail (no real server), so returns false
        $result = @sso_check();

        $this->assertFalse($result);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testRemoteUserWithAtSign(): void
    {
        $_SERVER['REMOTE_USER'] = 'jdoe@example.com';

        $result = @sso_check();

        $this->assertFalse($result);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testAuthUserFallback(): void
    {
        // AUTH_USER as fallback when REMOTE_USER not set
        $_SERVER['AUTH_USER'] = 'DOMAIN\\jdoe';

        $result = @sso_check();

        $this->assertFalse($result);
    }

    // ═══════════════════════════════════════════════════════
    // Injection test
    // ═══════════════════════════════════════════════════════

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testInjectedClientGetResourceIsCalled(): void
    {
        $_SERVER['REMOTE_USER'] = 'DOMAIN\\jdoe';

        $nativeConn = ldap_connect('ldap://localhost');

        $mockClient = $this->getMockBuilder(\LDAP\Client::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getResource'])
            ->getMock();

        $mockClient->expects($this->atLeastOnce())
            ->method('getResource')
            ->willReturn($nativeConn);

        @sso_check($mockClient);

        // getResource() must have been called on the mock
        // (verified by expect()->atLeastOnce() above)
    }

    // ═══════════════════════════════════════════════════════
    // Graceful degradation
    // ═══════════════════════════════════════════════════════

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testSsoCheckHandlesFailedConnectionGracefully(): void
    {
        $_SERVER['REMOTE_USER'] = 'DOMAIN\\jdoe';

        $nativeConn = ldap_connect('ldap://localhost');

        $mockClient = $this->getMockBuilder(\LDAP\Client::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getResource'])
            ->getMock();

        $mockClient->method('getResource')->willReturn($nativeConn);

        $result = @sso_check($mockClient);

        // With no real LDAP server, should return false without fatals
        $this->assertFalse($result);
    }
}
