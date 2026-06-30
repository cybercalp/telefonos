<?php
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

class SecureConnectionsTest extends TestCase
{
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testLdapTlsReqcertDefaultInProduction(): void
    {
        putenv('APP_ENV=production');
        
        require __DIR__ . '/../private/config.php';
        
        $this->assertEquals('demand', getenv('LDAPTLS_REQCERT'), "LDAPTLS_REQCERT should default to demand in production");
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testLdapTlsReqcertDefaultInDevelopment(): void
    {
        putenv('APP_ENV=development');
        
        require __DIR__ . '/../private/config.php';
        
        $this->assertEquals('never', getenv('LDAPTLS_REQCERT'), "LDAPTLS_REQCERT should default to never in development");
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testCurlCaBundleConfigExposed(): void
    {
        global $curl_ca_bundle;
        require __DIR__ . '/../private/config.php';
        
        $this->assertTrue(array_key_exists('curl_ca_bundle', $GLOBALS), "Global variable \$curl_ca_bundle should be declared");
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testLdapTlsReqcertOverrideFromIni(): void
    {
        $iniPath = __DIR__ . '/../private/config.ini';
        $backupPath = __DIR__ . '/../private/config.ini.bak';
        copy($iniPath, $backupPath);
        
        try {
            $customContent = "[medley]\napp_env = \"production\"\n[ldap]\nldap_reqcert = \"never\"\n";
            file_put_contents($iniPath, $customContent);
            
            require __DIR__ . '/../private/config.php';
            
            $this->assertEquals('never', getenv('LDAPTLS_REQCERT'), "LDAPTLS_REQCERT should be overridden to never from config.ini");
        } finally {
            copy($backupPath, $iniPath);
            unlink($backupPath);
        }
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testCurlCaBundleOverrideFromIni(): void
    {
        $iniPath = __DIR__ . '/../private/config.ini';
        $backupPath = __DIR__ . '/../private/config.ini.bak';
        copy($iniPath, $backupPath);
        
        try {
            $customContent = "[medley]\ncurl_ca_bundle = \"/path/to/custom/ca-bundle.crt\"\n";
            file_put_contents($iniPath, $customContent);
            
            global $curl_ca_bundle;
            require __DIR__ . '/../private/config.php';
            
            $this->assertEquals('/path/to/custom/ca-bundle.crt', $curl_ca_bundle, "curl_ca_bundle should be overridden from config.ini");
        } finally {
            copy($backupPath, $iniPath);
            unlink($backupPath);
        }
    }
}
