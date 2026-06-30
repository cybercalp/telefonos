<?php
use PHPUnit\Framework\TestCase;

class LdapEncodePasswordTest extends TestCase
{
    protected function setUp(): void
    {
        require_once __DIR__ . '/../lib/ldap_encodepwd.php';
    }

    public function testEncodePasswordWrapsInQuotes(): void
    {
        $result = encode_password('test');
        $hex = bin2hex($result);

        // UTF-16LE of " is 22 00 → hex starts/ends with 2200
        $this->assertStringStartsWith('2200', $hex, 'Output should start with UTF-16LE quote bytes (2200)');
        $this->assertTrue(
            substr($hex, -4) === '2200',
            'Output should end with UTF-16LE quote bytes (2200)'
        );
    }

    public function testEncodePasswordUtf16LeEncoding(): void
    {
        $password = 'test123';
        $result = encode_password($password);

        // Each char in UTF-16LE = 2 bytes; +2 chars for surrounding quotes
        $expectedLength = (strlen($password) + 2) * 2;

        $this->assertEquals(
            $expectedLength,
            strlen($result),
            'Output length should be (password length + 2 quotes) × 2 bytes for UTF-16LE'
        );
    }

    public function testEncodePasswordWithAsciiInput(): void
    {
        $result = encode_password('secret');

        $this->assertNotEmpty($result);
        $this->assertIsString($result);
    }

    public function testEncodePasswordWithSpecialChars(): void
    {
        $result = encode_password('señor€');
        $asciiResult = encode_password('senor');

        $this->assertNotEmpty($result);
        // Output with special chars should differ from ASCII-only equivalent
        $this->assertNotEquals(
            bin2hex($asciiResult),
            bin2hex($result),
            'Encoding with special chars must differ from ASCII-only equivalent'
        );
    }

    public function testEncodePasswordOutputIsNotEmpty(): void
    {
        $result = encode_password('anything');

        $this->assertNotEmpty($result);
        $this->assertGreaterThan(0, strlen($result));
    }

    public function testEncodePasswordHexFormat(): void
    {
        $result = encode_password('test');
        $hex = bin2hex($result);

        // "test" in UTF-16LE with surrounding quotes:
        // " = 2200, t = 7400, e = 6500, s = 7300, t = 7400, " = 2200
        $expected = '220074006500730074002200';

        $this->assertEquals($expected, $hex, 'Known hex pattern for encode_password("test")');
    }
}
