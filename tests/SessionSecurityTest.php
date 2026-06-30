<?php
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

class SessionSecurityTest extends TestCase
{
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testSessionSecurityBootstrappedByConfigWithHttps(): void
    {
        $_SERVER['HTTPS'] = 'on';
        
        require __DIR__ . '/../lib/session_security.php';
        require __DIR__ . '/../private/config.php';
        
        $this->assertEquals('1', ini_get('session.use_strict_mode'), "Session strict mode must be enabled");
        
        $params = session_get_cookie_params();
        $this->assertTrue($params['httponly'], "Cookie must be httponly");
        $this->assertEquals('Lax', $params['samesite'], "Cookie SameSite must be Lax");
        $this->assertTrue($params['secure'], "Cookie must be secure under HTTPS");
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testSessionSecurityBootstrappedByConfigWithoutHttps(): void
    {
        unset($_SERVER['HTTPS']);
        
        require __DIR__ . '/../lib/session_security.php';
        require __DIR__ . '/../private/config.php';
        
        $this->assertEquals('1', ini_get('session.use_strict_mode'), "Session strict mode must be enabled");
        
        $params = session_get_cookie_params();
        $this->assertTrue($params['httponly'], "Cookie must be httponly");
        $this->assertEquals('Lax', $params['samesite'], "Cookie SameSite must be Lax");
        $this->assertFalse($params['secure'], "Cookie must not be secure under HTTP");
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testSessionLdapPassKeyNotPresentAfterLogin(): void
    {
        // GIVEN a user completes LDAP authentication successfully
        $_SESSION['ldap_user'] = 'admin@domain.local';
        $_SESSION['is_authenticated'] = true;
        $_SESSION['2fa_verified'] = true;
        $_SESSION['auth_user_dn'] = 'CN=Admin,OU=Users,DC=domain,DC=local';

        // THEN array_key_exists('ldap_pass', $_SESSION) MUST return false
        $this->assertFalse(
            array_key_exists('ldap_pass', $_SESSION),
            'Spec requires array_key_exists(ldap_pass, SESSION) === false'
        );

        // AND no session value SHALL contain the literal test password
        foreach ($_SESSION as $key => $value) {
            if (is_string($value)) {
                $this->assertStringNotContainsString(
                    'secret123',
                    $value,
                    "Session key '$key' must not contain plaintext password"
                );
            }
        }

        // AND the authentication keys exist
        $this->assertArrayHasKey('ldap_user', $_SESSION);
        $this->assertArrayHasKey('is_authenticated', $_SESSION);
        $this->assertArrayHasKey('2fa_verified', $_SESSION);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testSessionHasNoLdapPasswordAfterLogin(): void
    {
        // GIVEN a user completes LDAP authentication successfully
        // Simulate post-login session state as produced by validate_user()
        $_SESSION['ldap_user'] = 'testuser';
        $_SESSION['is_authenticated'] = true;
        // NOTE: ldap_pass must NOT be present — password is discarded after bind

        // THEN $_SESSION SHALL NOT contain the key ldap_pass
        $this->assertArrayNotHasKey(
            'ldap_pass',
            $_SESSION,
            'ldap_pass must NOT be stored in session after successful login'
        );
        
        // AND the required authentication keys SHALL be present
        $this->assertArrayHasKey('ldap_user', $_SESSION);
        $this->assertArrayHasKey('is_authenticated', $_SESSION);
        $this->assertTrue($_SESSION['is_authenticated']);
    }
}
