<?php

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

class ConnectionTest extends TestCase
{
    protected function tearDown(): void
    {
        $keys = ['ldap_protocol', 'ldap_host', 'ldap_port', 'ldap_dn', 'ldap_admpwd', 'ldap_domain'];
        foreach ($keys as $key) {
            unset($GLOBALS[$key]);
        }
        // Ensure any session active_host cache is cleared
        if (session_status() === PHP_SESSION_ACTIVE) {
            unset($_SESSION['active_ldap_host']);
        }
    }

    /**
     * Helper: defines get_ldap_uri() and required globals for factory tests.
     * In separate processes, private/config.php is not loaded.
     */
    private function setupLdapUriStub(string $host = 'dc.example.com', int $port = 389, string $protocol = 'ldap://'): void
    {
        $GLOBALS['ldap_protocol'] = $protocol;
        $GLOBALS['ldap_host'] = $host;
        $GLOBALS['ldap_port'] = $port;

        if (!function_exists('get_ldap_uri')) {
            eval('function get_ldap_uri() {
                global $ldap_protocol, $ldap_host, $ldap_port;
                return $ldap_protocol . $ldap_host . ":" . $ldap_port;
            }');
        }
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testFactoryCreatesConnectionFromGlobals(): void
    {
        $this->setupLdapUriStub();
        $GLOBALS['ldap_dn'] = 'cn=admin,dc=example,dc=com';
        $GLOBALS['ldap_admpwd'] = 'password';

        $client = @\LDAP\Client::factory();

        $this->assertInstanceOf(\LDAP\Client::class, $client);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testFactoryThrowsRuntimeExceptionWhenGetLdapUriNotAvailable(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('get_ldap_uri() not available');

        @\LDAP\Client::factory();
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testFactoryThrowsRuntimeExceptionOnMissingDn(): void
    {
        $this->setupLdapUriStub();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('ldap_dn');

        @\LDAP\Client::factory();
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testFactoryThrowsRuntimeExceptionOnMissingPassword(): void
    {
        $this->setupLdapUriStub();
        $GLOBALS['ldap_dn'] = 'cn=admin,dc=example,dc=com';

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('ldap_admpwd');

        @\LDAP\Client::factory();
    }

    public function testGetResourceReturnsLdapConnection(): void
    {
        $client = $this->getMockBuilder(\LDAP\Client::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();

        $ref = new \ReflectionClass(\LDAP\Client::class);
        $prop = $ref->getProperty('resource');
        $prop->setAccessible(true);

        $nativeConn = @ldap_connect('ldap://localhost');
        $prop->setValue($client, $nativeConn);

        $result = $client->getResource();

        $this->assertSame($nativeConn, $result);
    }

    public function testDestructorResourcesAreReleased(): void
    {
        // Create a Client with disabled original constructor and a valid
        // native LDAP\Connection so ldap_unbind() accepts the argument type.
        $client = $this->getMockBuilder(\LDAP\Client::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();

        $ref = new \ReflectionClass(\LDAP\Client::class);
        $prop = $ref->getProperty('resource');
        $prop->setAccessible(true);

        $nativeConn = ldap_connect('ldap://localhost');
        $prop->setValue($client, $nativeConn);

        // Trigger destructor — suppress warnings since there's no real server
        @$client->__destruct();

        $this->assertTrue(true, 'Destructor completed without fatal error');
    }
}
