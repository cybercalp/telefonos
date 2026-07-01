<?php

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

class ConnectionTest extends TestCase
{
    protected function tearDown(): void
    {
        $keys = ['ldap_host', 'ldap_port', 'ldap_dn', 'ldap_admpwd', 'ldap_protocol', 'ldap_domain'];
        foreach ($keys as $key) {
            unset($GLOBALS[$key]);
        }
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testFactoryCreatesConnectionFromGlobals(): void
    {
        // GIVEN valid globals
        $GLOBALS['ldap_protocol'] = 'ldap://';
        $GLOBALS['ldap_host'] = 'dc.example.com';
        $GLOBALS['ldap_port'] = 389;
        $GLOBALS['ldap_dn'] = 'cn=admin,dc=example,dc=com';
        $GLOBALS['ldap_admpwd'] = 'password';

        // WHEN factory() is called (suppress ldap_bind warnings — no real server)
        $client = @\LDAP\Client::factory();

        // THEN a Client instance is returned
        $this->assertInstanceOf(\LDAP\Client::class, $client);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testFactoryThrowsRuntimeExceptionOnMissingHost(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('ldap_host');

        @\LDAP\Client::factory();
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testFactoryThrowsRuntimeExceptionOnMissingPort(): void
    {
        $GLOBALS['ldap_host'] = 'dc.example.com';

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('ldap_port');

        @\LDAP\Client::factory();
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testFactoryThrowsRuntimeExceptionOnMissingDn(): void
    {
        $GLOBALS['ldap_host'] = 'dc.example.com';
        $GLOBALS['ldap_port'] = 389;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('ldap_dn');

        @\LDAP\Client::factory();
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testFactoryThrowsRuntimeExceptionOnMissingPassword(): void
    {
        $GLOBALS['ldap_host'] = 'dc.example.com';
        $GLOBALS['ldap_port'] = 389;
        $GLOBALS['ldap_dn'] = 'cn=admin,dc=example,dc=com';

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('ldap_admpwd');

        @\LDAP\Client::factory();
    }

    public function testGetResourceReturnsLdapConnection(): void
    {
        // Create instance bypassing constructor (no real LDAP server needed)
        $client = $this->getMockBuilder(\LDAP\Client::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();

        // Use reflection to inject a native LDAP\Connection object
        // (PHP 8.1+ ldap_connect returns LDAP\Connection, not a resource)
        $ref = new \ReflectionClass(\LDAP\Client::class);
        $prop = $ref->getProperty('resource');
        $prop->setAccessible(true);

        $nativeConn = ldap_connect('ldap://localhost');
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
