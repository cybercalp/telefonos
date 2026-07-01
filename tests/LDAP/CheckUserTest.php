<?php

use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../lib/ldap_checkuser.php';
require_once __DIR__ . '/../../private/config.php';

class CheckUserTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
        $GLOBALS['ldap_domain'] = ['DOMAIN', 'domain.local'];
        $GLOBALS['ldap_only_user'] = '';
        $GLOBALS['ldap_admuser'] = 'admin';
        $GLOBALS['ldap_admpwd'] = 'pass';
        $GLOBALS['ldap_dn'] = 'dc=example,dc=com';

        // Stub get_ldap_uri() for Client::factory()
        $GLOBALS['ldap_protocol'] = 'ldap://';
        $GLOBALS['ldap_host'] = 'localhost';
        $GLOBALS['ldap_port'] = 389;
        if (!function_exists('get_ldap_uri')) {
            eval('function get_ldap_uri() { global $ldap_protocol, $ldap_host, $ldap_port; return $ldap_protocol . $ldap_host . ":" . $ldap_port; }');
        }
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    public function testCheckUserWithMockClientInjectsConnection(): void
    {
        $mockClient = $this->createMock(\LDAP\Client::class);
        $mockClient->expects($this->atLeastOnce())
            ->method('getResource')
            ->willReturn(null);

        check_user('testuser', $mockClient);

        // When resource is null, it should set connection error
        $this->assertIsArray($_SESSION['mensaje']);
        $this->assertStringContainsString('No se pudo conectar', $_SESSION['mensaje'][0]);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testCheckUserEmptyInputStillConnects(): void
    {
        $mockClient = $this->createMock(\LDAP\Client::class);
        $mockClient->expects($this->once())
            ->method('getResource')
            ->willReturn(null);

        check_user('', $mockClient);

        $this->assertIsArray($_SESSION['mensaje']);
        $this->assertStringContainsString('No se pudo conectar', $_SESSION['mensaje'][0]);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testCheckUserDefaultFactoryFallback(): void
    {
        // Without explicit Client, uses factory — will fail gracefully (no real LDAP)
        @check_user('nonexistent_user');

        $this->assertIsArray($_SESSION['mensaje']);
    }

    public function testCheckUserWithNullClientUsesFactory(): void
    {
        // Passing null should trigger factory fallback
        @check_user('testuser', null);

        $this->assertIsArray($_SESSION['mensaje']);
    }
}
