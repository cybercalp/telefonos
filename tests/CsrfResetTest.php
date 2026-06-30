<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/PhpTokenScanner.php';
use Tests\PhpTokenScanner;

class CsrfResetTest extends TestCase
{
    public function testLoginResetsCsrfOnGet(): void
    {
        $content = file_get_contents(__DIR__ . '/../login.php');
        $this->assertTrue($this->hasCsrfResetOnGet($content), "login.php should reset csrf_token_ok on GET request");
        $this->assertFalse($this->hasBlindCsrfReset($content), "login.php should not blindly reset csrf_token_ok");
    }

    public function testChangePwdResetsCsrfOnGet(): void
    {
        $content = file_get_contents(__DIR__ . '/../change_pwd.php');
        $this->assertTrue($this->hasCsrfResetOnGet($content), "change_pwd.php should reset csrf_token_ok on GET request");
        $this->assertFalse($this->hasBlindCsrfReset($content), "change_pwd.php should not blindly reset csrf_token_ok");
    }

    public function testRescueResetsCsrfOnGet(): void
    {
        $content = file_get_contents(__DIR__ . '/../rescue.php');
        $this->assertTrue($this->hasCsrfResetOnGet($content), "rescue.php should reset csrf_token_ok on GET request");
        $this->assertFalse($this->hasBlindCsrfReset($content), "rescue.php should not blindly reset csrf_token_ok");
    }

    public function testTotpResetsCsrfOnGet(): void
    {
        $content = file_get_contents(__DIR__ . '/../totp.php');
        $this->assertTrue($this->hasCsrfResetOnGet($content), "totp.php should reset csrf_token_ok on GET request");
        $this->assertFalse($this->hasBlindCsrfReset($content), "totp.php should not blindly reset csrf_token_ok");
    }

    private function hasCsrfResetOnGet(string $content): bool
    {
        $scanner = new PhpTokenScanner($content);
        $sequence = ['if', '(', '$_SERVER', '[', 'REQUEST_METHOD', ']', '===', 'GET', ')', '{', '$_SESSION', '[', 'csrf_token_ok', ']', '=', 'true', ';', '}'];
        return $scanner->hasSequence($sequence);
    }

    private function hasBlindCsrfReset(string $content): bool
    {
        $scanner = new PhpTokenScanner($content);
        $totalAssignments = $scanner->countSequence(['$_SESSION', '[', 'csrf_token_ok', ']', '=', 'true', ';']);
        $getAssignments = $scanner->countSequence(['if', '(', '$_SERVER', '[', 'REQUEST_METHOD', ']', '===', 'GET', ')', '{', '$_SESSION', '[', 'csrf_token_ok', ']', '=', 'true', ';', '}']);
        
        $initAssignments1 = $scanner->countSequence(['if', '(', '!', 'isset', '(', '$_SESSION', '[', 'csrf_token_ok', ']', ')', ')', '{', '$_SESSION', '[', 'csrf_token_ok', ']', '=', 'true', ';', '}']);
        $initAssignments2 = $scanner->countSequence(['if', '(', 'empty', '(', '$_SESSION', '[', 'csrf_token_ok', ']', ')', ')', '{', '$_SESSION', '[', 'csrf_token_ok', ']', '=', 'true', ';', '}']);
        
        $safeAssignments = $getAssignments + $initAssignments1 + $initAssignments2;
        return $totalAssignments > $safeAssignments;
    }
}
