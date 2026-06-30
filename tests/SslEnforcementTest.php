<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/PhpTokenScanner.php';
use Tests\PhpTokenScanner;

class SslEnforcementTest extends TestCase
{
    public function testGetTokenEnforcesSsl(): void
    {
        $content = file_get_contents(__DIR__ . '/../lib/get_token.php');
        $scanner = new PhpTokenScanner($content);
        
        // Assert secure options are present
        $this->assertTrue($scanner->hasSequence(['curl_setopt', '(', '$ch', ',', 'CURLOPT_SSL_VERIFYPEER', ',', 'true', ')']), "get_token.php must set CURLOPT_SSL_VERIFYPEER to true");
        $this->assertTrue($scanner->hasSequence(['curl_setopt', '(', '$ch', ',', 'CURLOPT_SSL_VERIFYHOST', ',', '2', ')']), "get_token.php must set CURLOPT_SSL_VERIFYHOST to 2");
        $this->assertTrue($scanner->hasSequence(['curl_setopt', '(', '$ch', ',', 'CURLOPT_CAINFO', ',', '$curl_ca_bundle', ')']), "get_token.php must support custom CURLOPT_CAINFO");
        
        // Assert insecure options are absent
        $this->assertFalse($scanner->hasSequence(['curl_setopt', '(', '$ch', ',', 'CURLOPT_SSL_VERIFYPEER', ',', 'false', ')']), "get_token.php must not disable SSL verification with false");
        $this->assertFalse($scanner->hasSequence(['curl_setopt', '(', '$ch', ',', 'CURLOPT_SSL_VERIFYPEER', ',', '0', ')']), "get_token.php must not disable SSL verification with 0");
    }

    public function testSyncPresenceEnforcesSsl(): void
    {
        $content = file_get_contents(__DIR__ . '/../lib/sync_presence.php');
        $scanner = new PhpTokenScanner($content);
        
        // Assert secure options are present
        $this->assertTrue($scanner->hasSequence(['CURLOPT_SSL_VERIFYPEER', '=>', 'true']), "sync_presence.php must set CURLOPT_SSL_VERIFYPEER to true");
        $this->assertTrue($scanner->hasSequence(['CURLOPT_SSL_VERIFYHOST', '=>', '2']), "sync_presence.php must set CURLOPT_SSL_VERIFYHOST to 2");
        $this->assertTrue($scanner->hasSequence(['curl_setopt', '(', '$ch', ',', 'CURLOPT_CAINFO', ',', '$curl_ca_bundle', ')']), "sync_presence.php must support custom CURLOPT_CAINFO");
        
        // Assert insecure options are absent
        $this->assertFalse($scanner->hasSequence(['CURLOPT_SSL_VERIFYPEER', '=>', 'false']), "sync_presence.php must not disable SSL verification with false");
        $this->assertFalse($scanner->hasSequence(['CURLOPT_SSL_VERIFYPEER', '=>', '0']), "sync_presence.php must not disable SSL verification with 0");
    }

    public function testTestNativeEnforcesSsl(): void
    {
        $filePath = __DIR__ . '/../lib/test_native.php';
        if (!file_exists($filePath)) {
            $this->markTestSkipped('lib/test_native.php is gitignored (contains secrets), not available in CI.');
        }
        $content = file_get_contents($filePath);
        $scanner = new PhpTokenScanner($content);
        
        // Assert cURL secure options are present
        $this->assertTrue($scanner->hasSequence(['curl_setopt', '(', '$ch', ',', 'CURLOPT_SSL_VERIFYPEER', ',', 'true', ')']), "test_native.php cURL must set CURLOPT_SSL_VERIFYPEER to true");
        $this->assertTrue($scanner->hasSequence(['curl_setopt', '(', '$ch', ',', 'CURLOPT_SSL_VERIFYHOST', ',', '2', ')']), "test_native.php cURL must set CURLOPT_SSL_VERIFYHOST to 2");
        
        // Assert stream secure options are present
        $this->assertTrue($scanner->hasSequence(['"verify_peer"', '=>', 'true']), "test_native.php stream must set verify_peer to true");
        $this->assertTrue($scanner->hasSequence(['"verify_peer_name"', '=>', 'true']), "test_native.php stream must set verify_peer_name to true");
        $this->assertTrue($scanner->hasSequence(['"cafile"', ']', '=', '$curl_ca_bundle']), "test_native.php stream must support custom cafile");
        
        // Assert cURL insecure options are absent
        $this->assertFalse($scanner->hasSequence(['curl_setopt', '(', '$ch', ',', 'CURLOPT_SSL_VERIFYPEER', ',', 'false', ')']), "test_native.php cURL must not disable SSL verification");
        $this->assertFalse($scanner->hasSequence(['curl_setopt', '(', '$ch', ',', 'CURLOPT_SSL_VERIFYPEER', ',', '0', ')']), "test_native.php cURL must not disable SSL verification");
        
        // Assert stream insecure options are absent
        $this->assertFalse($scanner->hasSequence(['"verify_peer"', '=>', 'false']), "test_native.php stream must not disable verify_peer");
        $this->assertFalse($scanner->hasSequence(['"verify_peer_name"', '=>', 'false']), "test_native.php stream must not disable verify_peer_name");
    }
}
